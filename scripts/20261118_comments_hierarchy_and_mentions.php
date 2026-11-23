<?php
declare(strict_types=1);

/**
 * Idempotent Additive Migration:
 * - Adds root_id, is_deleted, deleted_at, deleted_by + indexes + FKs on `comments`
 * - Backfills root_id for existing nested comments safely (cycle-guarded)
 * - Creates `comment_mentions` table for dynamic @Mention resolution
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$config = require __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $config['dbname'], $config['charset']),
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

$colExists = static function (PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
    $st->execute([$col]);
    return (bool)$st->fetch();
};

$idxExists = static function (PDO $pdo, string $table, string $idx): bool {
    $st = $pdo->prepare("SHOW INDEX FROM `{$table}` WHERE Key_name = ?");
    $st->execute([$idx]);
    return (bool)$st->fetch();
};

$fkExists = static function (PDO $pdo, string $table, string $fk): bool {
    $st = $pdo->prepare("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?");
    $st->execute([$table, $fk]);
    return (bool)$st->fetch();
};

echo "=== MIGRATION: COMMENTS HIERARCHY & MENTIONS ===\n";

// 1. Columns on `comments`
$cols = [
    'root_id'    => "BIGINT UNSIGNED NULL DEFAULT NULL AFTER `parent_id`",
    'is_deleted' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `moderation_status`",
    'deleted_at' => "TIMESTAMP NULL DEFAULT NULL AFTER `is_deleted`",
    'deleted_by' => "BIGINT UNSIGNED NULL DEFAULT NULL AFTER `deleted_at`",
];

foreach ($cols as $name => $def) {
    if ($colExists($pdo, 'comments', $name)) {
        echo "  [SKIP] comments.{$name} already exists.\n";
    } else {
        $pdo->exec("ALTER TABLE `comments` ADD COLUMN `{$name}` {$def}");
        echo "  [ADDED] comments.{$name}\n";
    }
}

// 2. Indexes on `comments`
if (!$idxExists($pdo, 'comments', 'idx_comments_root')) {
    $pdo->exec("ALTER TABLE `comments` ADD INDEX `idx_comments_root` (`root_id`)");
    echo "  [ADDED] INDEX idx_comments_root\n";
} else {
    echo "  [SKIP] INDEX idx_comments_root already exists.\n";
}

if (!$idxExists($pdo, 'comments', 'idx_comments_post_deleted')) {
    $pdo->exec("ALTER TABLE `comments` ADD INDEX `idx_comments_post_deleted` (`post_id`, `is_deleted`, `moderation_status`)");
    echo "  [ADDED] INDEX idx_comments_post_deleted\n";
} else {
    echo "  [SKIP] INDEX idx_comments_post_deleted already exists.\n";
}

// 3. Foreign keys on `comments`
if (!$fkExists($pdo, 'comments', 'fk_comments_root')) {
    $pdo->exec("ALTER TABLE `comments` ADD CONSTRAINT `fk_comments_root` FOREIGN KEY (`root_id`) REFERENCES `comments`(`id`) ON DELETE SET NULL");
    echo "  [ADDED] FK fk_comments_root\n";
} else {
    echo "  [SKIP] FK fk_comments_root already exists.\n";
}

if (!$fkExists($pdo, 'comments', 'fk_comments_deleted_by')) {
    $pdo->exec("ALTER TABLE `comments` ADD CONSTRAINT `fk_comments_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL");
    echo "  [ADDED] FK fk_comments_deleted_by\n";
} else {
    echo "  [SKIP] FK fk_comments_deleted_by already exists.\n";
}

// 4. Backfill root_id for existing replies safely (cycle-guarded)
$replies = $pdo->query("SELECT id, parent_id FROM comments WHERE parent_id IS NOT NULL AND root_id IS NULL")->fetchAll();
if (!empty($replies)) {
    $allParents = [];
    foreach ($pdo->query("SELECT id, parent_id FROM comments")->fetchAll() as $r) {
        $allParents[(int)$r['id']] = $r['parent_id'] !== null ? (int)$r['parent_id'] : null;
    }
    $upd = $pdo->prepare("UPDATE comments SET root_id = ? WHERE id = ?");
    $backfilled = 0;
    $pdo->beginTransaction();
    try {
        foreach ($replies as $rep) {
            $cur = (int)$rep['id'];
            $visited = [$cur => true];
            $p = $allParents[$cur] ?? null;
            $root = $p;
            while ($p !== null && isset($allParents[$p])) {
                if (isset($visited[$p])) break; // cycle guard
                $visited[$p] = true;
                $root = $p;
                $p = $allParents[$p];
            }
            if ($root !== null && $root !== (int)$rep['id']) {
                $upd->execute([$root, (int)$rep['id']]);
                $backfilled++;
            }
        }
        $pdo->commit();
        echo "  [BACKFILLED] root_id set for {$backfilled} existing reply comments.\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
} else {
    echo "  [SKIP] No reply comments need root_id backfill.\n";
}

// 5. Create `comment_mentions` table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `comment_mentions` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `comment_id` BIGINT UNSIGNED NOT NULL,
        `mentioned_user_id` BIGINT UNSIGNED NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_comment_mention` (`comment_id`, `mentioned_user_id`),
        INDEX `idx_cm_user` (`mentioned_user_id`, `created_at`),
        CONSTRAINT `fk_cm_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_cm_user` FOREIGN KEY (`mentioned_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "  [OK] Table `comment_mentions` ensured.\n";

echo "=== MIGRATION COMPLETED SUCCESSFULLY ===\n";
