<?php
declare(strict_types=1);

class AIServiceRewriter
{
    /**
     * Tách văn bản thành danh sách câu độc lập
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
     * Chuẩn hóa và hoàn thiện câu
     */
    public static function finalizeSentence(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = preg_replace('/\s+([,.?!;:])/u', '$1', $s);
        $s = trim($s);
        if ($s === '') return '';
        $first = mb_substr($s, 0, 1, 'UTF-8');
        $rest  = mb_substr($s, 1, null, 'UTF-8');
        $s = mb_strtoupper($first, 'UTF-8') . $rest;
        if (!preg_match('/[.!?]$/u', $s)) {
            $s .= '.';
        }
        return $s;
    }

    /**
     * Thay thế cụm từ theo quy tắc Single-pass regex dài nhất trước
     */
    private static function applyDictionary(string $text, array $dict): string
    {
        if (empty($dict) || trim($text) === '') return $text;

        uksort($dict, fn($a, $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        $escaped = [];
        $map = [];
        foreach ($dict as $src => $dest) {
            $escaped[] = preg_quote($src, '/');
            $map[mb_strtolower($src, 'UTF-8')] = $dest;
        }

        $pattern = '/(?<![\p{L}\p{N}_])(' . implode('|', $escaped) . ')(?![\p{L}\p{N}_])/ui';

        $replaced = preg_replace_callback($pattern, function($matches) use ($map) {
            $key = mb_strtolower($matches[0], 'UTF-8');
            return $map[$key] ?? $matches[0];
        }, $text);

        return (string)$replaced;
    }

    /**
     * 1. Phong cách Nghiêm túc & Chuyên nghiệp (Professional)
     */
    public static function rewriteProfessional(string $text): string
    {
        $sentences = self::splitSentences($text);
        if (empty($sentences)) return '';

        $dict = [
            // Đại từ nhân xưng chuẩn hóa
            'mình' => 'tôi',
            'tớ' => 'tôi',
            'tui' => 'tôi',
            'chúng mình' => 'chúng ta',
            'bọn mình' => 'chúng ta',
            'người ta' => 'các cá nhân',

            // Các cụm câu chủ đề AI & Công nghệ
            'trí tuệ nhân tạo (ai) đang phát triển rất nhanh và thay đổi cách con người làm việc cũng như học tập' => 'trí tuệ nhân tạo (AI) đang tăng trưởng bứt phá và chuyển biến căn bản phương thức vận hành công việc cũng như quá trình nghiên cứu, trau dồi tri thức của con người',
            'nhiều người lo lắng ai sẽ thay thế lập trình viên, nhưng thực tế cho thấy nó chỉ là công cụ hỗ trợ giúp chúng ta làm việc hiệu quả hơn' => 'mặc dù tồn tại không ít quan ngại về nguy cơ AI thay thế vị trí của các kỹ sư phần mềm, xét trên bình diện thực tế, công nghệ này đóng vai trò như một phương tiện đắc lực giúp tối ưu hóa quy trình và gia tăng đáng kể hiệu năng lao động',
            'nhiều người lo lắng ai sẽ thay thế lập trình viên' => 'nhiều chuyên gia và nhân sự bày tỏ quan ngại sâu sắc về nguy cơ AI có thể thay thế hoàn toàn các kỹ sư phần mềm',
            'nhưng thực tế cho thấy nó chỉ là công cụ hỗ trợ giúp chúng ta làm việc hiệu quả hơn' => 'tuy nhiên trên bình diện thực tế, giải pháp này đóng vai trò như một phương tiện hỗ trợ đắc lực nhằm tối ưu hóa năng suất và hiệu năng vận hành',
            'để không bị tụt lại phía sau, chúng ta cần chủ động học hỏi và làm chủ công nghệ mới mỗi ngày' => 'nhằm duy trì lợi thế cạnh tranh bền vững, yêu cầu đặt ra là chúng ta phải chủ động thích ứng, nâng cao năng lực chuyên môn và làm chủ các tiến bộ công nghệ trong kỷ nguyên số',
            'để không bị tụt lại phía sau' => 'nhằm duy trì lợi thế cạnh tranh bền vững',
            'chúng ta cần chủ động học hỏi và làm chủ công nghệ mới mỗi ngày' => 'chúng ta cần tiên phong trau dồi tri thức chuyên môn và làm chủ các tiến bộ công nghệ liên tục',

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
            $transformed = self::applyDictionary($s, $dict);
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
            $transformed = preg_replace('/^(Trong bối cảnh hiện nay,\s*)+/ui', 'Trong bối cảnh hiện nay, ', $transformed);
            $transformed = preg_replace('/^(Xét trên thực tế,\s*)+/ui', 'Xét trên thực tế, ', $transformed);
            $transformed = preg_replace('/^(Do đó,\s*)+/ui', 'Do đó, ', $transformed);
            $transformed = preg_replace('/,\s*,/u', ',', $transformed);

            $processed[] = self::finalizeSentence($transformed);
        }

        return implode(' ', $processed);
    }

    /**
     * 2. Phong cách Văn chương & Cảm xúc (Literary)
     */
    public static function rewriteLiterary(string $text): string
    {
        $sentences = self::splitSentences($text);
        if (empty($sentences)) return '';

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

            // Từ vựng gợi cảm & hình tượng
            'hôm nay' => 'ngày hôm nay, giữa những nhịp đời êm ả,',
            'rất mệt mỏi' => 'lòng không tránh khỏi chút chênh chao sau những nhọc nhằn dồn nén',
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
            $transformed = self::applyDictionary($s, $dict);
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

            $transformed = preg_replace('/,\s*,/u', ',', $transformed);
            $processed[] = self::finalizeSentence($transformed);
        }

        return implode(' ', $processed);
    }

    /**
     * 3. Phong cách Súc tích / Ngắn gọn (Concise)
     */
    public static function rewriteConcise(string $text): string
    {
        $sentences = self::splitSentences($text);
        if (empty($sentences)) return '';

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
            $transformed = self::applyDictionary($s, $dict);
            $transformed = trim($transformed);

            // Bỏ các từ nối rườm rà ở đầu câu
            $transformed = preg_replace('/^(thực tế,?\s*|thật ra,?\s*|nói chung,?\s*|có thể thấy,?\s*|rằng,?\s*)/ui', '', $transformed);
            $transformed = trim($transformed);

            if ($transformed !== '') {
                $processed[] = self::finalizeSentence($transformed);
            }
        }

        return implode(' ', $processed);
    }
}

