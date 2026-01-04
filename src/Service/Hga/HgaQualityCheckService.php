<?php

declare(strict_types=1);

namespace App\Service\Hga;

use App\Entity\Dokument;
use App\Repository\HgaQualityFeedbackRepository;
use App\Service\OllamaService;
use App\Service\AI\ClaudeProvider;
use Psr\Log\LoggerInterface;

/**
 * AI-powered quality checks for HGA documents.
 */
class HgaQualityCheckService
{
    public function __construct(
        private HgaQualityFeedbackRepository $feedbackRepository,
        private OllamaService $ollamaService,
        private ClaudeProvider $claudeProvider,
        private LoggerInterface $logger,
    ) {}

    /**
     * Run comprehensive quality checks on HGA document.
     *
     * @param Dokument $dokument           The HGA document to check
     * @param string   $provider           'ollama' or 'claude'
     * @param bool     $includeUserFeedback Whether to include recent user-reported issues in AI context
     *
     * @return array{
     *   status: 'pass'|'warning'|'critical',
     *   provider: string,
     *   processing_time: float,
     *   checks: array<array{
     *     category: string,
     *     severity: 'critical'|'high'|'medium'|'low',
     *     status: 'pass'|'fail'|'warning',
     *     message: string,
     *     details: array
     *   }>,
     *   ai_analysis: array{
     *     overall_assessment: string,
     *     confidence: float,
     *     issues_found: array,
     *     summary: string
     *   }|null,
     *   user_feedback_injected: int
     * }
     */
    public function runQualityChecks(
        Dokument $dokument,
        string $provider = 'ollama',
        bool $includeUserFeedback = true
    ): array {
        $startTime = microtime(true);

        // Get structured HGA data
        $hgaData = $dokument->getHgaData();
        if (!$hgaData) {
            throw new \InvalidArgumentException('Document has no HGA data');
        }

        // Run rule-based checks
        $checks = [];
        $checks = array_merge($checks, $this->checkDataCompleteness($hgaData));
        $checks = array_merge($checks, $this->checkCalculationPlausibility($hgaData));
        $checks = array_merge($checks, $this->checkCompliance($hgaData));

        // Determine overall status from rule-based checks
        $status = $this->determineOverallStatus($checks);

        // Get recent user feedback for AI context
        $userFeedback = $includeUserFeedback
            ? $this->feedbackRepository->getRecentIssues(10)
            : [];

        // Run AI analysis
        $aiAnalysis = $this->runAIAnalysis(
            $hgaData,
            $checks,
            $provider,
            $userFeedback
        );

        // Update status based on AI findings
        if ($aiAnalysis && $aiAnalysis['overall_assessment'] === 'critical') {
            $status = 'critical';
        } elseif ($aiAnalysis && $aiAnalysis['overall_assessment'] === 'warning' && $status === 'pass') {
            $status = 'warning';
        }

        $processingTime = microtime(true) - $startTime;

        return [
            'status' => $status,
            'provider' => $provider,
            'processing_time' => $processingTime,
            'checks' => $checks,
            'ai_analysis' => $aiAnalysis,
            'user_feedback_injected' => \count($userFeedback),
        ];
    }

