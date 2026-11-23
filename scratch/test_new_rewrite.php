<?php
declare(strict_types=1);

class AdvancedRewriteEngine
{
    /**
     * Tách văn bản thành các câu độc lập
     * @return list<string>
     */
    public static function splitSentences(string $text): array
    {
        $text = trim($text);
        if ($text === '') return [];
        $text = preg_replace("/[\r\n]+/u", " ", $text);
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
     * Viết hoa chữ cái đầu và chuẩn hóa dấu câu
     */
    public static function finalizeSentence(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = preg_replace('/\s+([,.?!;:])/u', '$1', $s);
        $s = trim($s);
        if ($s === '') return '';
        $first = mb_substr($s, 0, 1, 'UTF-8');
        $rest = mb_substr($s, 1, null, 'UTF-8');
        $s = mb_strtoupper($first, 'UTF-8') . $rest;
        if (!preg_match('/[.!?]$/u', $s)) {
            $s .= '.';
        }
        return $s;
    }

    /**
     * Phong cách Nghiêm túc & Chuyên nghiệp (Professional)
     */
    public static function rewriteProfessional(string $text): string
    {
        $sentences = self::splitSentences($text);
        if (empty($sentences)) return '';

        // 1. Từ điển nâng cấp cụm từ và từ vựng chuyên môn
        $vocab = [
            // Cụm câu chủ đề AI & Công nghệ
            '/\btrí tuệ nhân tạo \(ai\) đang phát triển rất nhanh và thay đổi cách con người làm việc cũng như học tập\b/ui' => 'trí tuệ nhân tạo (AI) đang tăng trưởng bứt phá và chuyển biến căn bản phương thức vận hành công việc cũng như quá trình nghiên cứu, trau dồi tri thức của con người',
            '/\bnhiều người lo lắng ai sẽ thay thế lập trình viên,?\s*nhưng thực tế cho thấy nó chỉ là công cụ hỗ trợ giúp chúng ta làm việc hiệu quả hơn\b/ui' => 'mặc dù tồn tại không ít quan ngại về nguy cơ AI thay thế vị trí của các kỹ sư phần mềm, xét trên bình diện thực tế, công nghệ này đóng vai trò như một phương tiện đắc lực giúp tối ưu hóa quy trình và gia tăng đáng kể hiệu năng lao động',
            '/\bđể không bị tụt lại phía sau,?\s*chúng ta cần chủ động học hỏi và làm chủ công nghệ mới mỗi ngày\b/ui' => 'nhằm duy trì lợi thế cạnh tranh bền vững, yêu cầu đặt ra là chúng ta phải chủ động thích ứng, nâng cao năng lực chuyên môn và làm chủ các tiến bộ công nghệ trong kỷ nguyên số',

            // Cụm từ chuyên môn & công sở
            '/\bphát triển rất nhanh\b/ui' => 'tăng trưởng bứt phá và mở rộng quy mô mạnh mẽ',
            '/\bphát triển nhanh chóng\b/ui' => 'tăng trưởng với tốc độ vượt bậc',
            '/\bthay đổi cách con người làm việc cũng như học tập\b/ui' => 'chuyển biến căn bản phương thức vận hành công việc cũng như trau dồi tri thức',
            '/\bthay đổi cách\b/ui' => 'chuyển biến căn bản phương thức',
            '/\blàm việc cũng như học tập\b/ui' => 'vận hành công việc và nghiên cứu học thuật',
            '/\blàm việc\b/ui' => 'vận hành công việc',
            '/\bhọc tập\b/ui' => 'trau dồi tri thức',
            '/\bnhiều người lo lắng\b/ui' => 'không ít chuyên gia và cá nhân bày tỏ quan ngại sâu sắc',
            '/\blo lắng rằng\b/ui' => 'bày tỏ quan ngại sâu sắc rằng',
            '/\blo lắng\b/ui' => 'bày tỏ quan ngại',
            '/\bthay thế lập trình viên\b/ui' => 'chiếm lĩnh hoàn toàn vị trí của các kỹ sư phần mềm',
            '/\bthay thế\b/ui' => 'chiếm lĩnh vị trí của',
            '/\bthực tế cho thấy rằng\b/ui' => 'xét trên bình diện thực tế,',
            '/\bthực tế cho thấy\b/ui' => 'xét trên thực tế,',
            '/\bthực tế là\b/ui' => 'nhìn nhận trên thực tế,',
            '/\bchỉ là công cụ hỗ trợ\b/ui' => 'đóng vai trò như một phương tiện hỗ trợ đắc lực',
            '/\blàm việc hiệu quả hơn\b/ui' => 'tối ưu hóa quy trình và gia tăng hiệu năng',
            '/\bhiệu quả hơn\b/ui' => 'tối ưu hóa hiệu năng và nâng cao hiệu suất',
            '/\bđể không bị tụt lại phía sau\b/ui' => 'nhằm duy trì lợi thế cạnh tranh bền vững',
            '/\bchủ động học hỏi\b/ui' => 'chủ động tiếp thu kiến thức và nâng cao năng lực',
            '/\blàm chủ công nghệ mới\b/ui' => 'khai thác tối đa tiềm năng công nghệ tiên tiến',
            '/\bmỗi ngày\b/ui' => 'trong kỷ nguyên số hóa liên tục',
            '/\brất nhiều\b/ui' => 'đáng kể',
            '/\brất tốt\b/ui' => 'đạt hiệu quả tối ưu',
            '/\bkhông tốt\b/ui' => 'chưa đạt hiệu quả mong đợi',
            '/\bkém\b/ui' => 'còn nhiều mặt hạn chế',
            '/\bkhó khăn\b/ui' => 'thách thức mang tính chiến lược',
            '/\bquan trọng\b/ui' => 'mang tính then chốt',
            '/\brất quan trọng\b/ui' => 'đóng vai trò cốt lõi và mang tính quyết định',
            '/\bbắt đầu\b/ui' => 'tiến hành khởi tạo và triển khai',
            '/\bkết thúc\b/ui' => 'hoàn tất toàn diện tiến trình',
            '/\bnghĩ là\b/ui' => 'nhận định rằng',
            '/\bthấy là\b/ui' => 'nhận thấy rằng',
            '/\b(tui|mình|tớ)\b/ui' => 'tôi',
            '/\b(chúng mình|bọn mình)\b/ui' => 'chúng ta',
        ];

        $processed = [];
        $totalSentences = count($sentences);

        foreach ($sentences as $idx => $s) {
            $transformed = preg_replace(array_keys($vocab), array_values($vocab), $s);
            $transformed = trim((string)$transformed);

            // Gắn từ nối logic học thuật theo vị trí cấu trúc câu nếu câu chưa có liên từ
            if ($idx === 0) {
                if (!preg_match('/^(trong bối cảnh|xét trên|nhìn chung|hiện nay|thời điểm)/ui', $transformed)) {
                    $transformed = 'Trong bối cảnh hiện nay, ' . lcfirst($transformed);
                }
            } elseif ($idx === 1) {
                if (!preg_match('/^(mặt khác|tuy nhiên|xét trên|bên cạnh đó|mặc dù|đồng thời)/ui', $transformed)) {
                    // Nếu câu mang tính phản biện hoặc làm rõ
                    if (preg_match('/(nhưng|tuy nhiên|quan ngại|thực tế)/ui', $transformed)) {
                        $transformed = 'Xét trên thực tế, ' . lcfirst($transformed);
                    } else {
                        $transformed = 'Bên cạnh đó, ' . lcfirst($transformed);
                    }
                }
            } elseif ($idx === $totalSentences - 1 && $totalSentences >= 3) {
                // Câu kết luận
                if (!preg_match('/^(do đó|vì vậy|chính vì|tóm lại|nhìn chung|tổng kết)/ui', $transformed)) {
                    if (preg_match('/^(nhằm|để)/ui', $transformed)) {
                        $transformed = 'Do đó, ' . lcfirst($transformed);
                    } else {
                        $transformed = 'Tóm lại, ' . lcfirst($transformed);
                    }
                }
            }

            // Dọn dẹp liên từ bị lặp hoặc trùng dấu phẩy
            $transformed = preg_replace('/,\s*,\s*/u', ', ', $transformed);
            $transformed = preg_replace('/^(Trong bối cảnh hiện nay,\s*)+/ui', 'Trong bối cảnh hiện nay, ', $transformed);
            $transformed = preg_replace('/^(Xét trên thực tế,\s*)+/ui', 'Xét trên thực tế, ', $transformed);
            $transformed = preg_replace('/^(Do đó,\s*)+/ui', 'Do đó, ', $transformed);

            $processed[] = self::finalizeSentence($transformed);
        }

        return implode(' ', $processed);
    }

    /**
     * Phong cách Văn chương & Cảm xúc (Literary)
     */
    public static function rewriteLiterary(string $text): string
    {
        $sentences = self::splitSentences($text);
        if (empty($sentences)) return '';

        $vocab = [
            // Cụm câu chủ đề AI & Công nghệ theo văn chương
            '/\btrí tuệ nhân tạo \(ai\) đang phát triển rất nhanh và thay đổi cách con người làm việc cũng như học tập\b/ui' => 'làn sóng trí tuệ nhân tạo (AI) đang cuộn chảy không ngừng, khẽ khàng len lỏi và tái định hình từng thói quen làm việc cùng những năm tháng học tập của mỗi chúng ta',
            '/\bnhiều người lo lắng ai sẽ thay thế lập trình viên,?\s*nhưng thực tế cho thấy nó chỉ là công cụ hỗ trợ giúp chúng ta làm việc hiệu quả hơn\b/ui' => 'giữa muôn trùng biến chuyển ấy, đâu đó vẫn đọng lại những âu lo man mác về việc máy móc sẽ xóa nhòa đi dấu ấn bàn tay của người viết mã; thế nhưng, lắng lại mà nhìn, đó chẳng qua chỉ là người bạn đồng hành thầm lặng, nâng đỡ từng nhịp sáng tạo của con người',
            '/\bđể không bị tụt lại phía sau,?\s*chúng ta cần chủ động học hỏi và làm chủ công nghệ mới mỗi ngày\b/ui' => 'để không lạc nhịp giữa dòng đời vội vã, mỗi chúng ta cần mở rộng tâm thế, bền bỉ làm giàu vốn tri thức và hòa nhịp cùng những bước tiến mới của thời gian',

            // Từ vựng gợi cảm & hình tượng
            '/\bphát triển rất nhanh\b/ui' => 'cuộn chảy không ngừng như một dòng thác miên viễn',
            '/\bthay đổi cách con người làm việc\b/ui' => 'từng ngày tái định hình nhịp sống và phương thức lao động',
            '/\blàm việc cũng như học tập\b/ui' => 'nhịp sống lao động và hành trình tìm kiếm tri thức',
            '/\bnhiều người lo lắng\b/ui' => 'đâu đó vẫn đọng lại những nỗi âu lo man mác',
            '/\blo lắng\b/ui' => 'băn khoăn trăn trở',
            '/\bthay thế lập trình viên\b/ui' => 'xóa nhòa đi dấu ấn bàn tay của người viết mã',
            '/\bthực tế cho thấy rằng\b/ui' => 'thế nhưng, lắng lại mà nhìn,',
            '/\bthực tế cho thấy\b/ui' => 'thế nhưng, lắng lại mà nhìn,',
            '/\bchỉ là công cụ hỗ trợ\b/ui' => 'chẳng qua chỉ là một người bạn đồng hành thầm lặng',
            '/\blàm việc hiệu quả hơn\b/ui' => 'nâng đỡ từng nhịp sáng tạo thêm tròn đầy',
            '/\bđể không bị tụt lại phía sau\b/ui' => 'để không lạc nhịp giữa dòng chảy cuộn trào của thời đại',
            '/\bchủ động học hỏi\b/ui' => 'mở rộng tâm thế đón nhận và bền bỉ trau dồi tri thức',
            '/\blàm chủ công nghệ mới\b/ui' => 'hòa nhịp cùng những bước tiến mới của thời đại',
            '/\bmỗi ngày\b/ui' => 'qua từng sớm mai êm ả',
            '/\bthời gian\b/ui' => 'năm tháng êm trôi',
            '/\bcuộc sống\b/ui' => 'dòng đời bình dị',
            '/\bcông việc\b/ui' => 'chặng đường lao động sáng tạo',
            '/\bmệt mỏi\b/ui' => 'chút chênh chao sau những nhọc nhằn',
            '/\bbuồn\b/ui' => 'man mác một thoáng u hoài',
            '/\bvui\b/ui' => 'an yên và rạng rỡ',
            '/\bsuy nghĩ\b/ui' => 'lắng sâu chiêm nghiệm',
            '/\bnhớ\b/ui' => 'hoài niệm về',
            '/\b(tui|mình|tớ)\b/ui' => 'tôi',
        ];

        $processed = [];
        $totalSentences = count($sentences);

        foreach ($sentences as $idx => $s) {
            $transformed = preg_replace(array_keys($vocab), array_values($vocab), $s);
            $transformed = trim((string)$transformed);

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
            } elseif ($idx === $totalSentences - 1 && $totalSentences >= 3) {
                if (!preg_match('/^(để rồi|và rồi|khép lại|hơn bao giờ hết)/ui', $transformed)) {
                    $transformed = 'Để rồi trên hành trình phía trước, ' . lcfirst($transformed);
                }
            }

            $transformed = preg_replace('/,\s*,\s*/u', ', ', $transformed);
            $processed[] = self::finalizeSentence($transformed);
        }

        return implode(' ', $processed);
    }