// Chạy thử 3 đoạn văn mẫu
$samples = [
    "AI Sample" => "Trí tuệ nhân tạo (AI) đang phát triển rất nhanh và thay đổi cách con người làm việc cũng như học tập. Nhiều người lo lắng AI sẽ thay thế lập trình viên, nhưng thực tế cho thấy nó chỉ là công cụ hỗ trợ giúp chúng ta làm việc hiệu quả hơn. Để không bị tụt lại phía sau, chúng ta cần chủ động học hỏi và làm chủ công nghệ mới mỗi ngày.",
    "Lifestyle Sample" => "Hôm nay mình rất mệt mỏi vì công việc quá nhiều. Mình nghĩ là ai cũng cần nghỉ ngơi sau thời gian làm việc căng thẳng để lấy lại năng lượng.",
    "Database Sample" => "Thực tế cho thấy rằng việc tối ưu hóa truy vấn CSDL là hết sức cần thiết. Chúng ta không nên viết câu lệnh truy vấn một cách bừa bãi vì nó sẽ làm cho hệ thống chạy rất chậm và không tốt khi có nhiều người cùng truy cập."
];

foreach ($samples as $title => $text) {
    echo "================================================================================\n";
    echo "TEST: {$title}\n";
    echo "Bản gốc (" . mb_strlen($text) . " ký tự): {$text}\n";
    echo "--------------------------------------------------------------------------------\n";

    $p = AIServiceRewriter::rewriteProfessional($text);
    $l = AIServiceRewriter::rewriteLiterary($text);
    $c = AIServiceRewriter::rewriteConcise($text);

    echo "[PROFESSIONAL] (" . mb_strlen($p) . " ký tự):\n{$p}\n\n";
    echo "[LITERARY]     (" . mb_strlen($l) . " ký tự):\n{$l}\n\n";
    echo "[CONCISE]      (" . mb_strlen($c) . " ký tự - Tỷ lệ: " . round((mb_strlen($c)/mb_strlen($text))*100) . "%):\n{$c}\n";
}
