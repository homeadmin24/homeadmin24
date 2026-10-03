<?php

namespace App\Service\Parser;

use App\Entity\Dokument;
use App\Entity\Rechnung;

interface ParserInterface
{
    /**
     * Parse a PDF document and extract invoice data.
     *
     * @param Dokument $dokument The document to parse
     *
     * @throws \Exception if parsing fails
     *
     * @return Rechnung The created Rechnung entity (not persisted)
     */
    public function parse(Dokument $dokument): Rechnung;

    /**
     * Get required fields for this parser.
     *
     * @return array<string> List of required field names
     */
    public function getRequiredFields(): array;

    /**
     * Check if this parser can handle the given document.
     */
    public function canParse(Dokument $dokument): bool;
}
