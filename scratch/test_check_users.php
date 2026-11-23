<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/Models/User.php';

$userModel = new User();
$admin = $userModel->findByLogin('admin');
echo "Admin found: " . ($admin ? "YES (ID: {$admin['id']}, Name: {$admin['full_name']})" : "NO") . "\n";

$demo = $userModel->findByLogin('demo');
echo "Demo found: " . ($demo ? "YES (ID: {$demo['id']}, Name: {$demo['full_name']})" : "NO") . "\n";
