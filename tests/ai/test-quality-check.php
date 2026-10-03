<?php
require __DIR__.'/vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;
(new Dotenv())->bootEnv(__DIR__.'/.env');
$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();
$dokumentRepo = $container->get('doctrine')->getRepository(\App\Entity\Dokument::class);
$qualityService = $container->get(\App\Service\Hga\HgaQualityCheckService::class);
$dokumentId = $argv[1] ?? 102;
$dokument = $dokumentRepo->find($dokumentId);
if (!$dokument) die("Document $dokumentId not found\n");
echo "Testing quality check for document #$dokumentId (may take 60-90s)...\n\n";
$start = microtime(true);
try {
    $result = $qualityService->runQualityChecks($dokument, 'ollama', true);
    echo "✓ Success! Duration: " . round(microtime(true) - $start, 2) . "s\n\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (\Exception $e) {
    echo "✗ Failed: " . $e->getMessage() . "\n";
}