    /**
     * Check data completeness (critical issues).
     */
    private function checkDataCompleteness(array $hgaData): array
    {
        $checks = [];

        // Check 1: External costs data
        $heatingShare = $hgaData['external_costs']['heating']['unit_share'] ?? 0.0;
        $waterShare = $hgaData['external_costs']['water']['unit_share'] ?? 0.0;

        if ($heatingShare === 0.0 && $waterShare === 0.0) {
            $checks[] = [
                'category' => 'data_completeness',
                'severity' => 'high',
                'status' => 'fail',
                'message' => 'Keine externen Kosten (Heizung/Wasser) vorhanden',
                'details' => [
                    'recommendation' => 'Bitte Heiz- und Wasserkostendaten nachtragen',
                ],
            ];
        }

        // Check 2: Payment data completeness
        $payments = $hgaData['payments'] ?? [];
        $paymentsIst = $payments['ist'] ?? 0.0;

        if ($paymentsIst === 0.0) {
            $checks[] = [
                'category' => 'data_completeness',
                'severity' => 'critical',
                'status' => 'fail',
                'message' => 'Keine Zahlungsdaten vorhanden (Ist = 0 EUR)',
                'details' => [
                    'recommendation' => 'Bitte Zahlungen erfassen',
                ],
            ];
        }

        // Check 3: Payment count plausibility for this single unit
        // (12 monthly payments + optional: 1 prior year settlement + up to 2 special assessments)
        $paymentsCount = $payments['count'] ?? 0;
        $year = $hgaData['year'] ?? null;

        if ($paymentsCount > 0 && $year) {
            // Expected for ONE unit: 12 monthly payments + optional settlements/special assessments
            $expectedMinPayments = 10; // At least 10 monthly payments (allow some missing)
            $expectedMaxPayments = 16; // 12 monthly + 1 settlement + up to 2 special assessments + buffer

            if ($paymentsCount < $expectedMinPayments) {
                $checks[] = [
                    'category' => 'data_completeness',
                    'severity' => 'medium',
                    'status' => 'warning',
                    'message' => sprintf(
                        'Zu wenige Zahlungen für diese Einheit: %d vorhanden, mindestens %d erwartet',
                        $paymentsCount,
                        $expectedMinPayments
                    ),
                    'details' => [
                        'actual_count' => $paymentsCount,
                        'expected_min' => $expectedMinPayments,
                        'expected_typical' => 12,
                        'year' => $year,
                        'recommendation' => sprintf(
                            'Erwartete Zahlungen für diese Einheit: 12 Monatszahlungen, plus eventuell Nachzahlungen/Sonderumlagen. Tatsächlich: %d Zahlungen gefunden.',
                            $paymentsCount
                        ),
                    ],
                ];
            } elseif ($paymentsCount > $expectedMaxPayments) {
                $checks[] = [
                    'category' => 'data_completeness',
                    'severity' => 'low',
                    'status' => 'warning',
                    'message' => sprintf(
                        'Ungewöhnlich viele Zahlungen für diese Einheit: %d vorhanden, normalerweise 12-16',
                        $paymentsCount
                    ),
                    'details' => [
                        'actual_count' => $paymentsCount,
                        'expected_max' => $expectedMaxPayments,
                        'expected_typical' => 12,
                        'year' => $year,
                        'recommendation' => 'Prüfen Sie ob Doppelbuchungen vorliegen oder Zahlungen aus anderen Jahren versehentlich zugeordnet wurden',
                    ],
                ];
            }
        }

        // Check 4: All checks passed
        if (empty($checks)) {
            $checks[] = [
                'category' => 'data_completeness',
                'severity' => 'low',
                'status' => 'pass',
                'message' => 'Datenqualität: Vollständig',
                'details' => [],
            ];
        }

        return $checks;
    }

    /**
     * Check calculation plausibility.
     */
    private function checkCalculationPlausibility(array $hgaData): array
    {
        $checks = [];

        // Check 1: Individual costs should be reasonable percentage of WEG total
        $einheitCosts = $hgaData['costs']['total'] ?? 0.0;
        $wegCosts = $hgaData['weg_totals']['gesamtkosten'] ?? 0.0;
        $meaPercentage = $hgaData['einheit']['mea'] ?? 0.0;

        if ($wegCosts > 0 && $meaPercentage > 0) {
            $actualPercentage = ($einheitCosts / $wegCosts) * 100;
            $deviation = abs($actualPercentage - $meaPercentage);

            // Allow 10% deviation for rounding and special distributions
            if ($deviation > 10) {
                $checks[] = [
                    'category' => 'calculation_plausibility',
                    'severity' => 'high',
                    'status' => 'fail',
                    'message' => sprintf(
                        'Kostenanteil unplausibel: %.1f%% statt erwartete %.1f%% (MEA-Anteil)',
                        $actualPercentage,
                        $meaPercentage
                    ),
                    'details' => [
                        'actual_percentage' => $actualPercentage,
                        'expected_percentage' => $meaPercentage,
                        'deviation' => $deviation,
                        'recommendation' => 'Prüfen Sie die Kostenverteilungsschlüssel und MEA-Anteile',
                    ],
                ];
            }
        }

        // Check 2: Tax deduction limits (§35a EStG: max €1,200/year)
        $taxDeduction = $hgaData['tax_deductible']['tax_reduction'] ?? 0.0;
        if ($taxDeduction > 1200) {
            $checks[] = [
                'category' => 'calculation_plausibility',
                'severity' => 'critical',
                'status' => 'fail',
                'message' => sprintf(
                    'Steuerermäßigung überschreitet gesetzliches Limit: %.2f EUR > 1.200 EUR',
                    $taxDeduction
                ),
                'details' => [
                    'amount' => $taxDeduction,
                    'legal_maximum' => 1200,
                    'recommendation' => 'Prüfen Sie die steuerlich absetzbaren Beträge',
                ],
            ];
        }

        // Check 3: Heating costs plausibility (suspiciously high)
        $heatingCosts = $hgaData['external_costs']['heating']['unit_share'] ?? 0.0;
        if ($heatingCosts > 5000) {
            $checks[] = [
                'category' => 'calculation_plausibility',
                'severity' => 'medium',
                'status' => 'warning',
                'message' => sprintf(
                    'Heizkosten ungewöhnlich hoch: %.2f EUR',
                    $heatingCosts
                ),
                'details' => [
                    'amount' => $heatingCosts,
                    'recommendation' => 'Bitte prüfen Sie die Heizkostenabrechnungsdaten',
                ],
            ];
        }

        if (empty($checks)) {
            $checks[] = [
                'category' => 'calculation_plausibility',
                'severity' => 'low',
                'status' => 'pass',
                'message' => 'Berechnungen: Plausibel',
                'details' => [],
            ];
        }

        return $checks;
    }

