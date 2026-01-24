<?php

declare(strict_types=1);

namespace App\Service\Parser;

use App\Entity\Dokument;
use App\Entity\Rechnung;
use App\Service\AI\ClaudeProvider;
use App\Service\AI\DocIntelProvider;
use App\Service\OllamaService;
use App\Service\PdfRenderService;
use Psr\Log\LoggerInterface;

/**
 * LLM-based invoice parser using Ollama or Claude.
 *
 * Uses AI to extract invoice data from PDF text when regex-based
 * parsing is not reliable or configured.
 *
 * Provider selection follows same pattern as HgaQualityCheckService:
 * - 'ollama': Local LLM (default, DSGVO-compliant)
 * - 'claude': Anthropic Claude API (higher accuracy, costs money)
 */
class LlmParser extends AbstractPdfParser
{
    private string $provider = 'ollama';

    public function __construct(
        private readonly OllamaService $ollamaService,
        private readonly ClaudeProvider $claudeProvider,
        private readonly DocIntelProvider $docIntelProvider,
        private readonly PdfRenderService $pdfRenderService,
        private readonly LoggerInterface $logger,
        ?string $projectDir = null,
    ) {
        parent::__construct($projectDir);
    }

    /**
     * Set the AI provider to use.
     *
     * @param string $provider 'ollama' or 'claude'
     */
    public function setProvider(string $provider): self
    {
        if (!\in_array($provider, ['ollama', 'claude'], true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid provider "%s". Supported: ollama, claude', $provider));
        }

        $this->provider = $provider;

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * {@inheritdoc}
     */
    public function parse(Dokument $dokument): Rechnung
    {
        $startTime = microtime(true);

        // Get the PDF file path
        $pdfPath = $this->getPdfPath($dokument);

        // Extract text from PDF
        $pdfText = $this->extractText($pdfPath);

        // Log extraction attempt
        $this->logger->info('LlmParser: Starting invoice extraction', [
            'dokument_id' => $dokument->getId(),
            'provider' => $this->provider,
            'text_length' => mb_strlen($pdfText),
        ]);

        $extractedData = [];
        $usedDocIntel = false;

        if (!empty(mb_trim($pdfText))) {
            $extractedData = $this->extractWithAI($pdfText);
        }

        if (!$this->hasRequiredFields($extractedData) && $this->docIntelProvider->isAvailable()) {
            $usedDocIntel = true;
            $imagePaths = $this->pdfRenderService->renderToImages($pdfPath);
            try {
                $extractedData = $this->docIntelProvider->extractInvoiceDataFromImages($imagePaths, $pdfText ?: null);
            } finally {
                $this->pdfRenderService->cleanup($imagePaths);
            }
        }

        if (empty($extractedData)) {
            $extractedData = [
                'rechnungsnummer' => null,
                'betrag_mit_steuern' => null,
                'gesamt_mwst' => null,
                'datum_leistung' => null,
                'faelligkeitsdatum' => null,
                'arbeits_fahrtkosten' => null,
                'confidence' => 0.0,
                'reasoning' => 'No extraction available',
            ];
        }

        $duration = microtime(true) - $startTime;

        // Log results
        $this->logger->info('LlmParser: Extraction completed', [
            'dokument_id' => $dokument->getId(),
            'provider' => $this->provider,
            'duration' => $duration,
            'confidence' => $extractedData['confidence'],
            'docintel_used' => $usedDocIntel,
            'extracted_fields' => array_keys(array_filter($extractedData, fn ($v) => null !== $v && 'confidence' !== $v && 'reasoning' !== $v)),
        ]);

        // Create Rechnung entity from extracted data
        return $this->createRechnung($dokument, $extractedData, $usedDocIntel);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredFields(): array
    {
        return ['rechnungsnummer', 'betrag_mit_steuern'];
    }

    /**
     * Check if this parser can handle the document.
     * LlmParser can handle any PDF invoice document.
     */
    public function canParse(Dokument $dokument): bool
    {
        // Check basic requirements from parent
        if (!parent::canParse($dokument)) {
            return false;
        }

        // Check if at least one AI provider is available
        return $this->isProviderAvailable();
    }

    /**
     * Check if the selected provider is available.
     */
    public function isProviderAvailable(): bool
    {
        if ('ollama' === $this->provider) {
            return $this->ollamaService->isEnabled() && $this->ollamaService->isOllamaAvailable();
        }

        if ('claude' === $this->provider) {
            return $this->claudeProvider->isAvailable();
        }

        return false;
    }

    /**
     * Extract invoice data using the selected AI provider.
     *
     * @return array{
     *   rechnungsnummer: ?string,
     *   betrag_mit_steuern: ?string,
     *   gesamt_mwst: ?string,
     *   datum_leistung: ?string,
     *   faelligkeitsdatum: ?string,
     *   arbeits_fahrtkosten: ?string,
     *   confidence: float,
     *   reasoning: string
     * }
     */
    private function extractWithAI(string $pdfText): array
    {
        try {
            if ('ollama' === $this->provider) {
                if (!$this->ollamaService->isEnabled()) {
                    throw new \RuntimeException('Ollama service is disabled');
                }

                return $this->ollamaService->extractInvoiceData($pdfText);
            }

            if ('claude' === $this->provider) {
                if (!$this->claudeProvider->isAvailable()) {
                    throw new \RuntimeException('Claude provider is not available');
                }

                return $this->claudeProvider->extractInvoiceData($pdfText);
            }

            throw new \InvalidArgumentException("Unknown provider: {$this->provider}");
        } catch (\Exception $e) {
            $this->logger->error('LlmParser: AI extraction failed', [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(\sprintf('AI extraction failed with %s: %s', $this->provider, $e->getMessage()), 0, $e);
        }
    }

    /**
     * Create Rechnung entity from extracted data.
     *
     * @param array<string, mixed> $data Extracted invoice data from AI
     */
    private function createRechnung(Dokument $dokument, array $data, bool $usedDocIntel): Rechnung
    {
        $rechnung = new Rechnung();

        // Set Dienstleister from document
        if ($dokument->getDienstleister()) {
            $rechnung->setDienstleister($dokument->getDienstleister());
        }

        // Set extracted fields
        if (!empty($data['rechnungsnummer'])) {
            $rechnung->setRechnungsnummer($data['rechnungsnummer']);
        }

        if (!empty($data['betrag_mit_steuern'])) {
            $rechnung->setBetragMitSteuern($this->normalizeDecimal($data['betrag_mit_steuern']));
        }

        if (!empty($data['gesamt_mwst'])) {
            $rechnung->setGesamtMwSt($this->normalizeDecimal($data['gesamt_mwst']));
        }

        if (!empty($data['datum_leistung'])) {
            $date = $this->parseGermanDate($data['datum_leistung']);
            if ($date) {
                $rechnung->setDatumLeistung($date);
            }
        }

        if (!empty($data['faelligkeitsdatum'])) {
            $date = $this->parseGermanDate($data['faelligkeitsdatum']);
            if ($date) {
                $rechnung->setFaelligkeitsdatum($date);
            }
        }

        if (!empty($data['arbeits_fahrtkosten'])) {
            $rechnung->setArbeitsFahrtkosten($this->normalizeDecimal($data['arbeits_fahrtkosten']));
        }

        // Add extraction metadata to information field
        $confidence = $data['confidence'] ?? 0.0;
        $reasoning = $data['reasoning'] ?? '';
        $sourceLabel = $usedDocIntel ? 'docintel' : $this->provider;
        $rechnung->setInformation(\sprintf(
            '[LLM/%s, Konfidenz: %.0f%%] %s',
            $sourceLabel,
            $confidence * 100,
            $reasoning
        ));

        // Always set ausstehend: true for low confidence, false for high confidence
        $rechnung->setAusstehend($confidence < 0.7);

        return $rechnung;
    }

    /**
     * Normalize decimal string from German format to DB format.
     *
     * Converts "1.234,56" to "1234.56"
     */
    private function normalizeDecimal(string $value): string
    {
        // Remove thousand separators (dots in German format)
        $value = str_replace('.', '', $value);
        // Replace decimal comma with dot
        $value = str_replace(',', '.', $value);

        return $value;
    }

    private function hasRequiredFields(array $data): bool
    {
        return !empty($data['rechnungsnummer']) && !empty($data['betrag_mit_steuern']);
    }

    /**
     * Get the PDF file path from document.
     */
    private function getPdfPath(Dokument $dokument): string
    {
        if (!$dokument->getDateipfad()) {
            throw new \InvalidArgumentException('Document has no file path');
        }

        // Use Dokument's method which knows the correct path (/data/dokumente/)
        $absolutePath = $dokument->getAbsoluterPfad($this->projectDir);

        if (!file_exists($absolutePath)) {
            throw new \InvalidArgumentException("PDF file not found: {$absolutePath}");
        }

        return $absolutePath;
    }
}
