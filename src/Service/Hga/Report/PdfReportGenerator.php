<?php

declare(strict_types=1);

namespace App\Service\Hga\Report;

use App\Entity\WegEinheit;
use App\Service\Hga\HgaServiceInterface;
use App\Service\Hga\ReportGeneratorInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * PDF report generator for HGA.
 *
 * Generates PDF reports using DomPDF and Twig templates, compatible with the new HGA service architecture.
 */
class PdfReportGenerator implements ReportGeneratorInterface
{
    public function __construct(
        private HgaServiceInterface $hgaService,
        private Environment $twig,
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
        ]);

        // Configure DomPDF (matching old implementation)
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('isPhpEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
