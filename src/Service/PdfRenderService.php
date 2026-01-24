<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PdfRenderService
{
    public function __construct(private readonly string $projectDir)
    {
    }

    /**
     * Render a PDF into PNG images and return the file paths.
     *
     * @return array<int, string>
     */
    public function renderToImages(string $pdfPath, int $dpi = 150): array
    {
        if (!file_exists($pdfPath)) {
            throw new \InvalidArgumentException("PDF file not found: {$pdfPath}");
        }

        $outputDir = $this->createTempDir();
        $outputPrefix = $outputDir . '/page';

        $process = new Process(['pdftoppm', '-png', '-r', (string) $dpi, $pdfPath, $outputPrefix]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $files = glob($outputPrefix . '-*.png') ?: [];
        sort($files);

        return $files;
    }

    /**
     * Clean up rendered images.
     *
     * @param array<int, string> $imagePaths
     */
    public function cleanup(array $imagePaths): void
    {
        foreach ($imagePaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function createTempDir(): string
    {
        $baseDir = $this->projectDir . '/var/tmp';
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        $dir = $baseDir . '/docintel-' . bin2hex(random_bytes(8));
        mkdir($dir, 0755, true);

        return $dir;
    }
}
