<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Dienstleister;
use App\Entity\Dokument;
use App\Entity\Zahlung;
use App\Repository\DienstleisterRepository;
use App\Repository\ZahlungRepository;
use App\Repository\ZahlungskategorieRepository;
use Doctrine\ORM\EntityManagerInterface;

class BankStatementParsingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DienstleisterRepository $dienstleisterRepository,
        private ZahlungRepository $zahlungRepository,
        private ZahlungskategorieRepository $zahlungskategorieRepository,
        private ZahlungKategorisierungService $kategorisierungService,
        private string $projectDir,
    ) {
    }

    public function parseCSVPreview(Dokument $dokument): array
    {
        $filePath = $dokument->getAbsoluterPfad($this->projectDir);

        if (!file_exists($filePath)) {
            throw new \Exception('CSV-Datei nicht gefunden');
        }

        $transactions = $this->parseCSVFile($filePath);

        $newProviders = $this->getNewProviders($transactions);

        return [
            'totalCount' => \count($transactions),
            'dateFrom' => $this->getMinDate($transactions),
            'dateTo' => $this->getMaxDate($transactions),
            'incomeCount' => $this->countByType($transactions, 'income'),
            'incomeAmount' => $this->formatAmount($this->sumByType($transactions, 'income')),
            'expenseCount' => $this->countByType($transactions, 'expense'),
            'expenseAmount' => $this->formatAmount($this->sumByType($transactions, 'expense')),
            'newProvidersCount' => \count($newProviders),
            'newProviders' => $newProviders,
            'duplicateCount' => $this->countDuplicates($transactions),
            'previewTransactions' => $this->formatPreviewTransactions($transactions),
            'allTransactions' => $transactions,
        ];
    }

    public function importTransactions(Dokument $dokument, array $options = []): array
    {
        $importAll = 'all' === $options['import_mode'];
        $createProviders = $options['create_providers'] ?? true;

        error_log('Import starting - mode: ' . ($importAll ? 'all' : 'new-only') . ', create providers: ' . ($createProviders ? 'yes' : 'no'));

        $previewData = $this->parseCSVPreview($dokument);
        $transactions = $previewData['allTransactions'];

        // Reverse order to import latest entries first (so they get higher IDs)
        $transactions = array_reverse($transactions);

        error_log('Found ' . \count($transactions) . ' transactions to process');

        $imported = 0;
        $skipped = 0;
        $newProviders = 0;
        $categorized = 0;

        foreach ($transactions as $transaction) {
            error_log('Processing transaction: ' . $transaction['booking_date']->format('d.m.Y') . ' - ' . $transaction['partner'] . ' - ' . $transaction['amount']);

            // Skip duplicates if not importing all
            if (!$importAll && $this->isDuplicate($transaction)) {
                error_log('Skipping duplicate transaction');
                ++$skipped;
                continue;
            }

            // Create or find Dienstleister
            $dienstleister = $this->findOrCreateDienstleister($transaction['partner'], $createProviders);

            // If no partner (bank fees), assign to KSK bank
            if (!$dienstleister && empty($transaction['partner'])) {
                $dienstleister = $this->findOrCreateDienstleister('Kreissparkasse München Starnberg Ebersberg', true);
                error_log('Assigned bank fee to KSK bank');
            }

            if (!$dienstleister && $createProviders) {
                ++$newProviders;
                error_log('Created new provider: ' . $transaction['partner']);
            }

            // Create Zahlung
            try {
                $zahlung = $this->createZahlung($transaction, $dienstleister);

                // Auto-categorize
                if ($this->kategorisierungService->kategorisieren($zahlung)) {
                    ++$categorized;
                    error_log('Auto-categorized payment');
                }

                error_log('Created payment ID: ' . ($zahlung->getId() ?: 'pending'));
                ++$imported;
            } catch (\Exception $e) {
                error_log('Failed to create payment: ' . $e->getMessage());
            }
        }

        error_log("Before flush - imported: $imported, skipped: $skipped, newProviders: $newProviders");

        try {
            $this->entityManager->flush();
            error_log('After flush completed successfully');
        } catch (\Exception $e) {
            error_log('Flush failed: ' . $e->getMessage());
            error_log('Exception details: ' . $e->getFile() . ':' . $e->getLine());
            throw $e;
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'newProviders' => $newProviders,
            'categorized' => $categorized,
            'uncategorized' => $imported - $categorized,
        ];
    }

    private function parseCSVFile(string $filePath): array
    {
        $transactions = [];

        // Read file content and detect/convert encoding
        $content = file_get_contents($filePath);
        if (false === $content) {
            throw new \Exception('Fehler beim Lesen der CSV-Datei');
        }

        // Detect encoding and convert to UTF-8
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'CP1252'], true);
        if ($encoding && 'UTF-8' !== $encoding) {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        } elseif (!mb_check_encoding($content, 'UTF-8')) {
            // Fallback: assume ISO-8859-1 if detection fails
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }

        // Create temporary file with UTF-8 content
        $tempFile = tmpfile();
        if (!$tempFile) {
            throw new \Exception('Fehler beim Erstellen der temporären Datei');
        }

        fwrite($tempFile, $content);
        rewind($tempFile);

        // Skip header row
        $header = fgetcsv($tempFile, 0, ';');

        while (($row = fgetcsv($tempFile, 0, ';')) !== false) {
            if (\count($row) < 17) {
                continue; // Skip incomplete rows
            }

            $transactions[] = [
                'account' => trim($row[0], '"'),
                'booking_date' => $this->parseDate(trim($row[1], '"')),
                'value_date' => $this->parseDate(trim($row[2], '"')),
                'booking_text' => trim($row[3], '"'),
                'purpose' => trim($row[4], '"'),
                'creditor_id' => trim($row[5], '"'),
                'mandate_reference' => trim($row[6], '"'),
                'end_to_end_reference' => trim($row[7], '"'),
                'collector_reference' => trim($row[8], '"'),
                'original_amount' => trim($row[9], '"'),
                'charge_back_fee' => trim($row[10], '"'),
                'partner' => trim($row[11], '"'),
                'iban' => trim($row[12], '"'),
                'bic' => trim($row[13], '"'),
                'amount' => $this->parseAmount(trim($row[14], '"')),
                'currency' => trim($row[15], '"'),
                'info' => trim($row[16], '"'),
            ];
        }

        fclose($tempFile);

        return $transactions;
    }

    private function parseDate(string $dateString): \DateTime
    {
        // Handle DD.MM.YY format
        $date = \DateTime::createFromFormat('d.m.y', $dateString);
        if (!$date) {
            $date = \DateTime::createFromFormat('d.m.Y', $dateString);
        }

        if (!$date) {
            throw new \Exception("Ungültiges Datumsformat: {$dateString}");
        }

        return $date;
    }

    private function parseAmount(string $amountString): float
    {
        // Handle German number format (1.234,56 or -1.234,56)
        $cleanAmount = str_replace(['.', ','], ['', '.'], $amountString);

        return (float) $cleanAmount;
    }

    private function getMinDate(array $transactions): string
    {
        $dates = array_column($transactions, 'booking_date');
        usort($dates, fn ($a, $b) => $a <=> $b);

        return reset($dates)->format('d.m.Y');
    }

    private function getMaxDate(array $transactions): string
    {
        $dates = array_column($transactions, 'booking_date');
        usort($dates, fn ($a, $b) => $b <=> $a);

        return reset($dates)->format('d.m.Y');
    }

    private function countByType(array $transactions, string $type): int
    {
        return \count(array_filter($transactions, fn ($t) => ('income' === $type && $t['amount'] > 0)
            || ('expense' === $type && $t['amount'] < 0)
        ));
    }

    private function sumByType(array $transactions, string $type): float
    {
        return array_sum(array_map(
            fn ($t) => ('income' === $type && $t['amount'] > 0)
                     || ('expense' === $type && $t['amount'] < 0) ? $t['amount'] : 0,
            $transactions
        ));
    }

    private function countNewProviders(array $transactions): int
    {
        return \count($this->getNewProviders($transactions));
    }

    private function getNewProviders(array $transactions): array
    {
        $partners = array_unique(array_column($transactions, 'partner'));
        $newProviders = [];

        foreach ($partners as $partner) {
            if (empty($partner)) {
                continue;
            }

            // Try exact match first
            $existing = $this->dienstleisterRepository->findOneBy(['bezeichnung' => $partner]);

            // If no exact match, try fuzzy matching
            if (!$existing) {
                $existing = $this->findSimilarDienstleister($partner);
            }

            // If still not found, it's a new provider
            if (!$existing) {
                $newProviders[] = $partner;
            }
        }

        return $newProviders;
    }

    private function countDuplicates(array $transactions): int
    {
        $duplicates = 0;

        foreach ($transactions as $transaction) {
            if ($this->isDuplicate($transaction)) {
                ++$duplicates;
            }
        }

        return $duplicates;
    }

    private function isDuplicate(array $transaction): bool
    {
        // Format amount the same way as when creating payment (string with 2 decimals)
        $formattedAmount = number_format($transaction['amount'], 2, '.', '');

        // Normalize date to midnight for comparison (date column has no time component)
        $date = clone $transaction['booking_date'];
        $date->setTime(0, 0, 0);

        // Try exact match first (date + amount + purpose)
        $existing = $this->zahlungRepository->findOneBy([
            'datum' => $date,
            'betrag' => $formattedAmount,
            'bezeichnung' => $transaction['purpose'],
        ]);

        if ($existing) {
            return true;
        }

        // For generic booking texts (GUTSCHR, LASTSCHRIFT, etc.), also check by date + amount + partner
        // This catches duplicates where purpose field varies but the transaction is the same
        if (!empty($transaction['partner'])) {
            // Check for dienstleister match (expenses)
            $dienstleister = $this->dienstleisterRepository->findOneBy(['bezeichnung' => $transaction['partner']]);
            if (!$dienstleister) {
                $dienstleister = $this->findSimilarDienstleister($transaction['partner']);
            }

            if ($dienstleister) {
                $qb = $this->zahlungRepository->createQueryBuilder('z');
                $existing = $qb
                    ->where('z.datum = :datum')
                    ->andWhere('z.betrag = :betrag')
                    ->andWhere('z.dienstleister = :dienstleister')
                    ->setParameter('datum', $date)
                    ->setParameter('betrag', $formattedAmount)
                    ->setParameter('dienstleister', $dienstleister)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($existing) {
                    return true;
                }
            }

            // Check for eigentuemer match (income - Hausgeld payments)
            $eigentuemer = $this->findEigentuemer($transaction['partner']);
            if ($eigentuemer) {
                $qb = $this->zahlungRepository->createQueryBuilder('z');
                $existing = $qb
                    ->where('z.datum = :datum')
                    ->andWhere('z.betrag = :betrag')
                    ->andWhere('z.eigentuemer = :eigentuemer')
                    ->setParameter('datum', $date)
                    ->setParameter('betrag', $formattedAmount)
                    ->setParameter('eigentuemer', $eigentuemer)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($existing) {
                    return true;
                }
            }
        }

        return false;
    }

    private function formatPreviewTransactions(array $transactions): array
    {
        return array_map(function ($transaction) {
            $isIncome = $transaction['amount'] > 0;
            $isDuplicate = $this->isDuplicate($transaction);

            return [
                'date' => $transaction['booking_date']->format('d.m.Y'),
                'type' => $transaction['booking_text'],
                'typeClass' => $this->getTypeClass($transaction['booking_text']),
                'partner' => $transaction['partner'] ?: 'Unbekannt',
                'purpose' => mb_substr($transaction['purpose'], 0, 50) . (\mb_strlen($transaction['purpose']) > 50 ? '...' : ''),
                'amount' => $this->formatAmount($transaction['amount']),
                'amountClass' => $isIncome ? 'text-green-600' : 'text-red-600',
                'status' => $isDuplicate ? 'Duplikat' : 'Neu',
                'statusClass' => $isDuplicate ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800',
                'isDuplicate' => $isDuplicate,
            ];
        }, $transactions);
    }

    private function getTypeClass(string $bookingText): string
    {
        return match (true) {
            str_contains($bookingText, 'GUTSCHR') => 'bg-green-100 text-green-800',
            str_contains($bookingText, 'LASTSCHRIFT') => 'bg-red-100 text-red-800',
            str_contains($bookingText, 'DAUERAUFTRAG') => 'bg-blue-100 text-blue-800',
            str_contains($bookingText, 'UEBERWEISUNG') => 'bg-purple-100 text-purple-800',
            str_contains($bookingText, 'ENTGELT') => 'bg-orange-100 text-orange-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . ' €';
    }

    private function findOrCreateDienstleister(string $partnerName, bool $createNew): ?Dienstleister
    {
        if (empty($partnerName)) {
            return null;
        }

        // Try exact match first
        $dienstleister = $this->dienstleisterRepository->findOneBy(['bezeichnung' => $partnerName]);

        // If no exact match, try fuzzy matching
        if (!$dienstleister) {
            $dienstleister = $this->findSimilarDienstleister($partnerName);
        }

        // Create new if still not found and creation is enabled
        if (!$dienstleister && $createNew) {
            $dienstleister = new Dienstleister();
            $dienstleister->setBezeichnung($partnerName);
            $dienstleister->setArtDienstleister('Bank-Import');

            $this->entityManager->persist($dienstleister);
        }

        return $dienstleister;
    }

    private function findSimilarDienstleister(string $partnerName): ?Dienstleister
    {
        $allDienstleister = $this->dienstleisterRepository->findAll();
        $normalizedPartner = $this->normalizeString($partnerName);

        $bestMatch = null;
        $bestSimilarity = 0.0;

        foreach ($allDienstleister as $dienstleister) {
            $normalizedExisting = $this->normalizeString($dienstleister->getBezeichnung());

            // Calculate similarity
            $similarity = $this->calculateSimilarity($normalizedPartner, $normalizedExisting);

            if ($similarity > $bestSimilarity && $similarity >= 0.85) {
                $bestSimilarity = $similarity;
                $bestMatch = $dienstleister;
            }
        }

        return $bestMatch;
    }

    private function normalizeString(string $str): string
    {
        // Convert to lowercase
        $str = mb_strtolower($str, 'UTF-8');

        // Replace German umlauts and special characters
        $replacements = [
            'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        ];
        $str = str_replace(array_keys($replacements), array_values($replacements), $str);

        // Remove special characters except spaces, letters, and numbers
        $str = preg_replace('/[^a-z0-9\s]/', '', $str);

        // Remove extra whitespace
        $str = preg_replace('/\s+/', ' ', $str);
        $str = trim($str);

        return $str;
    }

    private function calculateSimilarity(string $str1, string $str2): float
    {
        // Use Levenshtein distance for similarity
        $maxLen = max(\mb_strlen($str1), \mb_strlen($str2));
        if (0 === $maxLen) {
            return 1.0;
        }

        $distance = levenshtein($str1, $str2);
        $similarity = 1.0 - ($distance / $maxLen);

        // Bonus for substring matches
        if (str_contains($str1, $str2) || str_contains($str2, $str1)) {
            $similarity = max($similarity, 0.9);
        }

        return $similarity;
    }

    /**
     * Find WegEinheit (property owner) by partner name.
     * Matches partner name against miteigentuemer field using fuzzy matching.
     */
    private function findEigentuemer(string $partnerName): ?\App\Entity\WegEinheit
    {
        $wegEinheitRepository = $this->entityManager->getRepository(\App\Entity\WegEinheit::class);
        $allEinheiten = $wegEinheitRepository->findAll();

        $normalizedPartner = $this->normalizeString($partnerName);

        $bestMatch = null;
        $bestScore = 0.0;

        foreach ($allEinheiten as $einheit) {
            $normalizedEigentuemer = $this->normalizeString($einheit->getMiteigentuemer());

            // Calculate similarity score
            $score = $this->calculateNameSimilarity($normalizedPartner, $normalizedEigentuemer);

            if ($score > $bestScore && $score >= 0.6) {
                $bestScore = $score;
                $bestMatch = $einheit;
            }
        }

        return $bestMatch;
    }

    /**
     * Calculate similarity between two names using word matching.
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
                continue; // Skip short words
            }

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

    private function createZahlung(array $transaction, ?Dienstleister $dienstleister): Zahlung
    {
        $zahlung = new Zahlung();
        $zahlung->setDatum($transaction['booking_date']);
        $zahlung->setBetrag(number_format($transaction['amount'], 2, '.', ''));
        $zahlung->setBezeichnung($transaction['purpose'] ?: $transaction['booking_text']);

        if ($dienstleister) {
            $zahlung->setDienstleister($dienstleister);
        }

        $this->entityManager->persist($zahlung);

        return $zahlung;
    }
}
