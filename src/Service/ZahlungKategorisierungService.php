<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Zahlung;
use App\Repository\KostenkontoRepository;
use App\Repository\ZahlungskategorieRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for auto-categorizing Zahlung entities based on business rules.
 *
 * This service can be used during CSV import or for bulk categorization.
 */
class ZahlungKategorisierungService
{
    public function __construct(
        private ZahlungskategorieRepository $zahlungskategorieRepository,
        private KostenkontoRepository $kostenkontoRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Attempt to auto-categorize a payment based on bezeichnung and dienstleister.
     * Returns true if categorization was successful, false otherwise.
     */
    public function kategorisieren(Zahlung $zahlung): bool
    {
        // Skip if already fully categorized (both fields set)
        if (null !== $zahlung->getHauptkategorie() && null !== $zahlung->getKostenkonto()) {
            return false;
        }

        $bezeichnung = mb_strtolower($zahlung->getBezeichnung() ?? '');
        $dienstleister = $zahlung->getDienstleister();
        $dienstleisterName = $dienstleister ? mb_strtolower($dienstleister->getBezeichnung()) : '';
        $dienstleisterArt = $dienstleister ? mb_strtolower($dienstleister->getArtDienstleister() ?? '') : '';

        // Try to find matching kategorie and kostenkonto
        $kategorie = $this->findKategorie($bezeichnung, $dienstleisterName, $dienstleisterArt);
        $kostenkonto = $this->findKostenkonto($bezeichnung, $dienstleisterName, $dienstleisterArt);

        // Set kategorie if missing
        if ($kategorie && !$zahlung->getHauptkategorie()) {
            $zahlung->setHauptkategorie($kategorie);
        } elseif (!$kategorie && $zahlung->getHauptkategorie()) {
            // Keep existing kategorie if we couldn't find a new one
            $kategorie = $zahlung->getHauptkategorie();
        }

        if ($kostenkonto) {
            // Skip if kostenkonto is not active
            if (!$kostenkonto->isActive()) {
                // Inactive kostenkonto - skip it but keep the kategorie
                $kostenkonto = null;
            } elseif ($kategorie && !$this->isKostenkontoAllowed($kategorie, $kostenkonto)) {
                // Kostenkonto not allowed for this kategorie - skip it but keep the kategorie
                $kostenkonto = null;
            } else {
                // Valid kostenkonto - assign it
                $zahlung->setKostenkonto($kostenkonto);
            }
        }

        // Auto-assign eigentuemer for Hausgeld payments
        if ($kategorie && 'Hausgeld-Zahlung' === $kategorie->getName() && $dienstleister && !$zahlung->getEigentuemer()) {
            $eigentuemer = $this->findEigentuemer($dienstleister->getBezeichnung());
            if ($eigentuemer) {
                $zahlung->setEigentuemer($eigentuemer);
            }
        }

        // Only count as "categorized" if BOTH kategorie and kostenkonto are set
        return null !== $kategorie && null !== $kostenkonto;
    }

    private function findKategorie(string $bezeichnung, string $dienstleisterName, string $dienstleisterArt): ?\App\Entity\Zahlungskategorie
    {
        // Expense patterns - Hausmeister (check BEFORE Hausgeld to avoid false matches)
        if (str_contains($bezeichnung, 'hausmeister') || str_contains($dienstleisterArt, 'hausmeister')) {
            return $this->zahlungskategorieRepository->findOneBy(['name' => 'Rechnung von Dienstleister']);
        }

        // Income patterns - Hausgeld/Wohngeld (from keywords OR from property owners)
        if ($this->isHausgeldIncome($bezeichnung) || str_contains($dienstleisterArt, 'eigentümer')) {
            return $this->zahlungskategorieRepository->findOneBy(['name' => 'Hausgeld-Zahlung']);
        }

        // Income patterns - Interest
        if ($this->isInterestIncome($bezeichnung)) {
            return $this->zahlungskategorieRepository->findOneBy(['name' => 'Zinserträge']);
        }

        // Bank account closure/transfer (check before refunds)
        if ($this->isBankAccountClosure($bezeichnung)) {
            return $this->zahlungskategorieRepository->findOneBy(['name' => 'Sonstige Einnahme']);
        }

        // Income patterns - Corrections/Refunds
        if ($this->isRefund($bezeichnung)) {
            return $this->zahlungskategorieRepository->findOneBy(['name' => 'Gutschrift Dienstleister']);
        }

        // Expense patterns - bank fees
        if ($this->isBankFee($bezeichnung)) {
            return $this->zahlungskategorieRepository->findOneBy(['name' => 'Bankgebühren']);
        }

        // Expense patterns - property management fees
        if ($this->isPropertyManagementFee($bezeichnung, $dienstleisterArt)) {
            return $this->zahlungskategorieRepository->findOneBy(['name' => 'Rechnung von Dienstleister']);
        }

        // Expense patterns - invoices from service providers
        if ($this->isServiceInvoice($bezeichnung, $dienstleisterArt)) {
            return $this->zahlungskategorieRepository->findOneBy(['name' => 'Rechnung von Dienstleister']);
        }

        // Default: no match
        return null;
    }

    private function findKostenkonto(string $bezeichnung, string $dienstleisterName, string $dienstleisterArt): ?\App\Entity\Kostenkonto
    {
        // Bank account closure/transfer
        if ($this->isBankAccountClosure($bezeichnung)) {
            return $this->kostenkontoRepository->findOneBy(['nummer' => '049100']); // Kontoübertragung / Kontoauflösung
        }

        // Hausmeister (check BEFORE Hausgeld to avoid false matches with generic WEG keywords)
        if (str_contains($bezeichnung, 'hausmeister') || str_contains($dienstleisterArt, 'hausmeister')) {
            return $this->kostenkontoRepository->findOneBy(['nummer' => '040100']); // Hausmeisterkosten
        }

        // Hausgeld/Wohngeld income (from keywords OR from property owners)
        if ($this->isHausgeldIncome($bezeichnung) || str_contains($dienstleisterArt, 'eigentümer')) {
            return $this->kostenkontoRepository->findOneBy(['nummer' => '099900']);
        }

        // Bank fees
        if ($this->isBankFee($bezeichnung)) {
            return $this->kostenkontoRepository->findOneBy(['nummer' => '049000']); // Nebenkosten Geldverkehr
        }

        // Property management fees
        if ($this->isPropertyManagementFee($bezeichnung, $dienstleisterArt)) {
            return $this->kostenkontoRepository->findOneBy(['nummer' => '050000']); // Verwaltervergütung
        }

        // Heating / Gas / Combined Gas+Water (check BEFORE plain water!)
        if (str_contains($bezeichnung, 'heizung') || str_contains($bezeichnung, 'gas') || str_contains($dienstleisterArt, 'heizung')) {
            return $this->kostenkontoRepository->findOneBy(['nummer' => '006000']); // Kosten für verbundene Heizungs- und Warmwasserversorgungsanlagen
        }

        // Abwasser/sewage (check before plain water)
        if (str_contains($bezeichnung, 'abwasser')
            || str_contains($dienstleisterName, 'stadtentwasserung')
            || str_contains($dienstleisterName, 'stadtentw')
            || str_contains($dienstleisterArt, 'abwasser')
            || str_contains($bezeichnung, 'kanalgebühr')) {
            return $this->kostenkontoRepository->findOneBy(['nummer' => '042200']); // Abwasser
        }

        // Water only (after gas and abwasser checks)
        if (str_contains($bezeichnung, 'wasser')) {
            return $this->kostenkontoRepository->findOneBy(['nummer' => '042000']); // Wasser allgemein
        }

        // Electricity
        if (str_contains($bezeichnung, 'strom') || str_contains($dienstleisterName, 'swm')) {
            return $this->kostenkontoRepository->findOneBy(['nummer' => '043000']); // Allgemeinstrom
        }

        // Waste management
        if (str_contains($bezeichnung, 'abfall') || str_contains($bezeichnung, 'müll') || str_contains($dienstleisterName, 'awm')) {
            return $this->kostenkontoRepository->findOneBy(['nummer' => '043200']); // Müllentsorgung
        }

        // Insurance
        if (str_contains($bezeichnung, 'versicherung') || str_contains($dienstleisterArt, 'versicherung')) {
            return $this->kostenkontoRepository->findOneBy(['nummer' => '013000']); // Beiträge für die Sach- und Haftpflichtversicherung
        }

        // Brandschutz (Fire Protection)
        if (str_contains($bezeichnung, 'brandschutz') || str_contains($dienstleisterArt, 'brandschutz')) {
            return $this->kostenkontoRepository->findOneBy(['nummer' => '044000']); // Brandschutz
        }

        // Sun protection / Blinds / Windows (Sonnenschutz)
        if (str_contains($bezeichnung, 'sonnenschutz') || str_contains($dienstleisterName, 'sonnenschutz')) {
            return $this->kostenkontoRepository->findOneBy(['nummer' => '045100']); // Laufende Instandhaltung
        }

        // Measurement devices / Safety equipment testing (Messgeräte)
        if (str_contains($dienstleisterArt, 'messgeräte') || str_contains($dienstleisterArt, 'messgerat')) {
            return $this->kostenkontoRepository->findOneBy(['nummer' => '045100']); // Laufende Instandhaltung
        }

        // Heating maintenance/repair invoices
        if (str_contains($dienstleisterArt, 'heizung')) {
            return $this->kostenkontoRepository->findOneBy(['nummer' => '041300']); // Wartung Heizung
        }

        return null;
    }

    private function isHausgeldIncome(string $bezeichnung): bool
    {
        $keywords = ['wohngeld', 'hausgeld', 'nachzahlung'];

        foreach ($keywords as $keyword) {
            if (str_contains($bezeichnung, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function isPropertyManagementFee(string $bezeichnung, string $dienstleisterArt): bool
    {
        // Check for Hausverwaltung in bezeichnung or art
        if (str_contains($bezeichnung, 'hausverwaltung') || str_contains($dienstleisterArt, 'hausverwaltung')) {
            return true;
        }

        return false;
    }

    private function isInterestIncome(string $bezeichnung): bool
    {
        return str_contains($bezeichnung, 'habenzins');
    }

    private function isBankAccountClosure(string $bezeichnung): bool
    {
        // Detect bank account closures/transfers (not actual income)
        return str_contains($bezeichnung, 'kontoauflösung')
               || (str_contains($bezeichnung, 'ausgleich des kontosaldos') && str_contains($bezeichnung, 'konto'));
    }

    private function isRefund(string $bezeichnung): bool
    {
        $keywords = ['gutschr', 'rückerstattung'];

        foreach ($keywords as $keyword) {
            if (str_contains($bezeichnung, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function isBankFee(string $bezeichnung): bool
    {
        $feeKeywords = ['pauschalen', 'entgelte', 'porto', 'kapitalertragsteuer', 'solidaritaetszuschlag'];

        foreach ($feeKeywords as $keyword) {
            if (str_contains($bezeichnung, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function isServiceInvoice(string $bezeichnung, string $dienstleisterArt): bool
    {
        // Check for invoice patterns
        $invoicePatterns = [
            'rg nr', 'rechnung', 're-', 'rn.', 'inv-', 'abschlag',
            'vertragskontonummer', 'datum', 'betrag', 'eur', 'zb ', 'faellig',
        ];

        foreach ($invoicePatterns as $pattern) {
            if (str_contains($bezeichnung, $pattern)) {
                return true;
            }
        }

        // Check for service types that usually send invoices
        $serviceTypes = ['hausmeister', 'heizung', 'sanitär', 'energie', 'müll', 'versicherung', 'abwasser'];

        foreach ($serviceTypes as $type) {
            if (str_contains($dienstleisterArt, $type)) {
                return true;
            }
        }

        return false;
    }

    private function isKostenkontoAllowed(\App\Entity\Zahlungskategorie $kategorie, \App\Entity\Kostenkonto $kostenkonto): bool
    {
        $fieldConfig = $kategorie->getFieldConfig();
        $allowedKostenkontoIds = $fieldConfig['kostenkonto_filter'] ?? [];

        // If no filter is set, all kostenkonto are allowed
        if (empty($allowedKostenkontoIds)) {
            return true;
        }

        // Check if the kostenkonto ID is in the allowed list
        return \in_array($kostenkonto->getId(), $allowedKostenkontoIds, true);
    }

    /**
     * Find WegEinheit (property owner) by dienstleister name.
     * Matches dienstleister name against miteigentuemer field using fuzzy matching.
     */
    private function findEigentuemer(string $dienstleisterName): ?\App\Entity\WegEinheit
    {
        $wegEinheitRepository = $this->entityManager->getRepository(\App\Entity\WegEinheit::class);
        $allEinheiten = $wegEinheitRepository->findAll();

        $normalizedDienstleister = $this->normalizeString($dienstleisterName);

        $bestMatch = null;
        $bestScore = 0.0;

        foreach ($allEinheiten as $einheit) {
            $normalizedEigentuemer = $this->normalizeString($einheit->getMiteigentuemer());

            // Calculate similarity score
            $score = $this->calculateNameSimilarity($normalizedDienstleister, $normalizedEigentuemer);

            if ($score > $bestScore && $score >= 0.6) {
                $bestScore = $score;
                $bestMatch = $einheit;
            }
        }

        return $bestMatch;
    }

    /**
     * Normalize string for comparison (lowercase, remove special chars).
     */
    private function normalizeString(string $str): string
    {
        $str = mb_strtolower($str, 'UTF-8');

        // Replace German umlauts
        $replacements = [
            'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        ];
        $str = str_replace(array_keys($replacements), array_values($replacements), $str);

        // Remove special characters except spaces
        $str = preg_replace('/[^a-z0-9\s]/', '', $str);

        // Normalize whitespace
        $str = preg_replace('/\s+/', ' ', $str);

        return trim($str);
    }

    /**
     * Calculate similarity between two names.
     * Uses minimum word count to avoid penalizing shorter names.
     */
    private function calculateNameSimilarity(string $name1, string $name2): float
    {
        // Split into words
        $words1 = explode(' ', $name1);
        $words2 = explode(' ', $name2);

        $matchCount = 0;

        // Check how many words from name1 appear in name2
        foreach ($words1 as $word1) {
            if (\mb_strlen($word1) < 3) {
                continue;
            } // Skip short words

            foreach ($words2 as $word2) {
                if (\mb_strlen($word2) < 3) {
                    continue;
                }

                // Exact match or substring match
                if ($word1 === $word2 || str_contains($word2, $word1) || str_contains($word1, $word2)) {
                    ++$matchCount;
                    break;
                }
            }
        }

        // Calculate score based on MINIMUM word count to handle single vs. multiple owner names
        $minWords = min(\count($words1), \count($words2));

        return $minWords > 0 ? $matchCount / $minWords : 0.0;
    }
}
