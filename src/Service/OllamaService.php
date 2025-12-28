<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AiQueryResponseRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service for interacting with local Ollama LLM
 *
 * Ollama is an open-source tool to run large language models locally.
 * This service is only enabled in development environment.
 *
 * @see docs/core-system/ai_environment_configuration.md
 * @see docs/technical/ollama_learning_and_finetuning.md
 */
class OllamaService
{
    private const DEFAULT_TIMEOUT = 300; // 5 minutes for complex queries and model loading
    private const DEFAULT_TEMPERATURE = 0.1; // Low for factual extraction

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly AiQueryResponseRepository $responseRepository,
        private readonly string $ollamaUrl = 'http://ollama:11434',
        private readonly string $model = 'llama3.1:8b',
        private readonly bool $enabled = false,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Suggest Kostenkonto for payment categorization
     *
     * @param string $bezeichnung Payment description
     * @param string $partner Partner/service provider name
     * @param float $betrag Amount
     * @param array $historicalData Historical payments for context
     * @param array $learningExamples User corrections to learn from
     * @param array $availableKategorien Available cost accounts
     *
     * @return array{kostenkonto: string, confidence: float, reasoning: string}
     */
    public function suggestKostenkonto(
        string $bezeichnung,
        string $partner,
        float $betrag,
        array $historicalData = [],
        array $learningExamples = [],
        array $availableKategorien = [],
    ): array {
        if (!$this->enabled) {
            throw new \RuntimeException('OllamaService is disabled in this environment');
        }

        if (!$this->isOllamaAvailable()) {
            throw new \RuntimeException('Ollama service is not available at ' . $this->ollamaUrl);
        }

        $prompt = $this->buildCategorizationPrompt(
            $bezeichnung,
            $partner,
            $betrag,
            $historicalData,
            $learningExamples,
            $availableKategorien
        );

        $response = $this->generate($prompt);

        return $this->extractJson($response);
    }