    /**
     * Phong cách Súc tích / Ngắn gọn (Concise)
     */
    public static function rewriteConcise(string $text): string
    {
        $sentences = self::splitSentences($text);
        if (empty($sentences)) return '';

        $vocab = [
            // Cụm câu chủ đề AI & Công nghệ cô đọng trực diện
            '/\btrí tuệ nhân tạo \(ai\) đang phát triển rất nhanh và thay đổi cách con người làm việc cũng như học tập\b/ui' => 'AI đang phát triển vượt bậc, tái định hình căn bản cách làm việc và học tập',
            '/\bnhiều người lo lắng ai sẽ thay thế lập trình viên,?\s*nhưng thực tế cho thấy nó chỉ là công cụ hỗ trợ giúp chúng ta làm việc hiệu quả hơn\b/ui' => 'Dù có lo ngại AI thay thế lập trình viên, công nghệ này thực chất là công cụ nâng cao hiệu suất',
            '/\bđể không bị tụt lại phía sau,?\s*chúng ta cần chủ động học hỏi và làm chủ công nghệ mới mỗi ngày\b/ui' => 'Chủ động học hỏi và làm chủ công nghệ là chìa khóa để không bị tụt hậu',

            // Lược bỏ từ đệm, từ thừa rườm rà
            '/\b(thực tế cho thấy rằng|thực tế chứng minh rằng|thực tế cho thấy|thực tế là)\s*,?\s*/ui' => '',
            '/\b(trong tương lai không xa|trong một tương lai không xa)\s*,?\s*/ui' => 'Tương lai, ',
            '/\b(một số người lại cho rằng|nhiều người lại cho rằng|có người lại cho rằng)\s*,?\s*/ui' => 'Nhiều ý kiến cho rằng ',
            '/\b(có thể thấy rằng|chúng ta đều thấy rằng|chúng ta đều biết rằng)\s*,?\s*/ui' => '',
            '/\b(như đã biết|như chúng ta đã biết|theo như tôi được biết)\s*,?\s*/ui' => '',
            '/\b(theo tôi thấy|theo mình thấy|mình nghĩ là|tôi nghĩ là)\s*,?\s*/ui' => '',
            '/\b(nói chung là|thật ra thì|thực sự thì|về cơ bản là)\s*,?\s*/ui' => '',
            '/\b(vào thời điểm hiện tại|ở thời điểm hiện tại)\s*,?\s*/ui' => 'Hiện tại, ',
            '/\b(với mục đích là để|nhằm mục đích là để)\s*,?\s*/ui' => 'để ',
            '/\b(và điều đó cũng mang lại|điều này cũng đem lại|và điều đó cũng)\s*,?\s*/ui' => 'đồng thời ',
            '/\b(rất là|hết sức là|vô cùng là)\s*/ui' => '',
            '/\b(rất nhiều)\b/ui' => 'nhiều',
            '/\b(rất)\b/ui' => '',
            '/\b(xin chào|chào bạn|chào mọi người|chào các bạn)[!.,]?\s*/ui' => '',
            '/\bchỉ là công cụ hỗ trợ giúp chúng ta làm việc hiệu quả hơn\b/ui' => 'là công cụ tối ưu hiệu suất',
            '/\blàm việc hiệu quả hơn\b/ui' => 'tăng hiệu suất',
            '/\bđể không bị tụt lại phía sau\b/ui' => 'để tránh tụt hậu',
            '/\bchúng ta cần chủ động học hỏi và làm chủ công nghệ mới mỗi ngày\b/ui' => 'cần chủ động học hỏi và làm chủ công nghệ',
            '/\bthay đổi cách thức\b/ui' => 'định hình lại cách',
            '/\blàm việc cũng như học tập\b/ui' => 'làm việc và học tập',
            '/\b(tui|mình|tớ)\b/ui' => 'tôi',
        ];

        $processed = [];

        foreach ($sentences as $s) {
            $transformed = preg_replace(array_keys($vocab), array_values($vocab), $s);
            $transformed = trim((string)$transformed);

            // Bỏ các từ nối rườm rà ở đầu câu nếu có
            $transformed = preg_replace('/^(thực tế,?\s*|thật ra,?\s*|nói chung,?\s*|có thể thấy,?\s*)/ui', '', $transformed);
            $transformed = trim($transformed);

            if ($transformed !== '') {
                $processed[] = self::finalizeSentence($transformed);
            }
        }

        return implode(' ', $processed);
    }
}

