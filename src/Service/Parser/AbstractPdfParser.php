<?php

namespace App\Service\Parser;

use App\Entity\Dokument;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

abstract class AbstractPdfParser implements ParserInterface
{
    protected ?string $projectDir = null;

    public function __construct(?string $projectDir = null)
    {
        $this->projectDir = $projectDir;
    }

    /**
     * Default implementation - can be overridden.
     */
    public function canParse(Dokument $dokument): bool
    {
        return 'rechnungen' === $dokument->getKategorie()
            && null !== $dokument->getDienstleister()
            && 'application/pdf' === $dokument->getDateityp();
    }

    /**
     * Default required fields.
     */
    public function getRequiredFields(): array
    {
        return ['rechnungsnummer', 'betrag_mit_steuern'];
    }

    /**
     * Extract text from PDF using pdftotext command.
     */
    protected function extractText(string $pdfPath): string
    {
        // Check if file exists
        if (!file_exists($pdfPath)) {
            throw new \InvalidArgumentException("PDF file not found: $pdfPath");
        }

        // Use pdftotext command (usually available on most systems)
        $process = new Process(['pdftotext', '-layout', $pdfPath, '-']);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

    /**
     * Parse German decimal format (1.234,56) to float.
     */
    protected function parseGermanDecimal(?string $text): ?float
    {
        if (empty($text)) {
            return null;
        }

        // Remove any whitespace and currency symbols
        $text = preg_replace('/[€\s]/', '', $text);

        // Replace German decimal format with standard format
        $text = str_replace('.', '', $text); // Remove thousands separator
        $text = str_replace(',', '.', $text); // Replace decimal comma with dot

        return (float) $text;
    }

    /**
     * Parse German date format (dd.mm.yyyy) to DateTime.
     */
    protected function parseGermanDate(?string $text): ?\DateTime
    {
        if (empty($text)) {
            return null;
        }

        // Try different German date formats
        $formats = ['d.m.Y', 'd.m.y', 'd/m/Y', 'd-m-Y'];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, trim($text));
            if (false !== $date) {
                return $date;
            }
        }

        return null;
    }

    /**
     * Extract value using regex pattern.
     */
    protected function extractWithRegex(string $text, string $pattern): ?string
    {
        if (preg_match($pattern, $text, $matches)) {
            return isset($matches[1]) ? trim($matches[1]) : null;
        }

        return null;
    }

    /**
     * Apply field mappings from parser configuration.
     */
    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    protected function applyFieldMappings(array $config, string $text): array
    {
        $result = [];

        if (!isset($config['field_mappings'])) {
            return $result;
        }

        foreach ($config['field_mappings'] as $field => $mapping) {
            if (!isset($mapping['pattern'])) {
                continue;
            }

            $value = $this->extractWithRegex($text, $mapping['pattern']);

            if (null !== $value && isset($mapping['transform'])) {
                switch ($mapping['transform']) {
                    case 'german_decimal':
                        $value = $this->parseGermanDecimal($value);
                        break;
                    case 'date':
                        $value = $this->parseGermanDate($value);
                        break;
                }
            }

            $result[$field] = $value;
        }

        return $result;
    }
}
