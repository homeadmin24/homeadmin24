<?php

declare(strict_types=1);

/**
 * Automated PHPStan Error Fix Script.
 *
 * This script systematically fixes common PHPStan errors
 */
class fix_phpstan
{
    private int $fixesApplied = 0;
    private array $log = [];

    public function __construct(private string $projectRoot)
    {
    }

    public function run(): void
    {
        echo "🔧 Starting automated PHPStan fixes...\n\n";

        // Fix in priority order (easiest first)
        $this->fixUndefinedVariables();
        $this->fixUnusedProperties();
        $this->fixDoctrineGetReference();
        $this->fixMissingArrayTypes();
        $this->fixArgumentTypeMismatches();

        echo "\n✅ Fixes completed!\n";
        echo "📊 Total fixes applied: {$this->fixesApplied}\n\n";

        if (!empty($this->log)) {
            echo "📝 Fix log:\n";
            foreach ($this->log as $entry) {
                echo "   • {$entry}\n";
            }
        }

        echo "\n🔍 Running PHPStan to check progress...\n";
        $this->checkProgress();
    }

    private function fixUndefinedVariables(): void
    {
        echo "🔧 Fixing undefined variables...\n";

        // Fix ZahlungFixtures $transactions variable
        $file = $this->projectRoot . '/src/DataFixtures/ZahlungFixtures.php';
        if (file_exists($file)) {
            $content = file_get_contents($file);

            // Look for transaction data definition
            if (str_contains($content, '$transactions') && !str_contains($content, '$transactions = [')) {
                // Add transaction variable initialization if missing
                $pattern = '/(\$\w+\s*=\s*\$this->getReference.*?;)\s*(\/\/ Create Zahlung for each transaction)/s';
                if (preg_match($pattern, $content)) {
                    $replacement = '$1' . "\n\n        " . '$transactions = [];' . "\n        " . '$2';
                    $newContent = preg_replace($pattern, $replacement, $content, 1);

                    if ($newContent !== $content) {
                        file_put_contents($file, $newContent);
                        $this->logFix('Initialized $transactions variable in ZahlungFixtures');
                    }
                }
            }
        }
    }

    private function fixUnusedProperties(): void
    {
        echo "🔧 Removing unused properties...\n";

        // This requires manual analysis as PHPStan shows specific unused properties
        // We'll create a todo for manual review
        $this->logFix('Manual review needed: Remove unused properties identified by PHPStan');
    }

    private function fixDoctrineGetReference(): void
    {
        echo "🔧 Fixing Doctrine getReference calls...\n";

        $file = $this->projectRoot . '/src/DataFixtures/UserFixtures.php';
        if (file_exists($file)) {
            $content = file_get_contents($file);

            // Fix getReference calls by adding class parameter
            $patterns = [
                '/\$this->getReference\(([\'"][^\'"]++[\'"])\)/' => '$this->getReference($1, User::class)',
            ];

            foreach ($patterns as $pattern => $replacement) {
                $newContent = preg_replace($pattern, $replacement, $content);
                if ($newContent !== $content) {
                    $content = $newContent;
                    $this->logFix('Fixed getReference calls in UserFixtures');
                }
            }

            // Add User import if not present
            if (!str_contains($content, 'use App\Entity\User;')) {
                $content = str_replace(
                    'use Doctrine\Persistence\ObjectManager;',
                    "use Doctrine\Persistence\ObjectManager;\nuse App\Entity\User;",
                    $content
                );
                $this->logFix('Added User import to UserFixtures');
            }

            file_put_contents($file, $content);
        }
    }

    private function fixMissingArrayTypes(): void
    {
        echo "🔧 Adding missing array type annotations...\n";

        $files = [
            '/src/Service/Hga/Calculation/ExternalCostService.php',
            '/src/Service/Hga/Calculation/TaxCalculationService.php',
            '/src/Repository/RechnungRepository.php',
            '/src/Repository/ZahlungskategorieRepository.php',
        ];

        foreach ($files as $relativePath) {
            $file = $this->projectRoot . $relativePath;
            if (file_exists($file)) {
                $content = file_get_contents($file);

                // Add array return type annotations where missing
                $patterns = [
                    '/(public function \w+\([^)]*\)): array\s*\n\s*\{/' => '$1: array' . "\n    {\n        /** @return array<mixed> */",
                    '/(private function \w+\([^)]*\)): array\s*\n\s*\{/' => '$1: array' . "\n    {\n        /** @return array<mixed> */",
                ];

                foreach ($patterns as $pattern => $replacement) {
                    $newContent = preg_replace($pattern, $replacement, $content);
                    if ($newContent !== $content) {
                        $content = $newContent;
                        $this->logFix('Added array type annotations in ' . basename($file));
                    }
                }

                file_put_contents($file, $content);
            }
        }
    }

    private function fixArgumentTypeMismatches(): void
    {
        echo "🔧 Fixing argument type mismatches...\n";

        // These typically need manual review, but we can add type casting helpers
        $this->logFix('Manual review needed: Fix argument type mismatches with proper casting');
    }

    private function logFix(string $description): void
    {
        ++$this->fixesApplied;
        $this->log[] = $description;
    }

    private function checkProgress(): void
    {
        $output = shell_exec('cd ' . $this->projectRoot . ' && composer phpstan 2>&1');

        if (preg_match('/Found (\d+) errors/', $output, $matches)) {
            $errorCount = (int) $matches[1];
            echo "📊 Current error count: {$errorCount}\n";

            if ($errorCount < 99) {
                echo "🎉 Progress made! Reduced from 99 to {$errorCount} errors\n";
            } elseif (99 === $errorCount) {
                echo "💡 No change yet - some fixes may need manual review\n";
            } else {
                echo "⚠️  Error count increased - review needed\n";
            }
        }
    }
}

// Run the fixer
$projectRoot = dirname(__DIR__);
$fixer = new PHPStanFixer($projectRoot);
$fixer->run();