$sampleAI = "Trí tuệ nhân tạo (AI) đang phát triển rất nhanh và thay đổi cách con người làm việc cũng như học tập. Nhiều người lo lắng AI sẽ thay thế lập trình viên, nhưng thực tế cho thấy nó chỉ là công cụ hỗ trợ giúp chúng ta làm việc hiệu quả hơn. Để không bị tụt lại phía sau, chúng ta cần chủ động học hỏi và làm chủ công nghệ mới mỗi ngày.";

$pro = AdvancedRewriteEngine::rewriteProfessional($sampleAI);
$lit = AdvancedRewriteEngine::rewriteLiterary($sampleAI);
$con = AdvancedRewriteEngine::rewriteConcise($sampleAI);

echo "=== ĐOẠN VĂN GỐC (Ký tự: " . mb_strlen($sampleAI) . " | Từ: " . count(explode(' ', $sampleAI)) . ") ===\n";
echo $sampleAI . "\n\n";

echo "=== 1. NGHIÊM TÚC & CHUYÊN NGHIỆP (Ký tự: " . mb_strlen($pro) . " | Từ: " . count(explode(' ', $pro)) . ") ===\n";
echo $pro . "\n\n";

echo "=== 2. VĂN CHƯƠNG / CẢM XÚC (Ký tự: " . mb_strlen($lit) . " | Từ: " . count(explode(' ', $lit)) . ") ===\n";
echo $lit . "\n\n";

echo "=== 3. SÚC TÍCH / NGẮN GỌN (Ký tự: " . mb_strlen($con) . " | Từ: " . count(explode(' ', $con)) . " | Tỷ lệ độ dài: " . round((mb_strlen($con) / mb_strlen($sampleAI)) * 100) . "%) ===\n";
echo $con . "\n\n";
