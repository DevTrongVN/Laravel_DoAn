<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config/ai_config.php';

echo "Provider: " . ($config['provider'] ?? 'none') . "\n";
echo "API Key Length: " . strlen($config['api_key'] ?? '') . "\n";
echo "API Key Preview: " . ($config['api_key'] ? substr($config['api_key'], 0, 8) . '...' : '(EMPTY)') . "\n";
echo "Model: " . ($config['model'] ?? 'none') . "\n";
echo "Env GEMINI_API_KEY: " . (getenv('GEMINI_API_KEY') ? 'SET' : 'NOT SET') . "\n";
echo "Env AI_API_KEY: " . (getenv('AI_API_KEY') ? 'SET' : 'NOT SET') . "\n";