    /**
     * Check if Ollama service is reachable
     */
    public function isOllamaAvailable(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $response = $this->httpClient->request('GET', $this->ollamaUrl . '/api/tags', [
                'timeout' => 2,
            ]);

            return 200 === $response->getStatusCode();
        } catch (\Exception $e) {
            $this->logger->warning('Ollama not available', [
                'url' => $this->ollamaUrl,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Core generation method
     */
    private function generate(
        string $prompt,
        array $options = [],
        int $timeout = self::DEFAULT_TIMEOUT
    ): string {
        $startTime = microtime(true);

        try {
            $response = $this->httpClient->request('POST', $this->ollamaUrl . '/api/generate', [
                'json' => [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => array_merge([
                        'temperature' => self::DEFAULT_TEMPERATURE,
                        'top_p' => 0.9,
                    ], $options),
                ],
                'timeout' => $timeout,
            ]);

            $data = $response->toArray();
            $result = $data['response'] ?? '';

            $duration = microtime(true) - $startTime;

            // Log slow requests
            if ($duration > 10) {
                $this->logger->warning('Slow Ollama request', [
                    'duration' => $duration,
                    'prompt_length' => \strlen($prompt),
                    'model' => $this->model,
                ]);
            }

            $this->logger->info('Ollama request completed', [
                'duration' => $duration,
                'model' => $this->model,
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Ollama request failed', [
                'error' => $e->getMessage(),
                'model' => $this->model,
                'url' => $this->ollamaUrl,
            ]);

            throw new \RuntimeException('Ollama request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Answer natural language query with context
     */
    public function answerQuery(string $query, array $context): string
    {
        if (!$this->enabled) {
            throw new \RuntimeException('OllamaService is disabled in this environment');
        }

        if (!$this->isOllamaAvailable()) {
            throw new \RuntimeException('Ollama service is not available at ' . $this->ollamaUrl);
        }

        $prompt = $this->buildQueryPrompt($query, $context);

        return $this->generate($prompt);
    }

    /**
     * Extract JSON from LLM response
     */
    private function extractJson(string $response): array
    {
        // LLMs sometimes wrap JSON in markdown code blocks
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

            $this->logger->error('Could not extract JSON from Ollama response', [
                'response' => substr($response, 0, 500),
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Could not extract JSON from LLM response: ' . $e->getMessage());
        }
    }

    /**
     * Build AI prompt for payment categorization
     */
    private function buildCategorizationPrompt(
        string $bezeichnung,
        string $partner,
        float $betrag,
        array $historicalData,
        array $learningExamples,
        array $kategorien,
    ): string {
        $historicalStr = $this->formatHistoricalData($historicalData);
        $learningStr = $this->formatLearningExamples($learningExamples);
        $kategorienStr = $this->formatKategorien($kategorien);

        return <<<PROMPT
Du bist ein Experte für deutsche WEG-Buchhaltung und Zahlungskategorisierung.

Analysiere diese Bankbuchung und ordne sie der passendsten Kostenkonto-Kategorie zu:

BUCHUNGSDETAILS:
- Bezeichnung/Verwendungszweck: "{$bezeichnung}"
- Buchungspartner: "{$partner}"
- Betrag: {$betrag} EUR

{$learningStr}

{$historicalStr}

VERFÜGBARE KOSTENKONTEN:
{$kategorienStr}

KONTEXT & REGELN:
1. **LERNMUSTER HABEN PRIORITÄT**: Korrigierte Muster sind wichtiger als generische Regeln
2. "Abschlag" oder "Vorauszahlung" deutet auf wiederkehrende Betriebskosten hin
3. Ähnliche Buchungspartner + ähnlicher Betrag + ähnlicher Rhythmus → gleiche Kategorie wie historisch
4. Stadtwerke können Gas, Strom oder Wasser sein → Prüfe Historik und Lernmuster
5. Versicherungen haben meist jährliche Zahlungen
6. Hausmeister-Leistungen sind meist monatlich oder pro Einsatz

AUFGABE:
Analysiere alle Faktoren (Text, Betrag, Historik, Lernmuster) und empfehle das beste Kostenkonto.
Bevorzuge Lernmuster über generische Regeln.

Antworte NUR mit gültigem JSON in diesem Format:
{
    "kostenkonto": "043100",
    "confidence": 0.95,
    "reasoning": "Detaillierte Begründung warum dieses Kostenkonto gewählt wurde"
}
PROMPT;
    }

    private function formatHistoricalData(array $historicalData): string
    {
        if (empty($historicalData)) {
            return '';
        }

        $str = "HISTORISCHE ZAHLUNGEN (ähnliche vergangene Buchungen):\n";
        foreach ($historicalData as $item) {
            $str .= sprintf(
                "- %s: \"%s\" bei \"%s\" (%.2f EUR) → %s\n",
                $item['date'] ?? '',
                $item['purpose'] ?? '',
                $item['partner'] ?? '',
                $item['amount'] ?? 0.0,
                $item['kategorie'] ?? ''
            );
        }

        return $str . "\n";
    }

    private function formatLearningExamples(array $learningExamples): string
    {
        if (empty($learningExamples)) {
            return '';
        }

        $str = "⭐ GELERNTE MUSTER (aus Benutzerkorrekturen):\n";
        foreach ($learningExamples as $example) {
            $correctionNote = isset($example['was_corrected_from'])
                ? " (korrigiert von {$example['was_corrected_from']})"
                : '';

            $str .= sprintf(
                "- \"%s\" bei \"%s\" (%.2f EUR) → %s%s\n",
                $example['bezeichnung'] ?? '',
                $example['partner'] ?? '',
                $example['betrag'] ?? 0.0,
                $example['kostenkonto'] . ' - ' . $example['bezeichnung_konto'],
                $correctionNote
            );
        }

        $str .= "\n⚠️ WICHTIG: Diese Muster wurden von Benutzern korrigiert und haben höchste Priorität!\n\n";

        return $str;
    }

    private function formatKategorien(array $kategorien): string
    {
        if (empty($kategorien)) {
            // Fallback: common cost accounts
            return <<<KATEGORIEN
040100 - Hausmeisterkosten
041100 - Schornsteinfeger
041300 - Wartung Heizung
042000 - Wasser
042200 - Abwasser
043000 - Allgemeinstrom
043100 - Gas
043200 - Müllentsorgung
044000 - Brandschutz
045100 - Instandhaltung
046000 - Gebäudeversicherung
049000 - Nebenkosten Geldverkehr
050000 - Verwaltervergütung
099900 - Hausgeld-Einnahmen
KATEGORIEN;
        }

        $str = '';
        foreach ($kategorien as $kategorie) {
            $str .= sprintf(
                "%s - %s\n",
                $kategorie['nummer'] ?? '',
                $kategorie['bezeichnung'] ?? ''
            );
        }

        return trim($str);
    }

    /**
     * Build prompt for natural language query with few-shot learning
     */
    private function buildQueryPrompt(string $query, array $context): string
    {
        $contextStr = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Get good Claude examples for few-shot learning
        $fewShotExamples = $this->getFewShotExamples(5);

        return <<<PROMPT
Du bist ein Finanzassistent für WEG-Verwaltung (Wohnungseigentümergemeinschaft).

WICHTIG: Du MUSST die bereitgestellten Daten verwenden. Wenn Daten vorhanden sind, analysiere sie gründlich und gib konkrete Zahlen an.

{$fewShotExamples}

AKTUELLE FRAGE DES BENUTZERS:
{$query}

VERFÜGBARE DATEN:
{$contextStr}

ANWEISUNGEN:
1. **LERNE VON DEN BEISPIELEN OBEN**: Nutze ähnlichen Stil und Struktur
2. **NUTZE DIE DATEN VOLLSTÄNDIG**: Wenn "payments" oder "by_category" vorhanden sind, MUSST du diese Zahlen verwenden
3. **SEI KONKRET**: Gib die Gesamtsumme an (z.B. "5.234,50 €"), nicht nur "es gibt Daten"
4. **STRUKTURIERE KLAR**:
   - Beginne mit der Hauptantwort (Gesamtsumme)
   - Liste dann Details auf (nach Kategorie, Monat, etc.)
   - Gib Kontext wenn relevant (Vergleich zu Vorjahr)
5. **FORMAT**:
   - Geldbeträge: "1.234,50 €" (deutsches Format mit Punkt als Tausendertrennzeichen)
   - Bullet Points: • für Aufzählungen
   - Absätze für bessere Lesbarkeit
6. **KEIN GESCHWAFEL**: Wenn keine Daten vorhanden sind, sage das klar. Wenn Daten da sind, nutze sie!

Antworte jetzt auf die Frage mit ähnlicher Qualität wie in den Beispielen.
PROMPT;
    }

    /**
     * Get good Claude examples for few-shot learning
     */
    private function getFewShotExamples(int $limit = 5): string
    {
        try {
            $examples = $this->responseRepository->getGoodClaudeExamples($limit);

            if (empty($examples)) {
                return ''; // No examples yet
            }

            $examplesStr = "LERNE VON DIESEN HOCHWERTIGEN BEISPIEL-ANTWORTEN:\n\n";

            foreach ($examples as $i => $example) {
                $examplesStr .= sprintf(
                    "BEISPIEL %d:\nFrage: %s\n\nGute Antwort:\n%s\n\n---\n\n",
                    $i + 1,
                    $example->getQuery(),
                    $example->getResponse()
                );
            }

            $this->logger->info('Injected few-shot examples into Ollama prompt', [
                'example_count' => count($examples),
            ]);

            return $examplesStr;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to load few-shot examples', [
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }
}
