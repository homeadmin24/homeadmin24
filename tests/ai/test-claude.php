#!/usr/bin/env php
<?php

/**
 * Test Claude API directly
 */

require __DIR__.'/../../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/../../.env');

// Boot Symfony kernel
$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

echo "\n=== Claude API Test ===\n\n";

// Get Claude provider
$claudeProvider = $container->get(\App\Service\AI\ClaudeProvider::class);

// Check if available
echo "1. Checking availability...\n";
echo "   Available: " . ($claudeProvider->isAvailable() ? "✓ YES" : "✗ NO") . "\n";
echo "   API Key length: " . strlen($_ENV['ANTHROPIC_API_KEY'] ?? '') . " chars\n\n";

if (!$claudeProvider->isAvailable()) {
    echo "ERROR: Claude is not available!\n";
    exit(1);
}

// Test simple prompt
echo "2. Testing simple HGA quality check...\n";
echo "   Started at: " . date('H:i:s') . "\n";

$testPrompt = <<<PROMPT
Analysiere diese Hausgeldabrechnung:

EINHEIT: 0003 - Test Einheit
KOSTEN: 5000.00 EUR
ZAHLUNGEN: 5000.00 EUR

Antworte NUR mit gültigem JSON:
{
    "overall_assessment": "pass",
    "confidence": 1.0,
    "issues_found": [],
    "summary": "Test successful"
}
PROMPT;

try {
    $startTime = microtime(true);
    $result = $claudeProvider->analyzeHgaQuality($testPrompt);
    $duration = microtime(true) - $startTime;

    echo "   Completed at: " . date('H:i:s') . "\n";
    echo "   Duration: " . round($duration, 2) . " seconds\n\n";

    echo "3. Result:\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    echo "✓ SUCCESS! Claude API is working.\n\n";

} catch (\Exception $e) {
    echo "   ✗ FAILED!\n";
    echo "   Error: " . $e->getMessage() . "\n\n";

    // Check logs for more details
    echo "Recent logs:\n";
    $logFile = __DIR__ . '/../../var/log/dev.log';
    if (file_exists($logFile)) {
        $logs = shell_exec("tail -50 " . escapeshellarg($logFile) . " | grep -A 5 'Claude'");
        echo $logs ?: "No Claude logs found\n";
    }

    exit(1);
}
