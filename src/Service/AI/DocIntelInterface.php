<?php

declare(strict_types=1);

namespace App\Service\AI;

/**
 * Interface for vision-capable providers (image-aware extraction).
 */
interface DocIntelInterface
{
    /**
     * Check if provider is available and configured.
     */
    public function isAvailable(): bool;

    /**
     * Extract invoice data from image inputs.
     *
     * @param array<int, string> $imagePaths
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
    public function extractInvoiceDataFromImages(array $imagePaths, ?string $hintText = null): array;
}
