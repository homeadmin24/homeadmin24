<?php

namespace App\Service\Parser;

use App\Entity\Dienstleister;
use App\Entity\Dokument;
use App\Entity\Rechnung;

class GenericRegexParser extends AbstractPdfParser
{
    /**
     * @var array<string, mixed>
     */
    private array $config;
    private Dienstleister $dienstleister;

    public function __construct(Dienstleister $dienstleister, ?string $projectDir = null)
    {
        parent::__construct($projectDir);
        $this->dienstleister = $dienstleister;
        $this->config = $dienstleister->getParserConfig() ?? [];
    }

    public function parse(Dokument $dokument): Rechnung
    {
        $pdfPath = $dokument->getAbsoluterPfad($this->projectDir);
        $text = $this->extractText($pdfPath);

        // Apply field mappings from configuration
        $extractedData = $this->applyFieldMappings($this->config, $text);

        // Create Rechnung entity
        $rechnung = new Rechnung();
        $rechnung->setDienstleister($this->dienstleister);
        $rechnung->setAusstehend(true);

        // Map extracted data to Rechnung fields
        if (isset($extractedData['rechnungsnummer'])) {
            $rechnung->setRechnungsnummer($extractedData['rechnungsnummer']);
        }

        if (isset($extractedData['betrag_mit_steuern'])) {
            $rechnung->setBetragMitSteuern($extractedData['betrag_mit_steuern']);
        }

        if (isset($extractedData['gesamt_mw_st'])) {
            $rechnung->setGesamtMwSt($extractedData['gesamt_mw_st']);
        }

        if (isset($extractedData['arbeits_fahrtkosten'])) {
            $rechnung->setArbeitsFahrtkosten($extractedData['arbeits_fahrtkosten']);
        }

        if (isset($extractedData['datum_leistung'])) {
            $rechnung->setDatumLeistung($extractedData['datum_leistung']);
        }

        if (isset($extractedData['faelligkeitsdatum'])) {
            $rechnung->setFaelligkeitsdatum($extractedData['faelligkeitsdatum']);
        }

        // Set information field
        if (isset($extractedData['information'])) {
            $rechnung->setInformation($extractedData['information']);
        } else {
            // Default information
            $rechnung->setInformation(\sprintf('%s - %s',
                $this->dienstleister->getBezeichnung(),
                $extractedData['rechnungsnummer'] ?? 'N/A'
            ));
        }

        // Validate required fields
        $requiredFields = $this->getRequiredFields();
        foreach ($requiredFields as $field) {
            if (!isset($extractedData[$field]) || empty($extractedData[$field])) {
                throw new \Exception("Required field missing: $field");
            }
        }

        return $rechnung;
    }

    public function getRequiredFields(): array
    {
        return $this->config['required_fields'] ?? parent::getRequiredFields();
    }
}
