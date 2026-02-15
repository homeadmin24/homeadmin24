<?php

declare(strict_types=1);

namespace App\Service\AI;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client for an external Document Understanding service (OCR + LayoutLM).
 *
 * The service is expected to accept images and return structured invoice fields.
 */
class DocIntelProvider implements DocIntelInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $baseUrl = 'http://document-understanding:8000',
        private readonly bool $enabled = false,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->enabled && '' !== $this->baseUrl;
    }

    public function extractInvoiceDataFromImages(array $imagePaths, ?string $hintText = null, bool $debug = false): array
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('DocIntelProvider is not available');
        }

        $images = [];
        foreach ($imagePaths as $path) {
            if (!file_exists($path)) {
                throw new \InvalidArgumentException("Image file not found: {$path}");
            }

            $content = file_get_contents($path);
            if (false === $content) {
                throw new \RuntimeException("Failed to read image file: {$path}");
            }

            $images[] = [
                'filename' => basename($path),
                'content_type' => mime_content_type($path) ?: 'application/octet-stream',
                'data_base64' => base64_encode($content),
            ];
        }

        $payload = [
            'images' => $images,
            'hint_text' => $hintText,
            'output_schema' => 'invoice_v1',
            'debug' => $debug,
        ];

        try {
            $response = $this->httpClient->request('POST', mb_rtrim($this->baseUrl, '/') . '/api/invoice-extract', [
                'json' => $payload,
                'timeout' => 600,
            ]);

            $data = $response->toArray(false);
        } catch (\Exception $e) {
            $this->logger->error('DocIntelProvider request failed', [
                'error' => $e->getMessage(),
                'base_url' => $this->baseUrl,
            ]);

            throw new \RuntimeException('DocIntel request failed', 0, $e);
        }

        if (!isset($data['fields']) || !\is_array($data['fields'])) {
            $this->logger->warning('DocIntelProvider: unexpected response', [
                'response' => $data,
            ]);

            throw new \RuntimeException('DocIntel returned an invalid response');
        }

        return [
            'rechnungsnummer' => $data['fields']['rechnungsnummer'] ?? null,
            'betrag_mit_steuern' => $data['fields']['betrag_mit_steuern'] ?? null,
            'gesamt_mwst' => $data['fields']['gesamt_mwst'] ?? null,
            'datum_leistung' => $data['fields']['datum_leistung'] ?? null,
            'faelligkeitsdatum' => $data['fields']['faelligkeitsdatum'] ?? null,
            'arbeits_fahrtkosten' => $data['fields']['arbeits_fahrtkosten'] ?? null,
            'confidence' => (float) ($data['confidence'] ?? 0.0),
            'reasoning' => (string) ($data['reasoning'] ?? ''),
            'debug' => $data['debug'] ?? null,
        ];
    }
}
