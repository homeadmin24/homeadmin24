<?php

declare(strict_types=1);

namespace App\Service\Hga\Report;

use App\Entity\WegEinheit;
use App\Service\Hga\HgaServiceInterface;
use App\Service\Hga\ReportGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;
use Twig\Environment;

/**
 * PDF report generator for HGA.
 *
 * Generates PDF reports using headless Chrome and Twig templates, compatible with the new HGA service architecture.
 */
class PdfReportGenerator implements ReportGeneratorInterface
{
    public function __construct(
        private HgaServiceInterface $hgaService,
        private Environment $twig,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function generateReport(WegEinheit $einheit, int $year, array $options = []): string
    {
        $data = $this->hgaService->generateReportData($einheit, $year);

        return $this->generatePdfContent($data);
    }

    /**
     * {@inheritdoc}
     */
    public function getMimeType(): string
    {
        return 'application/pdf';
    }

    /**
     * {@inheritdoc}
     */
    public function getFileExtension(): string
    {
        return 'pdf';
    }

    /**
     * {@inheritdoc}
     */
    public function validateInputs(WegEinheit $einheit, int $year, array $options = []): array
    {
        return $this->hgaService->validateCalculationInputs($einheit, $year);
    }

    /**
     * Generate PDF content from data.
     *
     * @param array<string, mixed> $data
     */
    private function generatePdfContent(array $data): string
    {
        // Render HTML using Twig template
        $html = $this->twig->render('hga/pdf_report.html.twig', [
            'data' => $data,
            'generatedAt' => new \DateTime(),
            'renderer' => 'chrome',
        ]);

        return $this->renderWithChrome($html);
    }

    private function renderWithChrome(string $html): string
    {
        $tmpDir = sys_get_temp_dir();
        $inputBase = tempnam($tmpDir, 'hga_html_');
        if (false === $inputBase) {
            throw new \RuntimeException('Failed to create temp file for HTML.');
        }

        $inputPath = $inputBase . '.html';
        $outputPath = $inputBase . '.pdf';
        @unlink($inputBase);

        file_put_contents($inputPath, $html);

        $scriptPath = $this->projectDir . '/scripts/render_pdf.js';
        $processArgs = null;
        $tempScript = null;

        if (file_exists($scriptPath)) {
            $processArgs = ['node', $scriptPath, $inputPath, $outputPath];
        } else {
            $tempBase = tempnam($tmpDir, 'hga_js_');
            if (false === $tempBase) {
                @unlink($inputPath);
                throw new \RuntimeException('Failed to create temp script for Chrome renderer.');
            }
            $tempScript = $tempBase . '.js';
            @unlink($tempBase);
            $script = <<<'JS'
const { pathToFileURL } = require('url');
const puppeteerModulePath = process.env.HGA_PUPPETEER_MODULE_PATH;
const puppeteer = puppeteerModulePath ? require(puppeteerModulePath) : require('puppeteer');

(async () => {
  const [inputPath, outputPath] = process.argv.slice(2);
  if (!inputPath || !outputPath) {
    console.error('Usage: render <input.html> <output.pdf>');
    process.exit(1);
  }
  const footerText = process.env.HGA_PDF_FOOTER_TEXT || '';
  const footerTemplate = `
    <div style="font-size:7pt; width:100%; text-align:center; border-top:1px solid #ddd; padding-top:4px; color:#666;">
      ${escapeHtml(footerText)} - Seite <span class="pageNumber"></span>
    </div>
  `;

  const browser = await puppeteer.launch({
    executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || undefined,
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--disable-crashpad',
      '--no-zygote',
      '--disable-features=Crashpad',
      '--crashpad-handler-pid=0',
      '--user-data-dir=/tmp/puppeteer',
      '--disable-gpu',
    ],
    env: {
      ...process.env,
      CHROME_HEADLESS: '1',
      BREAKPAD_DUMP_LOCATION: '/tmp',
    },
  });
  try {
    const page = await browser.newPage();
    const fileUrl = pathToFileURL(inputPath).toString();
    await page.goto(fileUrl, { waitUntil: 'networkidle0' });
    await page.pdf({
      path: outputPath,
      format: 'A4',
      displayHeaderFooter: true,
      headerTemplate: '<div></div>',
      footerTemplate,
      margin: {
        top: '80px',
        right: '25px',
        bottom: '40px',
        left: '25px',
      },
      printBackground: true,
      preferCSSPageSize: true,
    });
  } finally {
    await browser.close();
  }
})();

function escapeHtml(value) {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
JS;
            file_put_contents($tempScript, $script);
            $processArgs = ['node', $tempScript, $inputPath, $outputPath];
        }

        $process = new Process($processArgs, $this->projectDir);
        $nodePath = $this->projectDir . '/node_modules';
        $env = ['NODE_PATH' => $nodePath];
        $footerText = mb_trim(\sprintf(
            'Hausgeldabrechnung %s - %s %s',
            $data['year'] ?? '',
            $data['einheit']['nummer'] ?? '',
            $data['einheit']['beschreibung'] ?? ''
        ));
        $env['HGA_PDF_FOOTER_TEXT'] = $footerText;
        $puppeteerModule = $this->projectDir . '/node_modules/puppeteer';
        if (is_dir($puppeteerModule)) {
            $env['HGA_PUPPETEER_MODULE_PATH'] = $puppeteerModule;
        }
        if (getenv('HGA_NODE_PATH')) {
            $env['NODE_PATH'] = getenv('HGA_NODE_PATH') . \PATH_SEPARATOR . $nodePath;
        }
        if (getenv('HGA_PUPPETEER_MODULE_PATH')) {
            $env['HGA_PUPPETEER_MODULE_PATH'] = getenv('HGA_PUPPETEER_MODULE_PATH');
        }
        if (getenv('PUPPETEER_EXECUTABLE_PATH')) {
            $env['PUPPETEER_EXECUTABLE_PATH'] = getenv('PUPPETEER_EXECUTABLE_PATH');
        }
        if (getenv('PUPPETEER_CACHE_DIR')) {
            $env['PUPPETEER_CACHE_DIR'] = getenv('PUPPETEER_CACHE_DIR');
        }
        $process->setEnv($env);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            @unlink($inputPath);
            if ($tempScript) {
                @unlink($tempScript);
            }
            throw new \RuntimeException('Chrome PDF render failed: ' . $process->getErrorOutput());
        }

        $pdf = file_get_contents($outputPath);
        @unlink($inputPath);
        @unlink($outputPath);
        if ($tempScript) {
            @unlink($tempScript);
        }

        if (false === $pdf) {
            throw new \RuntimeException('Failed to read rendered PDF.');
        }

        return $pdf;
    }
}
