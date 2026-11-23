<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';

$pdo = Database::getInstance()->getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function addColumnIfNotExists(PDO $pdo, string $table, string $column, string $def): void {
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($stmt->fetch()) {
        echo "Column `$table`.`$column` already exists.\n";
        return;
    }
    $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $def";
    $pdo->exec($sql);
    echo "Added column `$table`.`$column` successfully.\n";
}

echo "=== Running safe schema migrations (MySQL compatible) ===\n";

// posts table
addColumnIfNotExists($pdo, 'posts', 'ai_analyzed_at', 'TIMESTAMP NULL DEFAULT NULL AFTER ai_status');
addColumnIfNotExists($pdo, 'posts', 'ai_suggested_action', 'VARCHAR(50) NULL DEFAULT NULL AFTER ai_analyzed_at');
addColumnIfNotExists($pdo, 'posts', 'final_decision', 'VARCHAR(20) NULL DEFAULT NULL AFTER ai_suggested_action');

// comments table
addColumnIfNotExists($pdo, 'comments', 'ai_severity', "ENUM('low','medium','high') NULL DEFAULT NULL");
addColumnIfNotExists($pdo, 'comments', 'ai_violation_type', 'VARCHAR(50) NULL DEFAULT NULL');
addColumnIfNotExists($pdo, 'comments', 'ai_reason', 'TEXT NULL DEFAULT NULL');
addColumnIfNotExists($pdo, 'comments', 'ai_status', "ENUM('pending','approved','rejected') NULL DEFAULT NULL");

// ai_moderation_items table
addColumnIfNotExists($pdo, 'ai_moderation_items', 'final_decision', 'VARCHAR(20) NULL DEFAULT NULL AFTER admin_note');
addColumnIfNotExists($pdo, 'ai_moderation_items', 'final_decision_at', 'DATETIME NULL DEFAULT NULL AFTER final_decision');

echo "\n=== Verifying new columns ===\n";
$postCols = $pdo->query('SHOW COLUMNS FROM posts')->fetchAll(PDO::FETCH_COLUMN);
echo "posts.final_decision: " . (in_array('final_decision', $postCols) ? 'OK' : 'MISSING') . "\n";
echo "posts.ai_analyzed_at: " . (in_array('ai_analyzed_at', $postCols) ? 'OK' : 'MISSING') . "\n";
echo "posts.ai_suggested_action: " . (in_array('ai_suggested_action', $postCols) ? 'OK' : 'MISSING') . "\n";

$commentCols = $pdo->query('SHOW COLUMNS FROM comments')->fetchAll(PDO::FETCH_COLUMN);
echo "comments.ai_severity: " . (in_array('ai_severity', $commentCols) ? 'OK' : 'MISSING') . "\n";
echo "comments.ai_violation_type: " . (in_array('ai_violation_type', $commentCols) ? 'OK' : 'MISSING') . "\n";
echo "comments.ai_reason: " . (in_array('ai_reason', $commentCols) ? 'OK' : 'MISSING') . "\n";
echo "comments.ai_status: " . (in_array('ai_status', $commentCols) ? 'OK' : 'MISSING') . "\n";

$aimCols = $pdo->query('SHOW COLUMNS FROM ai_moderation_items')->fetchAll(PDO::FETCH_COLUMN);
echo "ai_moderation_items.final_decision: " . (in_array('final_decision', $aimCols) ? 'OK' : 'MISSING') . "\n";
echo "ai_moderation_items.final_decision_at: " . (in_array('final_decision_at', $aimCols) ? 'OK' : 'MISSING') . "\n";

echo "\nDone.\n";
