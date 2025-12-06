<?php

declare(strict_types=1);

namespace App\Service\AI;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Claude AI Provider (Anthropic API)
 *
 * Uses Claude Sonnet for high-quality financial query responses.
 * Only enabled in development environment for learning and comparison.
 *
 * @see https://docs.anthropic.com/claude/reference/messages
 */
class ClaudeProvider implements AIProviderInterface
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-3-5-sonnet-20241022';
    private const DEFAULT_TIMEOUT = 60; // Claude is usually fast
    private const COST_PER_1K_INPUT = 0.003; // $3 per 1M input tokens
    private const COST_PER_1K_OUTPUT = 0.015; // $15 per 1M output tokens

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiKey = '',
        private readonly bool $enabled = false,
    ) {
    }

    public function answerQuery(string $query, array $context): string
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('ClaudeProvider is not available');
        }

        $prompt = $this->buildQueryPrompt($query, $context);

        $startTime = microtime(true);

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'max_tokens' => 2048,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ],
                'timeout' => self::DEFAULT_TIMEOUT,
            ]);

            $data = $response->toArray();
            $answer = $data['content'][0]['text'] ?? '';

            $duration = microtime(true) - $startTime;

            $this->logger->info('Claude query completed', [
                'duration' => $duration,
                'input_tokens' => $data['usage']['input_tokens'] ?? 0,
                'output_tokens' => $data['usage']['output_tokens'] ?? 0,
                'cost_estimate' => $this->calculateCost($data['usage'] ?? []),
            ]);

            return $answer;
        } catch (\Exception $e) {
            $this->logger->error('Claude request failed', [
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Claude request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getProviderName(): string
    {
        return 'claude';
    }

    public function isAvailable(): bool
    {
        return $this->enabled && !empty($this->apiKey);
    }

    public function getEstimatedCost(): float
    {
        // Average query: ~2000 input tokens + ~500 output tokens
        // = $0.006 + $0.0075 = ~$0.014 (~€0.013)
        return 0.013;
    }

    /**
     * Build prompt for Claude with financial data context
     */
    private function buildQueryPrompt(string $query, array $context): string
    {
        $contextStr = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Du bist ein Finanzassistent für WEG-Verwaltung (Wohnungseigentümergemeinschaft) in Deutschland.

AUFGABE:
Beantworte die folgende Frage präzise basierend auf den bereitgestellten Finanzdaten.

FRAGE DES BENUTZERS:
{$query}

VERFÜGBARE FINANZDATEN:
{$contextStr}

ANFORDERUNGEN:
1. **NUTZE DIE DATEN VOLLSTÄNDIG**:
   - Analysiere alle bereitgestellten Zahlen
   - Gib konkrete Beträge an (z.B. "5.234,50 €")
   - Zeige Aufschlüsselungen wenn relevant

2. **STRUKTURIERE DIE ANTWORT KLAR**:
   - Beginne mit der Hauptantwort (direkter Betrag)
   - Liste dann Details auf (Kategorien, Zeiträume)
   - Füge Kontext hinzu (Vergleiche, Trends) wenn verfügbar

3. **FORMATIERUNG**:
   - Geldbeträge: deutsches Format mit Tausenderpunkt und Komma (1.234,50 €)
   - Bullet Points: • für Aufzählungen
   - Prozente: mit % Zeichen
   - Absätze: für bessere Lesbarkeit

4. **FACHSPRACHE**:
   - Verwende WEG-Terminologie (Kostenkonto, Umlagefähig, etc.)
   - Erkläre komplexe Sachverhalte verständlich
   - Gib handlungsrelevante Hinweise

5. **EHRLICHKEIT**:
   - Wenn Daten fehlen, sage das klar
   - Keine Spekulationen oder Annahmen
   - Nur Fakten aus den bereitgestellten Daten

BEISPIEL GUTER ANTWORTEN:
"Im Jahr 2024 wurden insgesamt 12.345,67 € für Heizung ausgegeben.

Die Kosten teilen sich wie folgt auf:
• 043100 Gas: 8.500,00 € (69%)
• 041300 Wartung Heizung: 2.500,00 € (20%)
• 041400 Heizungs-Reparaturen: 1.345,67 € (11%)

Das entspricht einem Anstieg von 8% gegenüber 2023 (11.432,80 €). Der Hauptgrund ist die Preissteigerung bei Gas."

Antworte jetzt auf die Frage des Benutzers.
PROMPT;
    }

    /**
     * Calculate actual cost based on token usage
     */
    private function calculateCost(array $usage): float
    {
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;

        $inputCost = ($inputTokens / 1000) * self::COST_PER_1K_INPUT;
        $outputCost = ($outputTokens / 1000) * self::COST_PER_1K_OUTPUT;

        return $inputCost + $outputCost;
    }
}