    /**
     * Check compliance (§35a, distribution keys, etc.).
     */
    private function checkCompliance(array $hgaData): array
    {
        $checks = [];

        // Check 1: Tax deductible amount should be ≤ 20% of eligible costs
        $taxDeductibleTotal = $hgaData['tax_deductible']['total'] ?? 0.0;
        $taxReduction = $hgaData['tax_deductible']['tax_reduction'] ?? 0.0;

        if ($taxDeductibleTotal > 0 && $taxReduction > 0) {
            $actualPercentage = ($taxReduction / $taxDeductibleTotal) * 100;

            // §35a: 20% of labor costs, so tax reduction should be ~20% of eligible costs
            if ($actualPercentage > 25) { // Allow small buffer
                $checks[] = [
                    'category' => 'compliance',
                    'severity' => 'medium',
                    'status' => 'warning',
                    'message' => sprintf(
                        'Steuerermäßigung scheint zu hoch: %.1f%% von absetzbaren Kosten',
                        $actualPercentage
                    ),
                    'details' => [
                        'percentage' => $actualPercentage,
                        'expected_max' => 20,
                        'recommendation' => 'Prüfen Sie die Berechnung der steuerlich absetzbaren Arbeitskosten',
                    ],
                ];
            }
        }

        if (empty($checks)) {
            $checks[] = [
                'category' => 'compliance',
                'severity' => 'low',
                'status' => 'pass',
                'message' => 'Compliance: OK',
                'details' => [],
            ];
        }

        return $checks;
    }

