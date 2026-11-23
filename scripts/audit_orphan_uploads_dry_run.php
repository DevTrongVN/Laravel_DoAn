<?php
declare(strict_types=1);

/**
 * Script CLI chế độ DRY-RUN (CHỈ ĐỌC — KHÔNG XÓA FILE THẬT).
 * Quét thư mục public/uploads và public/assets/uploads, đối chiếu với posts.thumbnail và users.avatar
 * để liệt kê các tệp ảnh/file mồ côi kèm kích thước, thời gian sửa, số lượng tham chiếu và khuyến nghị.
 *
 * Cách chạy:
 *   php scripts/audit_orphan_uploads_dry_run.php
 *   php scripts/audit_orphan_uploads_dry_run.php --json
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../core/Database.php';

$asJson = in_array('--json', $argv ?? [], true);
$pdo = Database::getInstance()->getConnection();
$publicDir = realpath(__DIR__ . '/../public');
if ($publicDir === false) {
    fwrite(STDERR, "Không tìm thấy thư mục public/.\n");
    exit(1);
}
$publicDirNorm = str_replace('\\', '/', $publicDir);

$normalize = static function (?string $raw): ?string {
    if ($raw === null) return null;
    $v = trim($raw);
    if ($v === '' || preg_match('#^(https?://|data:|//)#i', $v)) {
        return null;
    }
    $v = ltrim(str_replace('\\', '/', $v), '/');
    $v = preg_replace('#^DoAnMaNguonMo/public/#i', '', $v) ?? $v;
    $v = preg_replace('#^public/#i', '', $v) ?? $v;
    return $v;
};

// 1. Thu thập tham chiếu hợp lệ từ posts.thumbnail
$postRefs = [];
foreach ($pdo->query("SELECT thumbnail FROM posts WHERE thumbnail IS NOT NULL AND TRIM(thumbnail) <> ''") as $row) {
    $norm = $normalize((string)$row['thumbnail']);
    if ($norm !== null) {
        $postRefs[$norm] = ($postRefs[$norm] ?? 0) + 1;
    }
}

// 2. Thu thập tham chiếu hợp lệ từ users.avatar
$avatarRefs = [];
foreach ($pdo->query("SELECT avatar FROM users WHERE avatar IS NOT NULL AND TRIM(avatar) <> ''") as $row) {
    $norm = $normalize((string)$row['avatar']);
    if ($norm !== null) {
        $avatarRefs[$norm] = ($avatarRefs[$norm] ?? 0) + 1;
    }
}

// 3. Quét tệp thực tế trong thư mục upload (CHỈ ĐỌC)
$scanRoots = ['uploads', 'assets/uploads'];
$allFiles = [];
$orphans  = [];

foreach ($scanRoots as $sub) {
    $abs = $publicDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sub);
    if (!is_dir($abs)) continue;

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $ext = strtolower($file->getExtension());
        if (in_array($ext, ['htaccess', 'gitkeep', 'md', 'txt'], true)) continue;

        $full = str_replace('\\', '/', $file->getPathname());
        $rel  = ltrim(substr($full, strlen($publicDirNorm)), '/');

        $pCount = $postRefs[$rel] ?? 0;
        $aCount = $avatarRefs[$rel] ?? 0;
        $total  = $pCount + $aCount;

        $isAvatarDir = str_starts_with($rel, 'uploads/avatars/') || str_starts_with($rel, 'assets/uploads/avatars/');
        $reason = $total === 0
            ? ($isAvatarDir
                ? 'Tệp ảnh đại diện không còn được bất kỳ tài khoản users.avatar nào tham chiếu.'
                : 'Tệp upload không còn được bất kỳ bài viết posts.thumbnail hay users.avatar nào tham chiếu.')
            : 'Đang được sử dụng hợp lệ.';

        $entry = [
            'path'           => 'public/' . $rel,
            'rel_path'       => $rel,
            'size_bytes'     => $file->getSize(),
            'modified_at'    => date('Y-m-d H:i:s', $file->getMTime()),
            'post_refs'      => $pCount,
            'avatar_refs'    => $aCount,
            'total_refs'     => $total,
            'reason'         => $reason,
            'recommendation' => $total === 0 ? 'Có thể xóa sau khi người dùng xác nhận (dry-run hiện giữ nguyên).' : 'Giữ nguyên.',
        ];
        $allFiles[] = $entry;
        if ($total === 0) {
            $orphans[] = $entry;
        }
    }
}

$summary = [
    'mode'                   => 'DRY_RUN_READ_ONLY',
    'scanned_files_total'    => count($allFiles),
    'orphan_files_count'     => count($orphans),
    'orphan_total_bytes'     => array_sum(array_column($orphans, 'size_bytes')),
    'files_deleted'          => 0,
    'orphan_files'           => $orphans,
];

if ($asJson) {
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

echo "=== DRY-RUN AUDIT ẢNH/FILE MỒ CÔI (CHỈ ĐỌC — KHÔNG XÓA FILE) ===\n";
echo "Tổng file đã quét : {$summary['scanned_files_total']}\n";
echo "Số file mồ côi    : {$summary['orphan_files_count']}\n";
echo "Tổng dung lượng   : {$summary['orphan_total_bytes']} bytes\n";
echo "Số file đã xóa    : 0 (DRY-RUN)\n\n";
foreach ($orphans as $idx => $o) {
    echo sprintf(
        "%d. %s | %d bytes | %s | refs=%d | %s\n",
        $idx + 1,
        $o['path'],
        $o['size_bytes'],
        $o['modified_at'],
        $o['total_refs'],
        $o['recommendation']
    );
}
