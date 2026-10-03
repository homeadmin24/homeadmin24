#!/usr/bin/env php
<?php

/**
 * Direct Ollama test - bypasses web server timeouts
 *
 * Usage: docker compose exec web php test-ollama-direct.php
 */

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

// Boot Symfony kernel
$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

// Get services
$ollamaService = $container->get(\App\Service\OllamaService::class);

echo "\n=== Ollama Direct Test ===\n\n";

// 1. Check if enabled
echo "1. Checking if Ollama is enabled...\n";
$enabled = $ollamaService->isEnabled();
echo "   Status: " . ($enabled ? "✓ ENABLED" : "✗ DISABLED") . "\n\n";

if (!$enabled) {
    echo "ERROR: OllamaService is disabled!\n";
    echo "Check: AI_ENABLED=" . ($_ENV['AI_ENABLED'] ?? 'not set') . "\n";
    exit(1);
}

// 2. Check if Ollama is available
echo "2. Checking if Ollama service is reachable...\n";
$available = $ollamaService->isOllamaAvailable();
echo "   Status: " . ($available ? "✓ AVAILABLE" : "✗ NOT AVAILABLE") . "\n\n";

if (!$available) {
    echo "ERROR: Ollama is not reachable!\n";
    exit(1);
}

// 3. Test simple AI query
echo "3. Testing HGA quality analysis (this will take 60-90 seconds)...\n";
echo "   Started at: " . date('H:i:s') . "\n";

$testPrompt = <<<PROMPT
Analysiere diese Hausgeldabrechnung auf Fehler und Auffälligkeiten:

EINHEIT:
- Nummer: TEST-001
- Beschreibung: Test Einheit
- MEA-Anteil: 100/1000

KOSTEN-ÜBERSICHT:
- Gesamtkosten Einheit: 5000.00 EUR
- Umlagefähig: 4000.00 EUR
- Nicht umlagefähig: 1000.00 EUR

ZAHLUNGEN:
- Soll: 4000.00 EUR
- Ist: 4000.00 EUR
- Differenz: 0.00 EUR
- Status: Ausgeglichen

Antworte NUR mit gültigem JSON:
{
    "overall_assessment": "pass" | "warning" | "critical",
    "confidence": 0.0-1.0,
    "issues_found": [],
    "summary": "Zusammenfassung der Qualitätsprüfung"
}
PROMPT;

try {
    $startTime = microtime(true);
    $result = $ollamaService->analyzeHgaQuality($testPrompt);
    $duration = microtime(true) - $startTime;

    echo "   Completed at: " . date('H:i:s') . "\n";
    echo "   Duration: " . round($duration, 2) . " seconds\n\n";

    echo "4. Result:\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    echo "✓ SUCCESS! Ollama AI is working correctly.\n\n";

} catch (\Exception $e) {
    echo "   ✗ FAILED!\n";
    echo "   Error: " . $e->getMessage() . "\n\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