    /**
     * Run AI analysis using selected provider.
     *
     * @param array $hgaData      Structured HGA data
     * @param array $checks       Results from rule-based checks
     * @param string $provider    'ollama' or 'claude'
     * @param array $userFeedback Recent user-reported issues
     */
    private function runAIAnalysis(
        array $hgaData,
        array $checks,
        string $provider,
        array $userFeedback
    ): ?array {
        try {
            // Build AI prompt
            $prompt = $this->buildAIPrompt($hgaData, $checks, $userFeedback);

            // Log the prompt for debugging
            $this->logger->info('HGA Quality Check - AI Prompt', [
                'provider' => $provider,
                'prompt_length' => strlen($prompt),
                'prompt' => $prompt,
                'einheit' => $hgaData['einheit']['nummer'] ?? 'unknown',
                'year' => $hgaData['year'] ?? 'unknown',
            ]);

            // Ollama (local LLM)
            if ($provider === 'ollama') {
                $response = $this->ollamaService->analyzeHgaQuality($prompt);

                // Log the response
                $this->logger->info('HGA Quality Check - AI Response', [
                    'provider' => $provider,
                    'response' => $response,
                ]);

                return $response;
            }

            // Claude (Anthropic API)
            if ($provider === 'claude') {
                if (!$this->claudeProvider->isAvailable()) {
                    $this->logger->warning('Claude provider is not available (check AI_CLAUDE_ENABLED and ANTHROPIC_API_KEY)');
                    throw new \RuntimeException('Claude provider is not available. Please check your configuration.');
                }

                $response = $this->claudeProvider->analyzeHgaQuality($prompt);

                // Log the response
                $this->logger->info('HGA Quality Check - AI Response', [
                    'provider' => $provider,
                    'response' => $response,
                ]);

                return $response;
            }

            // Helpful error for typos
            if ($provider === 'olama') {
                $this->logger->warning('Provider name typo detected: "olama" should be "ollama"');
                throw new \InvalidArgumentException('Invalid provider "olama". Did you mean "ollama"?');
            }

            // Unknown provider
            throw new \InvalidArgumentException(sprintf('Unknown AI provider "%s". Supported: ollama, claude', $provider));
        } catch (\Exception $e) {
            $this->logger->error('AI analysis failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw in debug mode to see what's happening
            if ($_ENV['APP_DEBUG'] ?? false) {
                throw $e;
            }

            return null;
        }
    }

    /**
     * Build comprehensive AI prompt with context.
     */
    private function buildAIPrompt(array $hgaData, array $checks, array $userFeedback): string
    {
        // Extract key data with fallbacks for different HGA data structures
        $einheit = $hgaData['einheit'] ?? [];
        $costs = $hgaData['costs'] ?? [];
        $payments = $hgaData['payments'] ?? [];
        $externalCosts = $hgaData['external_costs'] ?? [];
        $taxDeductible = $hgaData['tax_deductible'] ?? [];
        $wegTotals = $hgaData['weg_totals'] ?? [];

        // Handle nested cost structure
        $gesamtkosten = $costs['gesamtkosten'] ?? $costs['total'] ?? 0.0;
        $umlagefaehig = $costs['umlagefaehig']['total'] ?? $costs['umlagefaehig'] ?? 0.0;
        $nichtUmlagefaehig = $costs['nicht_umlagefaehig']['total'] ?? $costs['nicht_umlagefaehig'] ?? 0.0;
        $ruecklagen = $costs['ruecklagen']['total'] ?? $costs['ruecklagenzufuehrung'] ?? 0.0;
        $year = $hgaData['year'] ?? date('Y');

        // Handle external costs (may not exist)
        $heatingTotal = $externalCosts['heating']['total'] ?? 0.0;
        $heatingUnitShare = $externalCosts['heating']['unit_share'] ?? 0.0;
        $waterTotal = $externalCosts['water']['total'] ?? 0.0;
        $waterUnitShare = $externalCosts['water']['unit_share'] ?? 0.0;

        // Handle tax deductible (may not exist)
        $taxDeductibleTotal = $taxDeductible['total'] ?? 0.0;
        $taxReduction = $taxDeductible['tax_reduction'] ?? 0.0;

        // Handle payments
        $paymentsSoll = $payments['soll'] ?? 0.0;
        $paymentsIst = $payments['ist'] ?? 0.0;
        $paymentsDifferenz = $payments['differenz'] ?? 0.0;
        $paymentsStatus = $payments['status'] ?? 'Unbekannt';

        // Handle einheit fields
        $einheitNummer = $einheit['nummer'] ?? 'N/A';
        $einheitBeschreibung = $einheit['beschreibung'] ?? 'N/A';
        $einheitEigentuemer = $einheit['eigentuemer'] ?? 'N/A';
        $einheitMea = $einheit['mea'] ?? 'N/A';

        // Build failed checks list
        $failedChecks = array_filter($checks, fn ($c) => $c['status'] !== 'pass');
        $failedChecksList = '';
        foreach ($failedChecks as $check) {
            $failedChecksList .= sprintf(
                "- [%s] %s: %s\n",
                strtoupper($check['severity']),
                $check['category'],
                $check['message']
            );
        }
        if (empty($failedChecksList)) {
            $failedChecksList = '(Keine automatisch erkannten Probleme)';
        }

        // Build user feedback context
        $userFeedbackContext = '';
        if (!empty($userFeedback)) {
            $userFeedbackContext = "\n\nWICHTIG - NUTZER HABEN DIESE FEHLER GEMELDET, DIE DU ERKENNEN SOLLST:\n\n";
            foreach ($userFeedback as $i => $feedback) {
                $userFeedbackContext .= sprintf(
                    "Beispiel %d: %s\n  → %s\n\n",
                    $i + 1,
                    $feedback->getUserDescription(),
                    $feedback->getUserFeedbackType() === 'false_negative'
                        ? 'Wurde nicht erkannt - bitte immer prüfen!'
                        : 'Neuer Check - bitte implementieren'
                );
            }
        }

        return <<<PROMPT
Analysiere diese Hausgeldabrechnung auf Fehler und Auffälligkeiten:{$userFeedbackContext}

EINHEIT:
- Nummer: {$einheitNummer}
- Beschreibung: {$einheitBeschreibung}
- Eigentümer: {$einheitEigentuemer}
- MEA-Anteil: {$einheitMea}

ABRECHNUNGSJAHR: {$year}

KOSTEN-ÜBERSICHT:
- Gesamtkosten Einheit: {$gesamtkosten} EUR
- Umlagefähig: {$umlagefaehig} EUR
- Nicht umlagefähig: {$nichtUmlagefaehig} EUR
- Rücklagenzuführung: {$ruecklagen} EUR

EXTERNE KOSTEN:
- Heizkosten gesamt: {$heatingTotal} EUR
- Heizkosten Einheit: {$heatingUnitShare} EUR
- Wasserkosten gesamt: {$waterTotal} EUR
- Wasserkosten Einheit: {$waterUnitShare} EUR

ZAHLUNGEN:
- Soll (Vorauszahlungen): {$paymentsSoll} EUR
- Ist (tatsächlich gezahlt): {$paymentsIst} EUR
- Differenz: {$paymentsDifferenz} EUR
- Status: {$paymentsStatus}

STEUERLICH ABSETZBAR (§35a EStG):
- Gesamt absetzbar: {$taxDeductibleTotal} EUR
- Steuerermäßigung: {$taxReduction} EUR

BEREITS ERKANNTE PROBLEME:
{$failedChecksList}

AUFGABE:
1. Prüfe die Plausibilität aller Beträge
2. Identifiziere ungewöhnliche Muster oder Anomalien
3. Bewerte die Vollständigkeit der Daten
4. Erkenne mögliche Berechnungsfehler
5. Gib konkrete Handlungsempfehlungen

WICHTIG:
- Heizkosten sollten typisch zwischen €800-€2000 pro Einheit/Jahr liegen
- Wasserkosten typisch €200-€600 pro Person/Jahr
- Steuerermäßigung maximal 20% der Arbeitskosten, max €1200/Jahr
- MEA-Kostenanteil sollte ungefähr dem MEA-Prozentsatz entsprechen

Antworte NUR mit gültigem JSON:
{
    "overall_assessment": "pass" | "warning" | "critical",
    "confidence": 0.0-1.0,
    "issues_found": [
        {
            "category": "data_completeness" | "calculation" | "pattern" | "compliance",
            "severity": "critical" | "high" | "medium" | "low",
            "issue": "Kurzbeschreibung",
            "details": "Ausführliche Erklärung",
            "recommendation": "Was sollte korrigiert werden"
        }
    ],
    "summary": "Zusammenfassung der Qualitätsprüfung in 2-3 Sätzen"
}
PROMPT;
    }

    /**
     * Get debug prompt for a document (without running AI analysis).
     * Useful for debugging and prompt engineering.
     */
    public function getDebugPrompt(Dokument $dokument): string
    {
        // Get structured HGA data
        $hgaData = $dokument->getHgaData();
        if (!$hgaData) {
            throw new \InvalidArgumentException('Document has no HGA data');
        }

        // Run rule-based checks
        $checks = [];
        $checks = array_merge($checks, $this->checkDataCompleteness($hgaData));
        $checks = array_merge($checks, $this->checkCalculationPlausibility($hgaData));
        $checks = array_merge($checks, $this->checkCompliance($hgaData));

        // Get recent user feedback
        $userFeedback = $this->feedbackRepository->getRecentIssues(10);

        // Build and return prompt
        return $this->buildAIPrompt($hgaData, $checks, $userFeedback);
    }

    /**
     * Determine overall status from check results.
     */
    private function determineOverallStatus(array $checks): string
    {
        $hasCritical = false;
        $hasHigh = false;
        $hasWarning = false;

        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                if ($check['severity'] === 'critical') {
                    $hasCritical = true;
                } elseif ($check['severity'] === 'high') {
                    $hasHigh = true;
                }
            } elseif ($check['status'] === 'warning') {
                $hasWarning = true;
            }
        }

        if ($hasCritical) {
            return 'critical';
        }
        if ($hasHigh || $hasWarning) {
            return 'warning';
        }

        return 'pass';
    }
}
