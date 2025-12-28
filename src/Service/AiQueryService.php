<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AiQueryResponse;
use App\Repository\AiQueryResponseRepository;
use App\Repository\KostenkontoRepository;
use App\Repository\ZahlungRepository;
use App\Repository\WegEinheitRepository;
use App\Service\AI\AIProviderInterface;
use App\Service\AI\ClaudeProvider;
use App\Service\AI\OllamaProvider;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for answering natural language financial queries using AI
 *
 * Examples:
 * - "Wie viel haben wir 2024 für Heizung ausgegeben?"
 * - "Hat Herr Müller alle Vorauszahlungen für 2024 bezahlt?"
 * - "Welche Kostenpositionen sind 2024 am stärksten gestiegen?"
 *
 * @see docs/technical/ai_integration_plan.md
 */
class AiQueryService
{
    public function __construct(
        private readonly OllamaProvider $ollamaProvider,
        private readonly ClaudeProvider $claudeProvider,
        private readonly EntityManagerInterface $entityManager,
        private readonly ZahlungRepository $zahlungRepository,
        private readonly KostenkontoRepository $kostenkontoRepository,
        private readonly WegEinheitRepository $wegEinheitRepository,
        private readonly AiQueryResponseRepository $responseRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Answer query using Ollama (local, DSGVO-compliant)
     */
    public function answerWithOllama(string $query): array
    {
        return $this->answerWithProvider($query, $this->ollamaProvider);
    }

    /**
     * Answer query using Claude (cloud API, dev-only)
     */
    public function answerWithClaude(string $query): array
    {
        return $this->answerWithProvider($query, $this->claudeProvider);
    }

    /**
     * Compare answers from both providers side-by-side
     */
    public function compareProviders(string $query): array
    {
        $context = $this->buildQueryContext($query);

        $results = [
            'query' => $query,
            'context_size' => count($context),
            'providers' => [],
        ];

        // Run both providers
        foreach ([$this->ollamaProvider, $this->claudeProvider] as $provider) {
            if (!$provider->isAvailable()) {
                $results['providers'][$provider->getProviderName()] = [
                    'available' => false,
                    'error' => 'Provider not available',
                ];
                continue;
            }

            try {
                $startTime = microtime(true);
                $answer = $provider->answerQuery($query, $context);
                $responseTime = microtime(true) - $startTime;

                $storedResponse = $this->storeResponse(
                    $query,
                    $context,
                    $provider->getProviderName(),
                    $answer,
                    $responseTime,
                    $provider->getEstimatedCost()
                );

                $results['providers'][$provider->getProviderName()] = [
                    'available' => true,
                    'answer' => $answer,
                    'response_time' => $responseTime,
                    'cost' => $provider->getEstimatedCost(),
                    'response_id' => $storedResponse->getId(),
                ];
            } catch (\Exception $e) {
                $results['providers'][$provider->getProviderName()] = [
                    'available' => true,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Answer with a specific provider
     */
    private function answerWithProvider(string $query, AIProviderInterface $provider): array
    {
        $this->logger->info('Processing AI query', [
            'query' => $query,
            'provider' => $provider->getProviderName(),
        ]);

        if (!$provider->isAvailable()) {
            return [
                'success' => false,
                'query' => $query,
                'error' => 'Provider ' . $provider->getProviderName() . ' is not available',
            ];
        }

        try {
            $context = $this->buildQueryContext($query);

            $this->logger->debug('AI query context', [
                'context_keys' => array_keys($context),
                'context_sample' => [
                    'type' => $context['type'] ?? null,
                    'year' => $context['year'] ?? null,
                    'payment_count' => $context['payments']['count'] ?? null,
                    'payment_total' => $context['payments']['total'] ?? null,
                ],
            ]);

            $startTime = microtime(true);
            $answer = $provider->answerQuery($query, $context);
            $responseTime = microtime(true) - $startTime;

            // Store response for learning
            $storedResponse = $this->storeResponse(
                $query,
                $context,
                $provider->getProviderName(),
                $answer,
                $responseTime,
                $provider->getEstimatedCost()
            );

            $this->logger->info('AI query answered successfully', [
                'provider' => $provider->getProviderName(),
                'answer_length' => \strlen($answer),
                'response_time' => $responseTime,
            ]);

            return [
                'success' => true,
                'query' => $query,
                'answer' => $answer,
                'provider' => $provider->getProviderName(),
                'response_time' => $responseTime,
                'cost' => $provider->getEstimatedCost(),
                'response_id' => $storedResponse->getId(),
            ];
        } catch (\Exception $e) {
            $this->logger->error('AI query failed', [
                'query' => $query,
                'provider' => $provider->getProviderName(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'query' => $query,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Store AI response for learning
     */
    private function storeResponse(
        string $query,
        array $context,
        string $provider,
        string $response,
        float $responseTime,
        float $cost
    ): AiQueryResponse {
        $aiResponse = new AiQueryResponse();
        $aiResponse->setQuery($query);
        $aiResponse->setContext($context);
        $aiResponse->setProvider($provider);
        $aiResponse->setResponse($response);
        $aiResponse->setResponseTime($responseTime);
        $aiResponse->setCost($cost);

        $this->entityManager->persist($aiResponse);
        $this->entityManager->flush();

        return $aiResponse;
    }

    /**
     * Rate a response (for learning)
     */
    public function rateResponse(int $responseId, string $rating): bool
    {
        $response = $this->responseRepository->find($responseId);

        if (!$response) {
            return false;
        }

        $response->setUserRating($rating);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Legacy method - defaults to Ollama
     *
     * @deprecated Use answerWithOllama() or answerWithClaude()
     */
    public function answerQuery(string $query): array
    {
        return $this->answerWithOllama($query);
    }

    /**
     * Build context data for the query based on intelligent analysis
     */
    private function buildQueryContext(string $query): array
    {
        $query = mb_strtolower($query);
        $context = [];

        // Detect year mentioned in query
        $year = $this->extractYear($query);

        // Check what type of query this is and fetch relevant data

        // 1. Cost/spending queries (Kosten, ausgegeben, bezahlt)
        if ($this->isAboutCosts($query)) {
            $context['type'] = 'cost_query';
            $context['year'] = $year;

            // Check if asking about specific cost category
            $kostenkontoNummern = $this->findMentionedKostenkonto($query);
            if ($kostenkontoNummern) {
                // Get payments for all matching Kostenkonto numbers
                $context['payments'] = $this->getPaymentsByKostenkontoNummern($kostenkontoNummern, $year);
                $context['kostenkonto_numbers'] = $kostenkontoNummern;
            } else {
                // General cost overview
                $context['all_costs'] = $this->getAllCostsByYear($year);
            }

            // Add previous year for comparison if available
            if ($year) {
                $context['previous_year_costs'] = $this->getAllCostsByYear($year - 1);
            }
        }

        // 2. Owner/payment status queries (Eigentümer, bezahlt, Vorauszahlung)
        if ($this->isAboutOwnerPayments($query)) {
            $context['type'] = 'owner_payment_query';
            $context['year'] = $year;

            // Try to extract owner name
            $ownerName = $this->extractOwnerName($query);
            if ($ownerName) {
                $einheit = $this->findEinheitByOwnerName($ownerName);
                if ($einheit) {
                    $context['einheit'] = [
                        'nummer' => $einheit->getEinheitsnummer(),
                        'eigentuemer' => $einheit->getMiteigentuemer(),
                        'mea' => $einheit->getMea(),
                    ];
                    $context['payments'] = $this->getOwnerPayments($einheit->getId(), $year);
                    $context['expected_payments'] = $this->calculateExpectedPayments($einheit, $year);
                }
            }
        }

        // 3. Cost increase/comparison queries (gestiegen, Steigerung, Vergleich)
        if ($this->isAboutCostIncreases($query)) {
            $context['type'] = 'cost_increase_query';
            $context['year'] = $year;
            $context['current_year_costs'] = $this->getAllCostsByYear($year);
            $context['previous_year_costs'] = $this->getAllCostsByYear($year - 1);
            $context['cost_comparison'] = $this->calculateCostComparison($year, $year - 1);
        }

        // 4. General statistics
        $context['available_kostenkonten'] = $this->getActiveKostenkonten();
        $context['query_date'] = (new \DateTime())->format('Y-m-d');

        return $context;
    }

    private function extractYear(string $query): ?int
    {
        // Look for 4-digit year
        if (preg_match('/\b(202[0-9])\b/', $query, $matches)) {
            return (int) $matches[1];
        }

        // Default to current year
        return (int) date('Y');
    }

    private function isAboutCosts(string $query): bool
    {
        $keywords = ['kosten', 'ausgaben', 'ausgegeben', 'bezahlt', 'betrag', 'euro', '€', 'kostet'];

        foreach ($keywords as $keyword) {
            if (str_contains($query, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function isAboutOwnerPayments(string $query): bool
    {
        $keywords = ['eigentümer', 'eigentuemer', 'herr', 'frau', 'vorauszahlung', 'hausgeld'];

        foreach ($keywords as $keyword) {
            if (str_contains($query, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function isAboutCostIncreases(string $query): bool
    {
        $keywords = ['gestiegen', 'steigerung', 'erhöht', 'vergleich', 'unterschied', 'mehr', 'teurer'];

        foreach ($keywords as $keyword) {
            if (str_contains($query, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function findMentionedKostenkonto(string $query): ?array
    {
        // Search keywords - flexible patterns that work with any Kostenkonto configuration
        $searchPatterns = [
            'heizung' => ['heiz', 'wärm', 'warmwasser', 'gas'],
            'gas' => ['gas'],
            'strom' => ['strom', 'elektr', 'beleucht'],
            'wasser' => ['wasser', 'trinkwasser'],
            'abwasser' => ['abwasser', 'kanal', 'entwässer'],
            'müll' => ['müll', 'abfall', 'entsorgun'],
            'hausmeister' => ['hausmeister', 'hauswart'],
            'versicherung' => ['versicher'],
            'verwaltung' => ['verwalt', 'verwalter'],
            'reparatur' => ['reparatur', 'repar'],
            'instandhaltung' => ['instandhalt', 'instand', 'wartung'],
            'reinigung' => ['reinig', 'putz'],
            'garten' => ['garten', 'grünfläch', 'pflege'],
        ];

        // Find which keyword was mentioned
        $matchedKeyword = null;
        foreach ($searchPatterns as $keyword => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($query, $pattern)) {
                    $matchedKeyword = $keyword;
                    break 2;
                }
            }
        }

        if (!$matchedKeyword) {
            return null;
        }

        // Search Kostenkontos by bezeichnung matching the patterns
        $patterns = $searchPatterns[$matchedKeyword];
        $allKostenkontos = $this->kostenkontoRepository->findBy(['isActive' => true]);

        $matchedNummern = [];
        foreach ($allKostenkontos as $konto) {
            $bezeichnung = mb_strtolower($konto->getBezeichnung());
            foreach ($patterns as $pattern) {
                if (str_contains($bezeichnung, $pattern)) {
                    $matchedNummern[] = $konto->getNummer();
                    break;
                }
            }
        }

        return !empty($matchedNummern) ? $matchedNummern : null;
    }

    private function extractOwnerName(string $query): ?string
    {
        // Look for name patterns: "Herr/Frau Nachname" or just "Nachname"
        if (preg_match('/(herr|frau)\s+([a-zäöüß]+)/i', $query, $matches)) {
            return $matches[2];
        }

        return null;
    }

    private function findEinheitByOwnerName(string $name): ?\App\Entity\WegEinheit
    {
        // Search for owner by name (fuzzy match)
        $allEinheiten = $this->wegEinheitRepository->findAll();

        foreach ($allEinheiten as $einheit) {
            $miteigentuemer = mb_strtolower($einheit->getMiteigentuemer());
            if (str_contains($miteigentuemer, mb_strtolower($name))) {
                return $einheit;
            }
        }

        return null;
    }

    private function getPaymentsByKostenkontoNummern(array $nummern, ?int $year): array
    {
        $qb = $this->zahlungRepository->createQueryBuilder('z');
        $qb->leftJoin('z.kostenkonto', 'k')
            ->where('k.nummer IN (:nummern)')
            ->setParameter('nummern', $nummern);

        if ($year) {
            $startDate = new \DateTime($year . '-01-01');
            $endDate = new \DateTime($year . '-12-31 23:59:59');
            $qb->andWhere('z.datum BETWEEN :startDate AND :endDate')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate);
        }

        $qb->orderBy('z.datum', 'ASC');

        $payments = $qb->getQuery()->getResult();

        $result = [];
        $total = 0.0;
        $categoryTotals = [];

        foreach ($payments as $payment) {
            $betrag = $payment->getBetrag();
            $total += abs($betrag); // Ausgaben sind negativ, wir wollen den absoluten Wert

            $kostenkonto = $payment->getKostenkonto();
            $kontoNummer = $kostenkonto ? $kostenkonto->getNummer() : 'Unbekannt';
            $kontoName = $kostenkonto ? $kostenkonto->getBezeichnung() : 'Unbekannt';

            if (!isset($categoryTotals[$kontoNummer])) {
                $categoryTotals[$kontoNummer] = [
                    'name' => $kontoName,
                    'total' => 0.0,
                    'count' => 0,
                ];
            }

            $categoryTotals[$kontoNummer]['total'] += abs($betrag);
            $categoryTotals[$kontoNummer]['count']++;

            $result[] = [
                'date' => $payment->getDatum()?->format('Y-m-d'),
                'bezeichnung' => $payment->getBezeichnung(),
                'partner' => $payment->getBuchungspartner(),
                'betrag' => $betrag,
                'kostenkonto' => $kontoNummer . ' - ' . $kontoName,
            ];
        }

        return [
            'total' => $total,
            'count' => count($result),
            'by_category' => $categoryTotals,
            'payments' => $result,
        ];
    }

    private function getPaymentsByKostenkonto(int $kostenkontoId, ?int $year): array
    {
        $qb = $this->zahlungRepository->createQueryBuilder('z');
        $qb->where('z.kostenkonto = :kostenkonto')
            ->setParameter('kostenkonto', $kostenkontoId);

        if ($year) {
            $startDate = new \DateTime($year . '-01-01');
            $endDate = new \DateTime($year . '-12-31 23:59:59');
            $qb->andWhere('z.datum BETWEEN :startDate AND :endDate')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate);
        }

        $qb->orderBy('z.datum', 'ASC');

        $payments = $qb->getQuery()->getResult();

        $result = [];
        $total = 0.0;

        foreach ($payments as $payment) {
            $betrag = $payment->getBetrag();
            $total += $betrag;

            $result[] = [
                'date' => $payment->getDatum()?->format('Y-m-d'),
                'bezeichnung' => $payment->getBezeichnung(),
                'partner' => $payment->getBuchungspartner(),
                'betrag' => $betrag,
            ];
        }

        return [
            'total' => $total,
            'count' => count($result),
            'payments' => $result,
        ];
    }

    private function getAllCostsByYear(?int $year): array
    {
        if (!$year) {
            return [];
        }

        $startDate = new \DateTime($year . '-01-01');
        $endDate = new \DateTime($year . '-12-31 23:59:59');

        $qb = $this->zahlungRepository->createQueryBuilder('z');
        $qb->select('k.nummer as kostenkonto_nummer, k.bezeichnung as kostenkonto_name, SUM(z.betrag) as total, COUNT(z.id) as count')
            ->leftJoin('z.kostenkonto', 'k')
            ->where('z.datum BETWEEN :startDate AND :endDate')
            ->andWhere('z.betrag < 0') // Only expenses (negative amounts)
            ->andWhere('k.nummer IS NOT NULL')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('k.id')
            ->orderBy('total', 'ASC'); // ASC because negative numbers

        $results = $qb->getQuery()->getResult();

        $costsByCategory = [];
        $grandTotal = 0.0;

        foreach ($results as $row) {
            $amount = abs((float) $row['total']); // Convert to positive
            $grandTotal += $amount;

            $costsByCategory[] = [
                'kostenkonto' => $row['kostenkonto_nummer'],
                'name' => $row['kostenkonto_name'],
                'total' => $amount,
                'count' => (int) $row['count'],
            ];
        }

        return [
            'year' => $year,
            'grand_total' => $grandTotal,
            'categories' => $costsByCategory,
        ];
    }

    private function getOwnerPayments(int $einheitId, ?int $year): array
    {
        $qb = $this->zahlungRepository->createQueryBuilder('z');
        $qb->where('z.eigentuemer = :einheit')
            ->setParameter('einheit', $einheitId);

        if ($year) {
            $startDate = new \DateTime($year . '-01-01');
            $endDate = new \DateTime($year . '-12-31 23:59:59');
            $qb->andWhere('z.datum BETWEEN :startDate AND :endDate')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate);
        }

        $qb->orderBy('z.datum', 'ASC');

        $payments = $qb->getQuery()->getResult();

        $result = [];
        $total = 0.0;

        foreach ($payments as $payment) {
            $betrag = $payment->getBetrag();
            $total += $betrag;

            $result[] = [
                'date' => $payment->getDatum()?->format('Y-m-d'),
                'bezeichnung' => $payment->getBezeichnung(),
                'betrag' => $betrag,
            ];
        }

        return [
            'total' => $total,
            'count' => count($result),
            'payments' => $result,
        ];
    }

    private function calculateExpectedPayments(\App\Entity\WegEinheit $einheit, ?int $year): array
    {
        // This is a simplified calculation
        // In a real system, you'd fetch the actual Vorauszahlung amount from configuration
        $monthlyAmount = 240.00; // Example: 240 EUR per month
        $expectedMonthly = $year ? 12 : 1;

        return [
            'monthly_amount' => $monthlyAmount,
            'months' => $expectedMonthly,
            'total_expected' => $monthlyAmount * $expectedMonthly,
        ];
    }

    private function calculateCostComparison(int $year1, int $year2): array
    {
        $costs1 = $this->getAllCostsByYear($year1);
        $costs2 = $this->getAllCostsByYear($year2);

        $comparison = [];

        foreach ($costs1['categories'] as $category1) {
            $kostenkonto = $category1['kostenkonto'];
            $current = $category1['total'];
            $previous = 0.0;

            // Find matching category in previous year
            foreach ($costs2['categories'] as $category2) {
                if ($category2['kostenkonto'] === $kostenkonto) {
                    $previous = $category2['total'];
                    break;
                }
            }

            $difference = $current - $previous;
            $percentage = $previous > 0 ? ($difference / $previous) * 100 : 0;

            $comparison[] = [
                'kostenkonto' => $kostenkonto,
                'name' => $category1['name'],
                'year1' => $year1,
                'year1_total' => $current,
                'year2' => $year2,
                'year2_total' => $previous,
                'difference' => $difference,
                'percentage_change' => $percentage,
            ];
        }

        // Sort by absolute difference (highest changes first)
        usort($comparison, function ($a, $b) {
            return abs($b['difference']) <=> abs($a['difference']);
        });

        return $comparison;
    }

    private function getActiveKostenkonten(): array
    {
        $konten = $this->kostenkontoRepository->findBy(['isActive' => true], ['nummer' => 'ASC']);

        $result = [];
        foreach ($konten as $konto) {
            $result[] = [
                'nummer' => $konto->getNummer(),
                'bezeichnung' => $konto->getBezeichnung(),
            ];
        }

        return $result;
    }
}
