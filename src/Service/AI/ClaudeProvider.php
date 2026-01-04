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
    // Using Claude 3 Haiku (fast, affordable, widely available)
    // Alternative: claude-3-5-sonnet-20241022 (requires higher tier access)
    private const MODEL = 'claude-3-haiku-20240307';
    private const DEFAULT_TIMEOUT = 60; // Claude is usually fast
    // Haiku pricing: $0.25 per 1M input, $1.25 per 1M output
    private const COST_PER_1K_INPUT = 0.00025;
    private const COST_PER_1K_OUTPUT = 0.00125;

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

        return $this->generateText($prompt);
    }

    /**
     * Analyze HGA quality with Claude AI
     *
     * @param string $prompt The analysis prompt with complete HGA context
     *
     * @return array{
     *   overall_assessment: string,
     *   confidence: float,
     *   issues_found: array,
     *   summary: string
     * }
     */
    public function analyzeHgaQuality(string $prompt): array
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('ClaudeProvider is not available');
        }

        $response = $this->generateText($prompt, maxTokens: 2048);

        return $this->extractJson($response);
    }

    /**
     * Core text generation method
     */
    private function generateText(string $prompt, int $maxTokens = 2048, int $timeout = self::DEFAULT_TIMEOUT): string
    {
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
                    'max_tokens' => $maxTokens,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ],
                'timeout' => $timeout,
            ]);

            $data = $response->toArray();
            $answer = $data['content'][0]['text'] ?? '';

            $duration = microtime(true) - $startTime;

            $this->logger->info('Claude request completed', [
                'duration' => $duration,
                'input_tokens' => $data['usage']['input_tokens'] ?? 0,
                'output_tokens' => $data['usage']['output_tokens'] ?? 0,
                'cost_estimate' => $this->calculateCost($data['usage'] ?? []),
            ]);

            return $answer;
        } catch (\Exception $e) {
            // Try to get more details from the API response
            $errorDetails = $e->getMessage();
            $userMessage = 'Claude API request failed';

            if (method_exists($e, 'getResponse')) {
                try {
                    $responseBody = $e->getResponse()->getContent(false);
                    $errorData = json_decode($responseBody, true);

                    if (isset($errorData['error']['message'])) {
                        $apiError = $errorData['error']['message'];
                        $errorDetails .= ' | API Error: ' . $apiError;

                        // Provide helpful user messages for common errors
                        if (str_contains($apiError, 'credit balance')) {
                            $userMessage = 'Claude API: Insufficient credits. Please add credits at https://console.anthropic.com/settings/plans';
                        } elseif (str_contains($apiError, 'invalid api key')) {
                            $userMessage = 'Claude API: Invalid API key. Please check ANTHROPIC_API_KEY in .env.local';
                        } else {
                            $userMessage = 'Claude API: ' . $apiError;
                        }
                    } else {
                        $errorDetails .= ' | Response: ' . $responseBody;
                    }
                } catch (\Exception $ex) {
                    // Couldn't parse response body
                }
            }

            $this->logger->error('Claude request failed', [
                'error' => $errorDetails,
                'api_key_length' => strlen($this->apiKey),
                'model' => self::MODEL,
            ]);

            throw new \RuntimeException($userMessage, 0, $e);
        }
    }

    /**
     * Extract JSON from Claude response
     */
    private function extractJson(string $response): array
    {
        // Claude sometimes wraps JSON in markdown code blocks
        $response = preg_replace('/```json\s*|\s*```/', '', $response);
        $response = trim($response);

        try {
            return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // Fallback: try to find JSON in text
            if (preg_match('/\{.*\}/s', $response, $matches)) {
                try {
                    return json_decode($matches[0], true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e2) {
                    // Give up
                }
            }

            $this->logger->error('Could not extract JSON from Claude response', [
                'response' => substr($response, 0, 500),
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Could not extract JSON from Claude response: ' . $e->getMessage());
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
