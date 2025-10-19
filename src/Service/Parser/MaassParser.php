<?php

namespace App\Service\Parser;

use App\Entity\Dokument;
use App\Entity\Rechnung;

class MaassParser extends AbstractPdfParser
{
    /**
     * Parse Maaß Gebäudemanagement invoice.
     */
    public function parse(Dokument $dokument): Rechnung
    {
        $pdfPath = $dokument->getAbsoluterPfad($this->projectDir);
        $text = $this->extractText($pdfPath);

        // Extract invoice data using specific patterns for Maaß invoices
        // The invoice number is on the line below the label, with lots of spaces
        $rechnungsnummer = $this->extractWithRegex($text, '/RECHNUNG\s+([0-9]+)/i');
        $gesamtbetrag = $this->extractWithRegex($text, '/Bruttobetrag\s+([0-9.,]+)\s*EUR/i');
        $nettobetrag = $this->extractWithRegex($text, '/Nettobetrag\s+([0-9.,]+)\s*EUR/i');
        $mwst = $this->extractWithRegex($text, '/19\s*%\s*MwSt\s+([0-9.,]+)\s*EUR/i');

        // Extract §35a EStG labor costs (more accurate than assuming 100% labor)
        $arbeitskostenAnteil = $this->extractWithRegex($text, '/Alle Leistungen\s+[0-9,]+\s*%\s+([0-9.,]+)\s*EUR/i');

        // Service period is also on the line below, after the date
        $leistungszeitraum = $this->extractWithRegex($text, '/[0-9]{2}\.[0-9]{2}\.[0-9]{4}\s+([0-9]+\/[0-9]{4})/');

        // Convert "1/2024" format to a proper date (end of the month)
        $datumLeistung = null;
        if ($leistungszeitraum) {
            if (preg_match('/([0-9]+)\/([0-9]{4})/', $leistungszeitraum, $matches)) {
                $month = (int) $matches[1];
                $year = (int) $matches[2];
                // Use the last day of the month as service date
                $datumLeistung = new \DateTime("$year-$month-01");
                $datumLeistung->modify('last day of this month');
            }
        }

        // Create Rechnung entity
        $rechnung = new Rechnung();
        $rechnung->setDienstleister($dokument->getDienstleister());
        $rechnung->setRechnungsnummer($rechnungsnummer);
        $rechnung->setAusstehend(true); // Default to outstanding

        // Set amounts
        if ($gesamtbetrag) {
            $parsedAmount = $this->parseGermanDecimal($gesamtbetrag);
            if (null !== $parsedAmount) {
                $rechnung->setBetragMitSteuern((string) $parsedAmount);
            }
        }

        if ($mwst) {
            $parsedMwSt = $this->parseGermanDecimal($mwst);
            if (null !== $parsedMwSt) {
                $rechnung->setGesamtMwSt((string) $parsedMwSt);
            }
        }

        // Set labor costs from §35a section if available, otherwise fallback to net amount
        if ($arbeitskostenAnteil) {
            $parsedLabor = $this->parseGermanDecimal($arbeitskostenAnteil);
            if (null !== $parsedLabor) {
                $rechnung->setArbeitsFahrtkosten((string) $parsedLabor);
            }
        } elseif ($nettobetrag) {
            // Fallback: assume 100% labor if no §35a section found
            $parsedNet = $this->parseGermanDecimal($nettobetrag);
            if (null !== $parsedNet) {
                $rechnung->setArbeitsFahrtkosten((string) $parsedNet);
            }
        }

        // Set dates
        if ($datumLeistung) {
            $rechnung->setDatumLeistung($datumLeistung);
        }

        // Set information field
        $info = \sprintf('Hausmeisterleistung %s',
            $leistungszeitraum ?: date('m/Y')
        );
        $rechnung->setInformation($info);

        // Validate required fields
        if (!$rechnungsnummer || !$gesamtbetrag) {
            throw new \Exception('Required fields missing: Rechnungsnummer or Gesamtbetrag');
        }

        return $rechnung;
    }

    /**
     * Check if this parser can handle Maaß invoices.
     */
    public function canParse(Dokument $dokument): bool
    {
        if (!parent::canParse($dokument)) {
            return false;
        }

        $dienstleister = $dokument->getDienstleister();

        return $dienstleister && (
            false !== mb_stripos($dienstleister->getBezeichnung(), 'maaß')
            || false !== mb_stripos($dienstleister->getBezeichnung(), 'maass')
        );
    }
}
