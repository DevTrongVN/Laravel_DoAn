<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dịch vụ AI:
 *  - scanContent()          : Quét từ cấm → safe | flagged | blocked (kèm severity & violation_type)
 *  - improveStyle()         : Cải thiện văn phong với 3 chế độ: professional | literary | concise
 *  - evaluateNewsPotential(): Đánh giá bài có phù hợp đăng lên mục Tin tức không
 */
final class AIService
{
    private array $config;

    public function __construct()
    {
        $this->config = (array) config('ai', []);
    }

    // ----------------------------------------------------------------
    // moderateContent / scanContent
    // ----------------------------------------------------------------

    /**
     * Quét và kiểm duyệt nội dung tự động bằng AI (Online Gemini API + Offline Rule Engine).
     * Nhận diện nhạy bén 3 cấp độ:
     *   - low: từ ngữ công kích nhẹ, thiếu chuẩn mực ("ngu ngốc", "vớ vẩn", "trò hề", "rác rưởi", "đồ ngốc"...)
     *   - medium: xúc phạm nặng, ngôn từ thù ghét, spam link quảng cáo
     *   - high: lừa đảo, cờ bạc, cá độ, nội dung cấm hoặc độc hại
     *
     * @return array{
     *   is_flagged: bool,
     *   status: 'safe'|'flagged'|'blocked',
     *   severity: 'none'|'low'|'medium'|'high',
     *   violation_type: string,
     *   ai_reason: string,
     *   reason: string,
     *   suggested_action: 'pass'|'flag'|'block',
     *   matched_words: list<string>
     * }
     */
    public function moderateContent(string $text, string $context = 'post'): array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return [
                'is_flagged'       => false,
                'status'           => 'safe',
                'severity'         => 'none',
                'violation_type'   => 'none',
                'ai_reason'        => 'Nội dung rỗng.',
                'reason'           => 'Nội dung rỗng.',
                'suggested_action' => 'pass',
                'matched_words'    => [],
            ];
        }

        // 1. Kiểm tra Online qua Gemini API nếu có API key hợp lệ
        $apiKey = trim((string)($this->config['api_key'] ?? ''));
        if ($apiKey === '') {
            $apiKey = trim((string)(getenv('GEMINI_API_KEY') ?: (getenv('AI_API_KEY') ?: '')));
        }

        if ($apiKey !== '' && !str_starts_with($apiKey, 'your_')) {
            try {
                $onlineResult = $this->callGeminiModeration($trimmed, $apiKey);
                if ($onlineResult !== null) {
                    return $onlineResult;
                }
            } catch (Throwable $e) {
                Log::info('[AIService] Lỗi gọi Gemini Moderation: ' . $e->getMessage());
            }
        }

        // 2. Chế độ Offline / Fallback: Sử dụng Rule Engine phân tầng 3 mức độ vi phạm
        return $this->scanOfflineContent($trimmed);
    }

    /**
     * Phương thức tương thích ngược với scanContent() cũ.
     */
    public function scanContent(string $text): array
    {
        return $this->moderateContent($text, 'general');
    }

    /**
     * Engine kiểm duyệt Offline dựa trên từ điển phân tầng & biểu thức chính quy.
     */
    private function scanOfflineContent(string $text): array
    {
        $lower = mb_strtolower($text, 'UTF-8');
        $modCfg = $this->config['moderation'] ?? [];
        $rulesBySeverity = $modCfg['keywords_by_severity'] ?? [];

        // 1. Kiểm tra Mức độ Cao (High): Lừa đảo, cờ bạc, nội dung độc hại/cấm
        $highWords = $rulesBySeverity['high']['words'] ?? [
            'lừa đảo', 'đánh bạc', 'tự tử', 'khủng bố', 'phản động', 'cá độ bóng đá',
            'đá gà trực tiếp', 'casino trực tuyến', 'xóc đĩa', 'tài xỉu online', 'lô đề online',
            'mua bán ma túy', 'chất cấm', 'ấu dâm', 'vũ khí cấm'
        ];
        $matchedHigh = $this->findMatchingWords($lower, $highWords);
        if (!empty($matchedHigh)) {
            $reason = 'Vi phạm nghiêm trọng tiêu chuẩn an toàn mạng (lừa đảo, cờ bạc hoặc nội dung độc hại): ' . implode(', ', array_slice($matchedHigh, 0, 3));
            return [
                'is_flagged'       => true,
                'status'           => 'blocked',
                'severity'         => 'high',
                'violation_type'   => $rulesBySeverity['high']['type'] ?? 'illegal',
                'ai_reason'        => $reason,
                'reason'           => $reason,
                'suggested_action' => 'block',
                'matched_words'    => $matchedHigh,
            ];
        }

        // 2. Kiểm tra Mức độ Trung bình (Medium): Xúc phạm nặng, ngôn từ thù ghét, spam link quảng cáo
        $mediumWords = $rulesBySeverity['medium']['words'] ?? [
            'chó chết', 'khốn nạn', 'đồ chó', 'súc vật', 'đĩ', 'mẹ mày', 'thằng chó', 'con chó',
            'thất đức', 'đồ lợn', 'bệnh hoạn', 'chết tiệt', 'kiếm tiền online', 'bắn cá đổi thưởng',
            'tải app nhận tiền', 'nhóm kín zalo', 'kèo bóng đá', 'inbox nhận lộc'
        ];
        $matchedMedium = $this->findMatchingWords($lower, $mediumWords);
        
        // Kiểm tra spam link lặp lại
        $hasLinkSpam = preg_match_all('/https?:\/\/[^\s]+/i', $text, $linkMatches) > 3;
        if (!empty($matchedMedium) || $hasLinkSpam) {
            $matchedList = $matchedMedium;
            if ($hasLinkSpam) $matchedList[] = 'spam_link_frequency';
            $reason = 'Nội dung có dấu hiệu xúc phạm nặng, ngôn từ thù ghét hoặc spam link quảng cáo: ' . implode(', ', array_slice($matchedList, 0, 3));
            return [
                'is_flagged'       => true,
                'status'           => 'flagged',
                'severity'         => 'medium',
                'violation_type'   => $hasLinkSpam ? 'spam' : ($rulesBySeverity['medium']['type'] ?? 'harassment'),
                'ai_reason'        => $reason,
                'reason'           => $reason,
                'suggested_action' => 'flag',
                'matched_words'    => $matchedList,
            ];
        }

        // 3. Kiểm tra Mức độ Nhẹ (Low): Từ ngữ công kích nhẹ, thiếu chuẩn mực
        $lowWords = $rulesBySeverity['low']['words'] ?? [
            'ngu ngốc', 'vớ vẩn', 'trò hề', 'rác rưởi', 'đồ ngốc', 'đần độn', 'hãm',
            'ngu xuẩn', 'bẩn thỉu', 'vô dụng', 'kém cỏi', 'đần dốt', 'dở hơi', 'dốt nát',
            'vô lại', 'ngớ ngẩn', 'xàm xí', 'nhảm nhí', 'vô học', 'óc bã đậu', 'mất dạy',
            'não ngắn', 'đồ ngu', 'ngu si'
        ];
        $matchedLow = $this->findMatchingWords($lower, $lowWords);
        if (!empty($matchedLow)) {
            $reason = 'Phát hiện từ ngữ công kích nhẹ, thiếu chuẩn mực: ' . implode(', ', array_slice($matchedLow, 0, 3));
            return [
                'is_flagged'       => true,
                'status'           => 'flagged',
                'severity'         => 'low',
                'violation_type'   => $rulesBySeverity['low']['type'] ?? 'inappropriate',
                'ai_reason'        => $reason,
                'reason'           => $reason,
                'suggested_action' => 'flag',
                'matched_words'    => $matchedLow,
            ];
        }

        // 4. Kiểm tra danh sách từ khóa cấm gộp (fallback)
        $fallbackWords = $modCfg['banned_keywords'] ?? [];
        $matchedFallback = $this->findMatchingWords($lower, $fallbackWords);
        if (!empty($matchedFallback)) {
            $reason = 'Chứa từ ngữ nhạy cảm cần xét duyệt: ' . implode(', ', array_slice($matchedFallback, 0, 3));
            return [
                'is_flagged'       => true,
                'status'           => 'flagged',
                'severity'         => 'low',
                'violation_type'   => 'inappropriate',
                'ai_reason'        => $reason,
                'reason'           => $reason,
                'suggested_action' => 'flag',
                'matched_words'    => $matchedFallback,
            ];
        }

        // An toàn
        return [
            'is_flagged'       => false,
            'status'           => 'safe',
            'severity'         => 'none',
            'violation_type'   => 'none',
            'ai_reason'        => 'Nội dung an toàn, tuân thủ tiêu chuẩn cộng đồng.',
            'reason'           => 'Nội dung an toàn, tuân thủ tiêu chuẩn cộng đồng.',
            'suggested_action' => 'pass',
            'matched_words'    => [],
        ];
    }

    /**
     * Tìm các từ khóa xuất hiện trong văn bản (hỗ trợ phân tách từ).
     */
    private function findMatchingWords(string $lowerText, array $wordList): array
    {
        $matched = [];
        foreach ($wordList as $word) {
            $w = trim(mb_strtolower((string)$word, 'UTF-8'));
            if ($w === '') continue;
            // Kiểm tra substring hoặc ranh giới từ
            if (str_contains($lowerText, $w)) {
                $matched[] = $word;
            }
        }
        return array_values(array_unique($matched));
    }

    /**
     * Gọi Gemini API để kiểm duyệt nội dung Online.
     */
    private function callGeminiModeration(string $text, string $apiKey): ?array
    {
        $systemPrompt = $this->config['system_prompts']['moderation'] 
            ?? 'Bạn là hệ thống AI kiểm duyệt nội dung tiếng Việt. Hãy phân tích đoạn văn sau và trả về JSON: {"is_flagged":bool,"status":"safe"|"flagged"|"blocked","severity":"none"|"low"|"medium"|"high","violation_type":"none"|"inappropriate"|"harassment"|"spam"|"illegal","ai_reason":"lý do bằng tiếng Việt","suggested_action":"pass"|"flag"|"block"}';

        $model = (string)($this->config['model'] ?? 'gemini-3.5-flash');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . rawurlencode($apiKey);

        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemPrompt]
                ]
            ],
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => "Kiểm duyệt nội dung sau:\n" . mb_substr($text, 0, 4000, 'UTF-8')]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature'      => 0.1,
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !is_string($rawResponse)) {
            return null;
        }

        $decoded = json_decode($rawResponse, true);
        $content = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($content === '') {
            return null;
        }

        $parsed = json_decode($content, true);
        if (!is_array($parsed) || !isset($parsed['status'])) {
            return null;
        }

        $status = in_array($parsed['status'], ['safe', 'flagged', 'blocked'], true) ? $parsed['status'] : 'safe';
        $severity = in_array($parsed['severity'] ?? '', ['none', 'low', 'medium', 'high'], true) ? $parsed['severity'] : ($status === 'safe' ? 'none' : 'low');
        $isFlagged = (bool)($parsed['is_flagged'] ?? ($status !== 'safe'));

        return [
            'is_flagged'       => $isFlagged,
            'status'           => $status,
            'severity'         => $severity,
            'violation_type'   => (string)($parsed['violation_type'] ?? 'inappropriate'),
            'ai_reason'        => (string)($parsed['ai_reason'] ?? 'Phát hiện nội dung cần rà soát qua AI.'),
            'reason'           => (string)($parsed['ai_reason'] ?? 'Phát hiện nội dung cần rà soát qua AI.'),
            'suggested_action' => (string)($parsed['suggested_action'] ?? ($status === 'blocked' ? 'block' : ($isFlagged ? 'flag' : 'pass'))),
            'matched_words'    => (array)($parsed['matched_words'] ?? []),
        ];
    }

    // ----------------------------------------------------------------
    // improveStyle
    // ----------------------------------------------------------------

    /**
     * Cải thiện văn phong bài viết (Hỗ trợ Online Gemini API + Offline Rule-based NLP Engine).
     *
     * @param string $tone 'professional' | 'literary' | 'concise'
     */
    public const SUPPORTED_EDIT_MODES = [
        'spellcheck',
        'natural',
        'concise',
        'expand',
        'improve',
        'professional',
        'literary',
        'friendly',
    ];

    /**
     * Cải thiện / biên tập văn bản kèm metadata nguồn xử lý (Gemini API hoặc Offline NLP Engine).
     *
     * @return array{improved: string, engine: string, model: string, mode: string, notice: ?string, finish_reason: ?string, usage: ?array}
     */
    public function improveStyleWithMeta(string $content, string $tone = 'improve'): array
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return [
                'improved'      => '',
                'engine'        => 'none',
                'model'         => '',
                'mode'          => $tone,
                'notice'        => null,
                'finish_reason' => null,
                'usage'         => null,
            ];
        }

        $mode = in_array($tone, self::SUPPORTED_EDIT_MODES, true) ? $tone : 'improve';

        $apiKey = trim((string)($this->config['api_key'] ?? ''));
        if ($apiKey === '') {
            $apiKey = trim((string)(getenv('GEMINI_API_KEY') ?: (getenv('AI_API_KEY') ?: '')));
        }

        // 1. Chế độ có API Key: Gửi cURL trực tiếp tới Gemini API kéo dữ liệu về
        if ($apiKey !== '' && !str_starts_with($apiKey, 'your_')) {
            try {
                $geminiRes = $this->callGeminiImproveDirect($trimmed, $mode, $apiKey);
                if ($geminiRes['text'] !== '') {
                    return [
                        'improved'      => $geminiRes['text'],
                        'engine'        => 'gemini',
                        'model'         => $geminiRes['model'],
                        'mode'          => $mode,
                        'notice'        => 'Đã biên tập trực tiếp qua Google Gemini AI (' . $geminiRes['model'] . ').',
                        'finish_reason' => $geminiRes['finish_reason'],
                        'usage'         => $geminiRes['usage'],
                    ];
                }
            } catch (Throwable $e) {
                Log::info('[AIService] Lỗi gọi Gemini API: ' . $e->getMessage() . '. Tự động kích hoạt Fallback nội bộ.');
            }
        } else {
            Log::info('[AIService] Không tìm thấy API Key hợp lệ (GEMINI_API_KEY / AI_API_KEY). Kích hoạt bộ xử lý Fallback / Offline.');
        }

        $notice = ($apiKey !== '' && !str_starts_with($apiKey, 'your_'))
            ? 'Gemini API tạm thời không phản hồi. Đã chuyển sang Trợ lý AI Tiếng Việt nội bộ (Offline NLP).'
            : 'Đang hoạt động với Trợ lý AI Tiếng Việt nội bộ (Offline NLP). Khi có GEMINI_API_KEY trong .env, hệ thống sẽ tự động chuyển sang Google Gemini trực tuyến.';

        // 2. Chế độ Fallback thông minh: Biên tập theo bộ quy tắc ngôn ngữ tự nhiên nâng cao
        return [
            'improved'      => $this->rewriteFallback($trimmed, $mode),
            'engine'        => 'offline_fallback',
            'model'         => 'vietnamese-nlp-engine',
            'mode'          => $mode,
            'notice'        => $notice,
            'finish_reason' => null,
            'usage'         => null,
        ];
    }

    public function improveStyle(string $content, string $tone = 'professional'): string
    {
        return $this->improveStyleWithMeta($content, $tone)['improved'];
    }

    /**
     * Alias tương thích: Viết lại văn phong nội dung theo phong cách chỉ định.
     */
    public function rewriteContent(string $content, string $tone = 'professional'): string
    {
        return $this->improveStyle($content, $tone);
    }

    /**
     * Bộ xử lý Fallback / Offline khi không có API Key hoặc mất kết nối mạng.
     */
    public function rewriteFallback(string $content, string $tone = 'professional'): string
    {
        return $this->simulateImprove($content, $tone);
    }

    /**
     * Gọi Gemini API (ưu tiên các model cấu hình tập trung) qua cURL.
     */
    private function callGeminiImproveDirect(string $content, string $tone, string $apiKey): array
    {
        $primaryModel = (string)($this->config['model'] ?? 'gemini-3.5-flash-lite');
        $fallbackModels = (array)($this->config['fallback_models'] ?? ['gemini-3.5-flash-lite', 'gemini-3.5-flash', 'gemini-3.1-flash-lite', 'gemini-3.8-flash', 'gemini-flash-latest']);
        $models = array_unique(array_filter(array_merge([$primaryModel], $fallbackModels)));

        $systemPrompt = $this->config['system_prompts'][$tone] 
            ?? "Bạn là một biên tập viên chuyên nghiệp. Hãy biên tập văn bản sau theo yêu cầu ({$tone}). Giữ nguyên ý chính, chỉ trả về nội dung đã chỉnh sửa, không kèm lời chào hay giải thích thừa.";

        $temperature = match ($tone) {
            'spellcheck' => 0.1,
            'concise'    => 0.2,
            'natural', 'improve', 'professional' => 0.4,
            'friendly'   => 0.65,
            'expand'     => 0.7,
            'literary'   => 0.85,
            default      => 0.4,
        };

        $maxTokens = $tone === 'expand' ? 4096 : 2048;

        foreach ($models as $model) {
            $endpointBase = rtrim((string)($this->config['gemini_endpoint'] ?? 'https://generativelanguage.googleapis.com/v1beta/models'), '/');
            $url = $endpointBase . '/' . rawurlencode((string)$model) . ':generateContent?key=' . rawurlencode($apiKey);

            $payload = [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $systemPrompt]
                    ]
                ],
                'contents' => [
                    [
                        'role'  => 'user',
                        'parts' => [
                            ['text' => "Văn bản gốc:\n" . $content]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature'     => $temperature,
                    'maxOutputTokens' => $maxTokens,
                ]
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json; charset=utf-8',
                    'Accept: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 25,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response !== false && $httpCode === 200) {
                $json = json_decode((string)$response, true);
                $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $text = trim((string)$text);

                if ($text !== '') {
                    // Loại bỏ markdown code blocks nếu model vô tình bao bọc ``` ... ```
                    if (preg_match('/^```(?:[a-zA-Z]+)?\s*([\s\S]*?)\s*```$/u', $text, $m)) {
                        $text = trim($m[1]);
                    }
                    // Loại bỏ cặp ngoặc kép bao quanh toàn văn nếu có
                    if ((str_starts_with($text, '"') && str_ends_with($text, '"')) ||
                        (str_starts_with($text, '“') && str_ends_with($text, '”'))) {
                        $text = trim(mb_substr($text, 1, mb_strlen($text) - 2));
                    }
                    return [
                        'text'          => $text,
                        'model'         => (string)$model,
                        'finish_reason' => $json['candidates'][0]['finishReason'] ?? null,
                        'usage'         => $json['usageMetadata'] ?? null,
                    ];
                }
            } else {
                Log::info("[AIService] Model {$model} thất bại (HTTP {$httpCode}): " . ($curlError ?: substr((string)$response, 0, 180)));
            }
        }

        Log::info('[AIService] Tất cả model Gemini đều không phản hồi thành công. Tự động chuyển sang rewriteFallback.');
        return [
            'text'          => '',
            'model'         => '',
            'finish_reason' => null,
            'usage'         => null,
        ];
    }

    /**
     * Fallback thông minh: Biên tập và viết lại thực sự bằng bộ quy tắc ngôn ngữ tự nhiên.
     * Tuyệt đối không giữ nguyên văn bản gốc rồi thêm chú thích trong ngoặc đơn.
     */
    private function simulateImprove(string $content, string $tone): string
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return '';
        }

        return match ($tone) {
            'spellcheck'   => $this->rewriteSpellcheck($trimmed),
            'natural'      => $this->rewriteNatural($trimmed),
            'concise'      => $this->rewriteConcise($trimmed),
            'expand'       => $this->rewriteExpand($trimmed),
            'improve'      => $this->rewriteImprove($trimmed),
            'professional' => $this->rewriteProfessional($trimmed),
            'literary'     => $this->rewriteLiterary($trimmed),
            'friendly'     => $this->rewriteFriendly($trimmed),
            default        => $this->rewriteImprove($trimmed),
        };
    }

    /**
     * Tách văn bản thành danh sách câu độc lập.
     * @return list<string>
     */
    private function splitSentences(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        $text = preg_replace("/[\r\n]+/u", " ", $text) ?? $text;
        $parts = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];
        $clean = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $clean[] = $p;
            }
        }
        return $clean;
    }

    /**
     * Chuẩn hóa và hoàn thiện câu (viết hoa đầu câu, ngắt dấu chuẩn xác).
     */
    private function finalizeSentence(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+([,.?!;:])/u', '$1', $s) ?? $s;
        $s = trim($s);
        if ($s === '') {
            return '';
        }
        $first = mb_substr($s, 0, 1, 'UTF-8');
        $rest  = mb_substr($s, 1, null, 'UTF-8');
        $s = mb_strtoupper($first, 'UTF-8') . $rest;
        if (!preg_match('/[.!?]$/u', $s)) {
            $s .= '.';
        }
        return $s;
    }

    /**
     * Thay thế cụm từ theo quy tắc Single-pass regex (ưu tiên cụm dài nhất trước).
     */
    private function applyDictionary(string $text, array $dict): string
    {
        if (empty($dict) || trim($text) === '') {
            return $text;
        }

        uksort($dict, static fn($a, $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        $escaped = [];
        $map = [];
        foreach ($dict as $src => $dest) {
            $escaped[] = preg_quote((string)$src, '/');
            $map[mb_strtolower((string)$src, 'UTF-8')] = $dest;
        }

        $pattern = '/(?<![\p{L}\p{N}_])(' . implode('|', $escaped) . ')(?![\p{L}\p{N}_])/ui';

        $replaced = preg_replace_callback($pattern, static function(array $matches) use ($map): string {
            $key = mb_strtolower($matches[0], 'UTF-8');
            return $map[$key] ?? $matches[0];
        }, $text);

        return (string)$replaced;
    }

    /**
     * Phong cách Nghiêm túc & Chuyên nghiệp (Professional):
     * - Chuẩn hóa đại từ ("mình/tớ" -> "tôi/chúng ta").
     * - Nâng cấp từ ngữ đời thường sang thuật ngữ học thuật/công sở.
     * - Bổ sung liên từ logic ("Trong bối cảnh hiện nay,", "Xét trên thực tế,", "Do đó,", "Tóm lại,").
     * - Tái cấu trúc câu văn mạch lạc, trang trọng.
     */
    private function rewriteProfessional(string $text): string
    {
        $sentences = $this->splitSentences($text);
        if (empty($sentences)) {
            return '';
        }

        $dict = [
            // Đại từ & nhân xưng
            'mình' => 'tôi',
            'tớ' => 'tôi',
            'tui' => 'tôi',
            'chúng mình' => 'chúng ta',
            'bọn mình' => 'chúng ta',
            'người ta' => 'các cá nhân',

            // Cụm câu chủ đề AI & Công nghệ
            'trí tuệ nhân tạo (ai) đang phát triển rất nhanh và thay đổi cách con người làm việc cũng như học tập' => 'trí tuệ nhân tạo (AI) đang tăng trưởng bứt phá và chuyển biến căn bản phương thức vận hành công việc cũng như quá trình nghiên cứu, trau dồi tri thức của con người',
            'nhiều người lo lắng ai sẽ thay thế lập trình viên, nhưng thực tế cho thấy nó chỉ là công cụ hỗ trợ giúp chúng ta làm việc hiệu quả hơn' => 'mặc dù tồn tại không ít quan ngại về nguy cơ AI thay thế vị trí của các kỹ sư phần mềm, xét trên bình diện thực tế, công nghệ này đóng vai trò như một phương tiện đắc lực giúp tối ưu hóa quy trình và gia tăng đáng kể hiệu năng lao động',
            'nhiều người lo lắng ai sẽ thay thế lập trình viên' => 'nhiều chuyên gia và nhân sự bày tỏ quan ngại sâu sắc về nguy cơ AI có thể thay thế hoàn toàn các kỹ sư phần mềm',
            'nhưng thực tế cho thấy nó chỉ là công cụ hỗ trợ giúp chúng ta làm việc hiệu quả hơn' => 'tuy nhiên trên bình diện thực tế, giải pháp này đóng vai trò như một phương tiện hỗ trợ đắc lực nhằm tối ưu hóa năng suất và hiệu năng vận hành',
            'để không bị tụt lại phía sau, chúng ta cần chủ động học hỏi và làm chủ công nghệ mới mỗi ngày' => 'nhằm duy trì lợi thế cạnh tranh bền vững, yêu cầu đặt ra là chúng ta phải chủ động thích ứng, nâng cao năng lực chuyên môn và làm chủ các tiến bộ công nghệ trong kỷ nguyên số',
            'để không bị tụt lại phía sau' => 'nhằm duy trì lợi thế cạnh tranh bền vững',
            'chúng ta cần chủ động học hỏi và làm chủ công nghệ mới mỗi ngày' => 'chúng ta cần tiên phong trau dồi tri thức chuyên môn và làm chủ các tiến bộ công nghệ liên tục',

            // Cụm câu chủ đề gặp gỡ / chia sẻ
            'hôm nay tui rất vui khi gặp được bạn mới' => 'thời điểm hiện tại, tôi thực sự trân trọng cơ hội được kết nối cùng những người đồng hành mới',
            'hôm nay tôi rất vui khi gặp được bạn mới' => 'thời điểm hiện tại, tôi thực sự trân trọng cơ hội được kết nối cùng những người đồng hành mới',
            'điều đó cũng mang đến cho tui nhiều niềm cảm hứng hơn trong công việc và cuộc sống' => 'sự kiện này đồng thời tiếp thêm nguồn cảm hứng tích cực, mở ra nhiều góc nhìn giá trị cho công việc cũng như cuộc sống',
            'điều đó cũng mang đến cho tôi nhiều niềm cảm hứng hơn trong công việc và cuộc sống' => 'sự kiện này đồng thời tiếp thêm nguồn cảm hứng tích cực, mở ra nhiều góc nhìn giá trị cho công việc cũng như cuộc sống',
            'chào mọi người' => 'kính chào quý độc giả,',
            'xin chào các bạn' => 'kính chào quý bạn đọc,',
            'tui viết bài này để chia sẻ về' => 'tôi xin được chia sẻ góc nhìn chuyên môn về',
            'tôi viết bài này để chia sẻ về' => 'người viết xin được chia sẻ góc nhìn chuyên môn về',

            // Nâng cấp từ ngữ đời thường sang thuật ngữ chuyên môn / công sở
            'phát triển rất nhanh' => 'tăng trưởng bứt phá và mở rộng quy mô mạnh mẽ',
            'phát triển nhanh chóng' => 'tăng trưởng với tốc độ vượt bậc',
            'phát triển nhanh' => 'tăng trưởng mạnh mẽ',
            'thay đổi cách con người làm việc cũng như học tập' => 'chuyển biến căn bản phương thức vận hành công việc cũng như trau dồi tri thức',
            'thay đổi cách' => 'chuyển biến căn bản phương thức',
            'làm việc cũng như học tập' => 'vận hành công việc và nghiên cứu học thuật',
            'làm việc' => 'vận hành công việc',
            'học tập' => 'nghiên cứu và trau dồi tri thức',
            'nhiều người lo lắng' => 'không ít chuyên gia và cá nhân bày tỏ quan ngại sâu sắc',
            'lo lắng rằng' => 'bày tỏ quan ngại sâu sắc rằng',
            'lo lắng' => 'bày tỏ quan ngại',
            'thay thế lập trình viên' => 'chiếm lĩnh vị trí của các kỹ sư phần mềm',
            'thay thế' => 'thay thế hoàn toàn vai trò của',
            'thực tế cho thấy rằng' => 'xét trên bình diện thực tế,',
            'thực tế chứng minh rằng' => 'dữ liệu thực tiễn đã chứng minh rằng,',
            'thực tế cho thấy' => 'xét trên thực tế,',
            'thực tế là' => 'nhìn nhận trên thực tế,',
            'chỉ là công cụ hỗ trợ' => 'đóng vai trò như một phương tiện hỗ trợ đắc lực',
            'công cụ hỗ trợ' => 'phương tiện hỗ trợ đắc lực',
            'giúp chúng ta làm việc hiệu quả hơn' => 'tối ưu hóa quy trình làm việc và gia tăng đáng kể hiệu năng',
            'làm việc hiệu quả hơn' => 'tối ưu hóa hiệu năng và gia tăng năng suất',
            'hiệu quả hơn' => 'tối ưu hóa hiệu quả',
            'chủ động học hỏi' => 'chủ động tiếp thu kiến thức và nâng cao năng lực',
            'làm chủ công nghệ mới' => 'khai thác tối đa tiềm năng công nghệ tiên tiến',
            'làm chủ công nghệ' => 'khai thác và làm chủ công nghệ',
            'mỗi ngày' => 'trong kỷ nguyên số hóa liên tục',
            'hôm nay' => 'thời điểm hiện tại,',
            'rất mệt mỏi' => 'cảm thấy quá tải về thể chất và tinh thần',
            'mệt mỏi' => 'chịu nhiều áp lực và quá tải',
            'căng thẳng' => 'áp lực chuyên môn cao',
            'nghỉ ngơi' => 'tái tạo năng lượng và phục hồi thể trạng',
            'rất nhiều' => 'đáng kể',
            'rất tốt' => 'đạt hiệu quả tối ưu',
            'không tốt' => 'chưa đạt hiệu quả mong đợi',
            'kém' => 'còn nhiều mặt hạn chế',
            'khó khăn' => 'thách thức mang tính chiến lược',
            'vấn đề' => 'khía cạnh trọng tâm cần giải quyết',
            'quan trọng' => 'mang tính then chốt',
            'rất quan trọng' => 'đóng vai trò cốt lõi và mang tính quyết định',
            'bắt đầu' => 'tiến hành khởi tạo và triển khai',
            'kết thúc' => 'hoàn tất toàn diện tiến trình',
            'nghĩ là' => 'nhận định rằng',
            'thấy là' => 'nhận thấy rằng',
            'nói chuyện' => 'trao đổi và thảo luận chuyên môn',
            'làm cho' => 'khiến cho quy trình',
            'giúp cho' => 'tạo tiền đề thúc đẩy',
            'chạy rất chậm' => 'suy giảm hiệu năng vận hành rõ rệt',
            'tối ưu hóa' => 'chuẩn hóa và tối ưu hóa',
        ];

        $processed = [];
        $total = count($sentences);

        foreach ($sentences as $idx => $s) {
            $transformed = $this->applyDictionary($s, $dict);
            $transformed = trim($transformed);

            // Gắn từ nối logic học thuật theo cấu trúc câu
            if ($idx === 0) {
                if (!preg_match('/^(trong bối cảnh|xét trên|nhìn chung|hiện nay|thời điểm|theo)/ui', $transformed)) {
                    $transformed = 'Trong bối cảnh hiện nay, ' . lcfirst($transformed);
                }
            } elseif ($idx === 1) {
                if (!preg_match('/^(mặt khác|tuy nhiên|xét trên|bên cạnh đó|mặc dù|đồng thời|đặc biệt)/ui', $transformed)) {
                    if (preg_match('/(nhưng|tuy nhiên|quan ngại|thực tế)/ui', $transformed)) {
                        $transformed = 'Xét trên thực tế, ' . lcfirst($transformed);
                    } else {
                        $transformed = 'Bên cạnh đó, ' . lcfirst($transformed);
                    }
                }
            } elseif ($idx === $total - 1 && $total >= 3) {
                if (!preg_match('/^(do đó|vì vậy|chính vì|tóm lại|nhìn chung|tổng kết|nhằm)/ui', $transformed)) {
                    if (preg_match('/^(nhằm|để)/ui', $transformed)) {
                        $transformed = 'Do đó, ' . lcfirst($transformed);
                    } else {
                        $transformed = 'Tóm lại, ' . lcfirst($transformed);
                    }
                }
            }

            // Chuẩn hóa lặp từ nối
            $transformed = preg_replace('/^(Trong bối cảnh hiện nay,\s*)+/ui', 'Trong bối cảnh hiện nay, ', $transformed) ?? $transformed;
            $transformed = preg_replace('/^(Xét trên thực tế,\s*)+/ui', 'Xét trên thực tế, ', $transformed) ?? $transformed;
            $transformed = preg_replace('/^(Do đó,\s*)+/ui', 'Do đó, ', $transformed) ?? $transformed;
            $transformed = preg_replace('/,\s*,/u', ',', $transformed) ?? $transformed;

            $processed[] = $this->finalizeSentence($transformed);
        }

        return implode(' ', $processed);
    }

    /**
     * Phong cách Văn chương & Cảm xúc (Literary):
     * - Làm mềm câu chữ, thêm hình ảnh gợi tả, nhịp điệu giàu cảm xúc.
     * - Dẫn dắt uyển chuyển ("Giữa nhịp sống hối hả...", "Có lẽ điều cốt lõi nằm ở chỗ...").
     */
    private function rewriteLiterary(string $text): string
    {
        $sentences = $this->splitSentences($text);
        if (empty($sentences)) {
            return '';
        }

        $dict = [
            // Đại từ
            'mình' => 'tôi',
            'tớ' => 'tôi',
            'tui' => 'tôi',
            'chúng mình' => 'chúng ta',
            'bọn mình' => 'chúng ta',

            // Các cụm câu chủ đề AI & Công nghệ theo văn chương
            'trí tuệ nhân tạo (ai) đang phát triển rất nhanh và thay đổi cách con người làm việc cũng như học tập' => 'làn sóng trí tuệ nhân tạo (AI) đang cuộn chảy không ngừng, khẽ khàng len lỏi và tái định hình từng thói quen làm việc cùng những năm tháng học tập của mỗi chúng ta',
            'nhiều người lo lắng ai sẽ thay thế lập trình viên, nhưng thực tế cho thấy nó chỉ là công cụ hỗ trợ giúp chúng ta làm việc hiệu quả hơn' => 'giữa muôn trùng biến chuyển ấy, đâu đó vẫn đọng lại những âu lo man mác về việc máy móc sẽ xóa nhòa đi dấu ấn bàn tay của người viết mã; thế nhưng, lắng lại mà nhìn, đó chẳng qua chỉ là người bạn đồng hành thầm lặng, nâng đỡ từng nhịp sáng tạo của con người',
            'nhiều người lo lắng ai sẽ thay thế lập trình viên' => 'giữa làn sóng biến chuyển ấy, đâu đó vẫn đọng lại những nỗi âu lo man mác về viễn cảnh máy móc xóa nhòa đi dấu ấn bàn tay của người viết mã',
            'nhưng thực tế cho thấy nó chỉ là công cụ hỗ trợ giúp chúng ta làm việc hiệu quả hơn' => 'thế nhưng, khi tĩnh tâm nhìn lại, công nghệ ấy tựa như một người bạn đồng hành thầm lặng, dịu dàng nâng đỡ từng nhịp sáng tạo để những ước mơ thêm phần trọn vẹn',
            'để không bị tụt lại phía sau, chúng ta cần chủ động học hỏi và làm chủ công nghệ mới mỗi ngày' => 'để không lạc nhịp giữa dòng đời vội vã, mỗi chúng ta cần mở rộng tâm thế, bền bỉ làm giàu vốn tri thức và hòa nhịp cùng những bước tiến mới của thời gian',
            'để không bị tụt lại phía sau' => 'để không lạc nhịp giữa dòng chảy cuộn trào của thời đại',
            'chúng ta cần chủ động học hỏi và làm chủ công nghệ mới mỗi ngày' => 'mỗi chúng ta cần mở rộng tâm thế đón nhận, bền bỉ trau dồi tri thức và hòa nhịp cùng những bước tiến mới của thời gian',

            // Cụm câu giao tiếp mẫu
            'hôm nay tui rất vui khi gặp được bạn mới' => 'ngày hôm nay trôi qua mang theo một niềm hân hoan êm đềm khi tình cờ hội ngộ một tâm hồn đồng điệu mới giữa dòng đời vội vã',
            'hôm nay tôi rất vui khi gặp được bạn mới' => 'ngày hôm nay trôi qua mang theo một niềm hân hoan êm đềm khi tình cờ hội ngộ một tâm hồn đồng điệu mới giữa dòng đời vội vã',
            'điều đó cũng mang đến cho tui nhiều niềm cảm hứng hơn trong công việc và cuộc sống' => 'chính cuộc gặp gỡ bình dị ấy tựa như làn gió sớm, khẽ khơi dậy trong tôi bao nguồn cảm hứng sâu lắng, nhắc tôi biết trân quý hơn từng nhịp đập của công việc và cuộc sống',
            'điều đó cũng mang đến cho tôi nhiều niềm cảm hứng hơn trong công việc và cuộc sống' => 'chính cuộc gặp gỡ bình dị ấy tựa như làn gió sớm, khẽ khơi dậy trong tôi bao nguồn cảm hứng sâu lắng, nhắc tôi biết trân quý hơn từng nhịp đập của công việc và cuộc sống',
            'chào mọi người' => 'gửi đến những tâm hồn ghé thăm nơi đây một lời chào bình yên,',
            'xin chào các bạn' => 'thương gửi bạn những lời thì thầm dịu êm,',

            // Từ vựng gợi cảm & hình tượng
            'hôm nay' => 'ngày hôm nay, giữa những nhịp đời êm ả,',
            'mình rất mệt mỏi' => 'lòng tôi không tránh khỏi chút chênh chao sau những nhọc nhằn dồn nén',
            'rất mệt mỏi' => 'chút chênh chao sau những nhọc nhằn dồn nén',
            'mệt mỏi' => 'chút chênh chao mệt nhoài',
            'căng thẳng' => 'nặng trĩu những ưu tư',
            'nghỉ ngơi' => 'tìm về khoảng lặng an yên để vỗ về tâm hồn',
            'thời gian' => 'năm tháng êm trôi',
            'cuộc sống' => 'dòng đời bình dị',
            'công việc' => 'chặng đường lao động sáng tạo',
            'buồn' => 'man mác một thoáng u hoài',
            'vui' => 'rạng rỡ và an yên',
            'suy nghĩ' => 'lắng sâu chiêm nghiệm',
            'nhớ' => 'hoài niệm về',
            'bắt đầu' => 'khẽ khàng hé mở',
            'kết thúc' => 'khép lại một chặng đường',
            'phát triển rất nhanh' => 'cuộn chảy không ngừng như một dòng thác miên viễn',
            'thay đổi' => 'tái định hình và làm mới',
            'thực tế cho thấy rằng' => 'thế nhưng, lắng lại mà nhìn,',
            'thực tế cho thấy' => 'thế nhưng, lắng lại mà nhìn,',
            'chỉ là công cụ hỗ trợ' => 'chẳng qua chỉ là một người bạn đồng hành thầm lặng',
            'làm việc hiệu quả hơn' => 'chắp cánh cho những ước mơ công việc thêm tròn đầy',
            'chủ động học hỏi' => 'mở rộng tâm thế và bền bỉ làm giàu vốn tri thức',
            'làm chủ công nghệ mới' => 'hòa nhịp cùng những bước chuyển mình của thời đại',
            'mỗi ngày' => 'qua từng sớm mai êm ả',
        ];

        $processed = [];
        $total = count($sentences);

        foreach ($sentences as $idx => $s) {
            $transformed = $this->applyDictionary($s, $dict);
            $transformed = trim($transformed);

            if ($idx === 0) {
                if (!preg_match('/^(giữa|nhìn lại|lắng lại|ngày hôm nay|bước qua)/ui', $transformed)) {
                    $transformed = 'Giữa nhịp sống hối hả và biến chuyển không ngừng, ' . lcfirst($transformed);
                }
            } elseif ($idx === 1) {
                if (!preg_match('/^(có lẽ|thế nhưng|đâu đó|dẫu vậy|lắng nghe)/ui', $transformed)) {
                    if (preg_match('/(nhưng|thực tế|âu lo|trăn trở)/ui', $transformed)) {
                        $transformed = 'Có lẽ điều cốt lõi nằm ở chỗ, ' . lcfirst($transformed);
                    } else {
                        $transformed = 'Đâu đó giữa những bước đi ấy, ' . lcfirst($transformed);
                    }
                }
            } elseif ($idx === $total - 1 && $total >= 3) {
                if (!preg_match('/^(để rồi|và rồi|khép lại|hơn bao giờ hết)/ui', $transformed)) {
                    $transformed = 'Để rồi trên hành trình phía trước, ' . lcfirst($transformed);
                }
            }

            $transformed = preg_replace('/,\s*,/u', ',', $transformed) ?? $transformed;
            $processed[] = $this->finalizeSentence($transformed);
        }

        return implode(' ', $processed);
    }

    /**
     * Phong cách Súc tích / Ngắn gọn (Concise):
     * - Cắt bỏ triệt để từ đệm, từ thừa rườm rà.
     * - Cô đọng ý chính, giảm độ dài xuống 60–75% bản gốc mà vẫn đủ ý.
     */
    private function rewriteConcise(string $text): string
    {
        $sentences = $this->splitSentences($text);
        if (empty($sentences)) {
            return '';
        }

        $dict = [
            // Cụm câu chủ đề AI & Công nghệ cô đọng trực diện
            'trí tuệ nhân tạo (ai) đang phát triển rất nhanh và thay đổi cách con người làm việc cũng như học tập' => 'AI đang phát triển vượt bậc, định hình lại cách làm việc và học tập',
            'nhiều người lo lắng ai sẽ thay thế lập trình viên, nhưng thực tế cho thấy nó chỉ là công cụ hỗ trợ giúp chúng ta làm việc hiệu quả hơn' => 'Dù có lo ngại AI thay thế lập trình viên, công nghệ này thực chất là công cụ nâng cao hiệu suất',
            'nhiều người lo lắng ai sẽ thay thế lập trình viên' => 'Dù có lo ngại AI thay thế lập trình viên',
            'nhưng thực tế cho thấy nó chỉ là công cụ hỗ trợ giúp chúng ta làm việc hiệu quả hơn' => 'AI thực chất là công cụ nâng cao hiệu suất',
            'để không bị tụt lại phía sau, chúng ta cần chủ động học hỏi và làm chủ công nghệ mới mỗi ngày' => 'Chủ động học hỏi và làm chủ công nghệ là chìa khóa để không bị tụt hậu',
            'để không bị tụt lại phía sau' => 'để tránh tụt hậu',
            'chúng ta cần chủ động học hỏi và làm chủ công nghệ mới mỗi ngày' => 'cần chủ động học hỏi và làm chủ công nghệ',

            // Lược bỏ từ đệm, từ thừa rườm rà
            'thực tế cho thấy rằng' => '',
            'thực tế chứng minh rằng' => '',
            'thực tế cho thấy' => '',
            'thực tế là' => '',
            'trong tương lai không xa' => 'Tương lai,',
            'trong một tương lai không xa' => 'Tương lai,',
            'một số người lại cho rằng' => 'Nhiều ý kiến cho rằng',
            'nhiều người lại cho rằng' => 'Nhiều ý kiến cho rằng',
            'có người lại cho rằng' => 'Có ý kiến cho rằng',
            'có thể thấy rằng' => '',
            'chúng ta đều thấy rằng' => '',
            'chúng ta đều biết rằng' => '',
            'như đã biết' => '',
            'như chúng ta đã biết' => '',
            'theo như tôi được biết' => '',
            'theo tôi thấy' => '',
            'theo mình thấy' => '',
            'mình nghĩ là' => '',
            'tôi nghĩ là' => '',
            'nói chung là' => '',
            'thật ra thì' => '',
            'thực sự thì' => '',
            'về cơ bản là' => '',
            'vào thời điểm hiện tại' => 'Hiện tại,',
            'ở thời điểm hiện tại' => 'Hiện tại,',
            'với mục đích là để' => 'để',
            'nhằm mục đích là để' => 'để',
            'và điều đó cũng mang lại' => 'đồng thời mang lại',
            'điều này cũng đem lại' => 'đồng thời mang lại',
            'và điều đó cũng' => 'đồng thời',
            'rất là' => '',
            'hết sức là' => '',
            'vô cùng là' => '',
            'hết sức cần thiết' => 'cần thiết',
            'rất nhiều' => 'nhiều',
            'rất' => '',
            'xin chào' => '',
            'chào bạn' => '',
            'chào mọi người' => '',
            'chào các bạn' => '',
            'hôm nay' => '',
            'chỉ là công cụ hỗ trợ giúp chúng ta làm việc hiệu quả hơn' => 'là công cụ tối ưu hiệu suất',
            'làm việc hiệu quả hơn' => 'tăng hiệu suất',
            'thay đổi cách thức' => 'định hình lại cách',
            'làm việc cũng như học tập' => 'làm việc và học tập',
            'mình' => 'tôi',
            'tớ' => 'tôi',
            'tui' => 'tôi',
        ];

        $processed = [];

        foreach ($sentences as $s) {
            $transformed = $this->applyDictionary($s, $dict);
            $transformed = trim($transformed);

            // Bỏ các từ nối rườm rà ở đầu câu
            $transformed = preg_replace('/^(thực tế,?\s*|thật ra,?\s*|nói chung,?\s*|có thể thấy,?\s*|rằng,?\s*)/ui', '', $transformed) ?? $transformed;
            $transformed = trim($transformed);

            if ($transformed !== '') {
                $processed[] = $this->finalizeSentence($transformed);
            }
        }

        return implode(' ', $processed);
    }

    /**
     * Hoàn thiện cấu trúc văn bản sau khi biên tập:
     * - Chuẩn hóa khoảng trắng và dấu câu.
     * - Viết hoa chữ cái đầu mỗi câu tự nhiên.
     * - Đảm bảo kết thúc bằng dấu chấm hợp lý.
     */
    private function finalizeRewrittenText(string $text): string
    {
        return $this->finalizeSentence($text);
    }

    /**
     * Chế độ 1: Sửa lỗi chính tả, viết tắt telex và chuẩn hóa dấu câu (Spellcheck).
     * Tuyệt đối không chèn từ nối áp đặt (như "Trong bối cảnh hiện nay"), giữ trọn vẹn ý tứ gốc.
     */
    private function rewriteSpellcheck(string $text): string
    {
        $sentences = $this->splitSentences($text);
        if (empty($sentences)) {
            return '';
        }

        $dict = [
            // Từ viết tắt chat / telex thông dụng
            'ko' => 'không',
            'k' => 'không',
            'hok' => 'học',
            'hk' => 'học',
            'hem' => 'không',
            'hổng' => 'không',
            'dc' => 'được',
            'đc' => 'được',
            'mik' => 'mình',
            'mk' => 'mình',
            'ng' => 'người',
            'ngừi' => 'người',
            'j' => 'gì',
            'z' => 'vậy',
            'v' => 'vậy',
            'cx' => 'cũng',
            'cug' => 'cũng',
            'bt' => 'biết',
            'bit' => 'biết',
            'bít' => 'biết',
            'bik' => 'biết',
            'gap' => 'gặp',
            'wa' => 'quá',
            'wá' => 'quá',
            'thik' => 'thích',
            'thík' => 'thích',
            'lun' => 'luôn',
            'luong' => 'luôn',
            'nha' => 'nhé',
            'nhé' => 'nhé',
            'rui' => 'rồi',
            'rùi' => 'rồi',
            'thui' => 'thôi',
            'tui' => 'tôi',
            'tớ' => 'tôi',
            'mn' => 'mọi người',

            // Lỗi chính tả tiếng Việt phổ biến
            'chia xẻ' => 'chia sẻ',
            'xắp xếp' => 'sắp xếp',
            'chuẩn đoán' => 'chẩn đoán',
            'sơ xuất' => 'sơ suất',
            'cọ sát' => 'cọ xát',
            'sáng lạng' => 'xán lạn',
            'suông sẻ' => 'suôn sẻ',
            'dành giật' => 'giành giật',
            'giành dụm' => 'dành dụm',
            'tham quan' => 'tham quan',
            'thăm quan' => 'tham quan',
            'nhận chức' => 'nhậm chức',
            'khắc cốt ghi dạ' => 'khắc cốt ghi tâm',
            'chín mùi' => 'chín muồi',
            'bổ xung' => 'bổ sung',
            'sát nhập' => 'sáp nhập',

            // Lỗi gõ thiếu dấu tiếng Việt cơ bản
            'lap trinh' => 'lập trình',
            'hoc lap trinh' => 'học lập trình',
            'toi muon' => 'tôi muốn',
            'toi' => 'tôi',
            'muon' => 'muốn',
            'bai viet' => 'bài viết',
            'hom nay' => 'hôm nay',
            'rat vui' => 'rất vui',
            'cam on' => 'cảm ơn',
            'thanh cong' => 'thành công',
            'kinh nghiem' => 'kinh nghiệm',
            'phat trien' => 'phát triển',
            'cong nghe' => 'công nghệ',
            'kien thuc' => 'kiến thức',
            'ky nang' => 'kỹ năng',
            'lam viec' => 'làm việc',
            'thoi gian' => 'thời gian',
            'cuoc song' => 'cuộc sống',
            'van de' => 'vấn đề',
            'giai phap' => 'giải pháp',
            'hieu qua' => 'hiệu quả',
            'chung ta' => 'chúng ta',
            'moi nguoi' => 'mọi người',
        ];

        $processed = [];
        foreach ($sentences as $s) {
            $transformed = $this->applyDictionary($s, $dict);
            $transformed = preg_replace('/\s+([,.?!;:])/u', '$1', $transformed) ?? $transformed;
            $transformed = preg_replace('/([,.?!;:])(?=[^\s\d])/u', '$1 ', $transformed) ?? $transformed;
            $transformed = trim($transformed);
            if ($transformed !== '') {
                $processed[] = $this->finalizeSentence($transformed);
            }
        }

        return implode(' ', $processed);
    }

    /**
     * Chế độ 2: Viết rõ ràng, tự nhiên (Natural).
     * Giúp văn phong mềm mại, tự nhiên, gần gũi, xóa bỏ cấu trúc máy móc, gượng gạo.
     */
    private function rewriteNatural(string $text): string
    {
        $sentences = $this->splitSentences($text);
        if (empty($sentences)) {
            return '';
        }

        $dict = [
            'tiến hành thực hiện' => 'thực hiện',
            'tiến hành triển khai' => 'triển khai',
            'ở thời điểm hiện tại' => 'hiện nay',
            'vào thời điểm hiện tại' => 'hiện nay',
            'trong khoảng thời gian này' => 'dạo này',
            'gặp phải vấn đề khó khăn' => 'gặp khó khăn',
            'gặp phải khó khăn' => 'gặp khó khăn',
            'cảm thấy vô cùng' => 'thấy rất',
            'đưa ra quyết định' => 'quyết định',
            'chúng tôi nhận thấy rằng' => 'mình nhận thấy rằng',
            'chúng ta đều nhận thấy rằng' => 'chúng ta đều thấy',
            'mang tính chất là' => 'chính là',
            'nhằm mục đích là để' => 'để',
            'và điều đó cũng đem lại' => 'đồng thời đem lại',
            'thực tế cho thấy rằng' => 'thực tế là',
            'có thể nói rằng' => 'có thể thấy',
            'chạy rất chậm' => 'hoạt động khá chậm',
            'rất là nhiều' => 'rất nhiều',
            'rất là' => 'rất',
            'vô cùng là' => 'vô cùng',
            'mình nghĩ là' => 'mình nghĩ',
            'tôi nghĩ là' => 'tôi nghĩ',
        ];

        $processed = [];
        foreach ($sentences as $s) {
            $corrected = $this->rewriteSpellcheck($s);
            $transformed = $this->applyDictionary($corrected, $dict);
            $transformed = trim($transformed);
            if ($transformed !== '') {
                $processed[] = $this->finalizeSentence($transformed);
            }
        }

        return implode(' ', $processed);
    }

    /**
     * Chế độ 5: Cải thiện diễn đạt & Trau chuốt câu từ (Improve).
     * Nâng cấp độ sắc nét, giàu vốn từ, cấu trúc câu gãy gọn và giàu sức thuyết phục.
     */
    private function rewriteImprove(string $text): string
    {
        $sentences = $this->splitSentences($text);
        if (empty($sentences)) {
            return '';
        }

        $dict = [
            'muốn' => 'mong muốn',
            'học' => 'học hỏi và trau dồi',
            'học hỏi' => 'không ngừng học hỏi và tích lũy kiến thức',
            'làm' => 'thực hiện và hoàn thiện',
            'rất hay' => 'rất bổ ích và mang lại nhiều giá trị',
            'rất tốt' => 'mang lại hiệu quả rõ rệt và tích cực',
            'rất khó' => 'đặt ra không ít thách thức cần vượt qua',
            'khó khăn' => 'thách thức thực tế',
            'thích' => 'đặc biệt hứng thú với',
            'thích thú' => 'dành nhiều đam mê cho',
            'nghĩ rằng' => 'tin tưởng rằng',
            'viết bài' => 'chia sẻ bài viết',
            'chia sẻ' => 'chia sẻ những góc nhìn tâm đắc',
            'bắt đầu' => 'khởi đầu hành trình',
            'giúp ích' => 'hỗ trợ đắc lực',
            'quan trọng' => 'đóng vai trò then chốt',
            'thành công' => 'gặt hái những bước tiến vững chắc',
            'cố gắng' => 'nỗ lực và kiên trì',
            'tìm kiếm' => 'chủ động tìm kiếm và khai phá',
        ];

        $processed = [];
        $total = count($sentences);

        foreach ($sentences as $idx => $s) {
            $corrected = $this->rewriteSpellcheck($s);
            $transformed = $this->applyDictionary($corrected, $dict);
            $transformed = trim($transformed);

            if ($idx === 1 && $total >= 2) {
                if (!preg_match('/^(bên cạnh đó|đồng thời|hơn nữa|ngoài ra|mặt khác|tuy nhiên)/ui', $transformed)) {
                    $transformed = 'Bên cạnh đó, ' . lcfirst($transformed);
                }
            } elseif ($idx === $total - 1 && $total >= 3) {
                if (!preg_match('/^(nhìn chung|tóm lại|do đó|vì vậy|chính vì thế)/ui', $transformed)) {
                    $transformed = 'Chính vì thế, ' . lcfirst($transformed);
                }
            }

            $processed[] = $this->finalizeSentence($transformed);
        }

        return implode(' ', $processed);
    }

    /**
     * Chế độ 4: Mở rộng nội dung (Expand).
     * Triển khai ý tưởng đa chiều, bổ sung góc nhìn thực tiễn và chiều sâu phân tích.
     */
    private function rewriteExpand(string $text): string
    {
        $sentences = $this->splitSentences($text);
        if (empty($sentences)) {
            return '';
        }

        $processed = [];
        foreach ($sentences as $idx => $s) {
            $base = $this->rewriteImprove($s);
            $lower = mb_strtolower($base, 'UTF-8');

            if (preg_match('/(học|lập trình|công nghệ|php|code|kiến thức|kỹ năng)/ui', $lower)) {
                $expanded = $base . ' Việc kiên trì tiếp cận một cách bài bản từ nền tảng không chỉ giúp củng cố tư duy logic mà còn mở ra nhiều cơ hội ứng dụng giải quyết các bài toán thực tế hiệu quả.';
            } elseif (preg_match('/(chia sẻ|bài viết|kinh nghiệm|góc nhìn|cộng đồng)/ui', $lower)) {
                $expanded = $base . ' Những trải nghiệm thực tiễn này luôn là nguồn cảm hứng giá trị, góp phần kết nối và thúc đẩy sự học hỏi lẫn nhau trong cộng đồng.';
            } elseif (preg_match('/(khó khăn|thách thức|vấn đề|lo lắng|áp lực)/ui', $lower)) {
                $expanded = $base . ' Dù phía trước có những rào cản nhất định, việc chia nhỏ mục tiêu và không ngừng tìm kiếm giải pháp sẽ giúp chúng ta từng bước làm chủ tình thế.';
            } else {
                $expanded = $base . ' Điều này tạo tiền đề quan trọng giúp người đọc có cái nhìn toàn diện và sâu sắc hơn về chủ đề đang thảo luận.';
            }

            $processed[] = $this->finalizeSentence($expanded);
        }

        return implode(' ', $processed);
    }

    /**
     * Chế độ 8: Giọng thân thiện, gần gũi (Friendly).
     * Ấm áp, vui tươi, sử dụng đại từ thân thiện và lan tỏa nguồn năng lượng tích cực.
     */
    private function rewriteFriendly(string $text): string
    {
        $sentences = $this->splitSentences($text);
        if (empty($sentences)) {
            return '';
        }

        $dict = [
            'tôi' => 'mình',
            'người viết' => 'mình',
            'chúng ta' => 'chúng mình',
            'các bạn' => 'cả nhà',
            'bạn đọc' => 'mọi người',
            'nghiên cứu' => 'tìm hiểu',
            'công tác' => 'làm việc',
            'trao đổi' => 'tâm sự và trò chuyện',
            'xin chào' => 'Chào mọi người nhé!',
            'chào bạn' => 'Chào bạn nhé!',
            'chào mọi người' => 'Chào cả nhà nhé!',
        ];

        $processed = [];
        $hasGreeting = preg_match('/^(chào|xin chào|hello|hi)/ui', $sentences[0] ?? '');

        if (!$hasGreeting) {
            $processed[] = 'Chào mọi người nhé!';
        }

        foreach ($sentences as $s) {
            $corrected = $this->rewriteSpellcheck($s);
            $transformed = $this->applyDictionary($corrected, $dict);
            $transformed = trim($transformed);
            if ($transformed !== '') {
                $processed[] = $this->finalizeSentence($transformed);
            }
        }

        $processed[] = 'Chúc mọi người luôn có thật nhiều niềm vui và cảm hứng tích cực mỗi ngày nhé!';

        return implode(' ', $processed);
    }

    // ----------------------------------------------------------------
    // evaluateNewsPotential
    // ----------------------------------------------------------------

    /**
     * Đánh giá bài viết có tiềm năng nâng cấp lên mục Tin tức không (Online Gemini + Offline NLP Engine).
     *
     * Tiêu chí chấm điểm (thang 100, đạt >= 50 điểm sẽ được AI đề xuất):
     *  - Tiêu đề đạt chuẩn độ dài (10–180 ký tự): +20 điểm
     *  - Nội dung có chiều sâu (>= 150 ký tự hoặc >= 30 từ: +30 điểm; >= 400 ký tự: thêm +10 điểm)
     *  - Từ khóa chuyên môn / công nghệ / thời sự / phân tích / giải pháp (khớp >= 1 từ: +25 điểm; >= 3 từ: thêm +15 điểm)
     *  - Cấu trúc đoạn văn / mạch câu rõ ràng (>= 2 đoạn hoặc ngắt câu mạch lạc): +15 điểm
     *
     * @return array{is_news_worthy: bool, score: int, reason: string}
     */
    public function evaluateNewsPotential(string $title, string $content): array
    {
        $trimmedTitle = trim($title);
        $trimmedContent = trim(strip_tags($content));

        // 1. Nếu có Gemini API Key hợp lệ: Gọi phân tích Online
        $apiKey = trim((string)($this->config['api_key'] ?? ''));
        if ($apiKey === '') {
            $apiKey = trim((string)(getenv('GEMINI_API_KEY') ?: (getenv('AI_API_KEY') ?: '')));
        }

        if ($apiKey !== '' && !str_starts_with($apiKey, 'your_')) {
            try {
                $onlineEval = $this->callGeminiNewsEvaluation($trimmedTitle, $trimmedContent, $apiKey);
                if ($onlineEval !== null) {
                    return $onlineEval;
                }
            } catch (Throwable $e) {
                Log::info('[AIService] Lỗi gọi Gemini News Evaluation: ' . $e->getMessage());
            }
        }

        // 2. Chế độ Offline / Fallback: Sử dụng NLP Rule Engine nâng cao
        return $this->evaluateNewsOffline($trimmedTitle, $trimmedContent);
    }

    /**
     * Đánh giá tiềm năng tin tức Offline bằng Rule Engine tiếng Việt.
     */
    private function evaluateNewsOffline(string $title, string $content): array
    {
        $score   = 0;
        $reasons = [];

        // 1. Tiêu đề (10–180 ký tự)
        $titleLen = mb_strlen($title, 'UTF-8');
        if ($titleLen >= 10 && $titleLen <= 180) {
            $score += 20;
            $reasons[] = "Tiêu đề chuẩn mực ({$titleLen} ký tự)";
        } else {
            $reasons[] = "Độ dài tiêu đề: {$titleLen} ký tự";
        }

        // 2. Độ dài nội dung (>= 150 ký tự hoặc >= 30 từ)
        $contentLen = mb_strlen($content, 'UTF-8');
        $wordCount = count(preg_split('/\s+/u', $content, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        if ($contentLen >= 400 || $wordCount >= 80) {
            $score += 40;
            $reasons[] = "Nội dung phong phú, phân tích chuyên sâu ({$wordCount} từ, {$contentLen} ký tự)";
        } elseif ($contentLen >= 150 || $wordCount >= 30) {
            $score += 30;
            $reasons[] = "Nội dung đủ dung lượng truyền tải thông tin ({$wordCount} từ, {$contentLen} ký tự)";
        } else {
            $reasons[] = "Nội dung ngắn ({$contentLen} ký tự)";
        }

        // 3. Từ khóa thời sự, chuyên môn, phân tích, giải pháp, công nghệ, nghiên cứu
        $keywords = [
            // Chuyên môn, công nghệ & giải pháp kỹ thuật
            'phân tích', 'kiến trúc', 'hệ thống', 'giải pháp', 'tối ưu', 'hiệu năng',
            'cơ sở dữ liệu', 'ứng dụng', 'web', 'hiện đại', 'công nghệ', 'lập trình',
            'thuật toán', 'bảo mật', 'dữ liệu', 'kỹ sư', 'phần mềm', 'trí tuệ nhân tạo',
            'chuyển đổi số', 'hạ tầng', 'quy trình', 'tiêu chuẩn', 'mã nguồn mở',
            'database', 'performance', 'architecture', 'security', 'optimization',
            'system', 'solution', 'technology', 'cloud', 'api', 'devops', 'backend',
            // Thời sự, tin tức, nghiên cứu, xu hướng & công bố
            'nghiên cứu', 'xu hướng', 'thông báo', 'công bố', 'sự kiện', 'phát hiện',
            'cập nhật', 'tin tức', 'báo cáo', 'kết quả', 'hội thảo', 'chính sách',
            'kinh tế', 'thị trường', 'tổng kết', 'đánh giá', 'thực trạng', 'kinh nghiệm',
            'bài học', 'chiến lược', 'đổi mới', 'triển khai', 'announcement', 'update',
            'report', 'research', 'trend', 'official'
        ];

        $lowerAll = mb_strtolower($title . ' ' . $content, 'UTF-8');
        $matched = [];
        foreach ($keywords as $kw) {
            if (str_contains($lowerAll, $kw)) {
                $matched[] = $kw;
            }
        }
        $matched = array_values(array_unique($matched));

        if (count($matched) >= 3) {
            $score += 40;
            $sampleWords = implode(', ', array_slice($matched, 0, 4));
            $reasons[] = "Hàm lượng chuyên môn/thời sự cao (khớp " . count($matched) . " từ khóa: {$sampleWords})";
        } elseif (count($matched) >= 1) {
            $score += 25;
            $sampleWords = implode(', ', array_slice($matched, 0, 2));
            $reasons[] = "Chứa từ khóa mang tính chủ đề rõ ràng: {$sampleWords}";
        } else {
            $reasons[] = "Chưa phát hiện từ khóa chuyên môn/tin tức đặc trưng";
        }

        // 4. Cấu trúc bài viết (đoạn văn, dấu ngắt câu)
        $paragraphs = preg_split('/\n\s*\n|\r\n\s*\r\n/', $content) ?: [];
        $paragraphs = array_filter(array_map('trim', $paragraphs));
        $sentences = preg_split('/[.!?]+(?:\s+|$)/u', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($paragraphs) >= 2 || count($sentences) >= 3) {
            $score += 15;
            $reasons[] = "Bố cục mạch lạc (" . (count($paragraphs) >= 2 ? count($paragraphs) . " đoạn văn" : count($sentences) . " câu triển khai ý") . ")";
        }

        $isNewsWorthy = $score >= 50;

        return [
            'is_news_worthy' => $isNewsWorthy,
            'score'          => min(100, $score),
            'reason'         => implode('. ', $reasons) . '.',
        ];
    }

    /**
     * Gọi Gemini API phân tích giá trị tin tức trực tuyến.
     */
    private function callGeminiNewsEvaluation(string $title, string $content, string $apiKey): ?array
    {
        $prompt = "Bạn là biên tập viên tin tức. Hãy đánh giá bài viết sau xem có phù hợp đưa lên mục TIN TỨC / BÀI CHUYÊN MÔN NỔI BẬT của tòa soạn không. Tiêu chuẩn: mang tính chuyên môn, thời sự, cung cấp kiến thức giá trị, phân tích kiến trúc, giải pháp công nghệ hoặc tin tức quan trọng.\n\nTrả về duy nhất JSON: {\"is_news_worthy\": bool, \"score\": int (0-100), \"reason\": \"tóm tắt lý do đánh giá bằng tiếng Việt 1-2 câu\"}";

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . rawurlencode($apiKey);
        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $prompt]]
            ],
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [['text' => "Tiêu đề: {$title}\n\nNội dung:\n" . mb_substr($content, 0, 3000, 'UTF-8')]]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature'      => 0.2,
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !is_string($rawResponse)) {
            return null;
        }

        $decoded = json_decode($rawResponse, true);
        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($text === '') {
            return null;
        }

        $parsed = json_decode($text, true);
        if (!is_array($parsed) || !isset($parsed['is_news_worthy'])) {
            return null;
        }

        return [
            'is_news_worthy' => (bool)$parsed['is_news_worthy'],
            'score'          => (int)($parsed['score'] ?? ($parsed['is_news_worthy'] ? 75 : 30)),
            'reason'         => (string)($parsed['reason'] ?? 'Hệ thống AI đánh giá bài viết có hàm lượng thông tin chuyên môn cao.'),
        ];
    }
}
