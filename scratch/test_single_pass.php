<?php
declare(strict_types=1);

$rules = [
    'thay thế lập trình viên' => 'chiếm lĩnh vị trí của các kỹ sư phần mềm',
    'thay thế' => 'chiếm lĩnh vị trí của',
    'rất mệt mỏi' => 'cảm thấy kiệt sức và quá tải',
    'mệt mỏi' => 'quá tải',
    'rất' => 'đặc biệt',
    'hôm nay' => 'thời điểm hiện tại',
    'trí tuệ nhân tạo (ai)' => 'trí tuệ nhân tạo (AI)',
    'phát triển rất nhanh' => 'tăng trưởng bứt phá',
];

// Sắp xếp các pattern theo độ dài giảm dần
uksort($rules, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));

// Tạo regex duy nhất với word boundary
$patterns = [];
foreach (array_keys($rules) as $k) {
    $patterns[] = preg_quote($k, '/');
}
$regex = '/\b(' . implode('|', $patterns) . ')\b/ui';

$text = "Hôm nay mình rất mệt mỏi vì lo lắng AI sẽ thay thế lập trình viên.";

$result = preg_replace_callback($regex, function($matches) use ($rules) {
    $lower = mb_strtolower($matches[0], 'UTF-8');
    foreach ($rules as $k => $v) {
        if (mb_strtolower($k, 'UTF-8') === $lower) {
            return $v;
        }
    }
    return $matches[0];
}, $text);

echo "Bản gốc: " . $text . "\n";
echo "Kết quả: " . $result . "\n";
