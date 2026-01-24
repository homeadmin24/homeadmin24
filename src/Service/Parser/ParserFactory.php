<?php

declare(strict_types=1);

namespace App\Service\Parser;

use App\Entity\Dienstleister;
use App\Service\AI\ClaudeProvider;
use App\Service\AI\DocIntelProvider;
use App\Service\OllamaService;
use App\Service\PdfRenderService;
use Psr\Log\LoggerInterface;

/**
 * Factory for creating invoice parsers.
 *
 * Parser selection priority:
 * 1. parser_class field (custom class name)
 * 2. Hardcoded parsers by Dienstleister name
 * 3. parser_config JSON (regex-based)
 * 4. LLM parser as fallback (if no config and AI available)
 */
class ParserFactory
{
    public function __construct(
        private readonly string $projectDir,
        private readonly OllamaService $ollamaService,
        private readonly ClaudeProvider $claudeProvider,
        private readonly DocIntelProvider $docIntelProvider,
        private readonly PdfRenderService $pdfRenderService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Create appropriate parser for given Dienstleister.
     *
     * @param string $provider AI provider for LlmParser ('ollama' or 'claude')
     */
    public function createParser(Dienstleister $dienstleister, string $provider = 'ollama'): ParserInterface
    {
        // Priority 1: Check if custom parser class is specified
        if ($dienstleister->getParserClass()) {
            $parserClass = $dienstleister->getParserClass();

            // Special case: LlmParser specified
            if (LlmParser::class === $parserClass || 'LlmParser' === $parserClass) {
                return $this->createLlmParser($provider);
            }

            // Ensure class exists and implements ParserInterface
            if (!class_exists($parserClass)) {
                throw new \InvalidArgumentException("Parser class not found: $parserClass");
            }

            if (!\in_array(ParserInterface::class, class_implements($parserClass), true)) {
                throw new \InvalidArgumentException("Parser class must implement ParserInterface: $parserClass");
            }

            return new $parserClass($this->projectDir);
        }

        // Priority 2: Check for hardcoded parsers by Dienstleister name
        $bezeichnung = $dienstleister->getBezeichnung();
        if (false !== mb_stripos($bezeichnung, 'maaß') || false !== mb_stripos($bezeichnung, 'maass')) {
            return new MaassParser($this->projectDir);
        }

        // Priority 3: Check if parser_config is set (regex-based)
        if ($dienstleister->getParserConfig()) {
            return new GenericRegexParser($dienstleister, $this->projectDir);
        }

        // Priority 4: Fall back to LLM parser if AI is available
        $llmParser = $this->createLlmParser($provider);
        if ($llmParser->isProviderAvailable()) {
            $this->logger->info('ParserFactory: Using LlmParser as fallback', [
                'dienstleister' => $dienstleister->getBezeichnung(),
                'provider' => $provider,
            ]);

            return $llmParser;
        }

        // No parser available - return GenericRegexParser (will likely fail gracefully)
        $this->logger->warning('ParserFactory: No parser configured and LLM not available', [
            'dienstleister' => $dienstleister->getBezeichnung(),
        ]);

        return new GenericRegexParser($dienstleister, $this->projectDir);
    }

    /**
     * Create LLM parser with specified provider.
     */
    public function createLlmParser(string $provider = 'ollama'): LlmParser
    {
        $parser = new LlmParser(
            $this->ollamaService,
            $this->claudeProvider,
            $this->docIntelProvider,
            $this->pdfRenderService,
            $this->logger,
            $this->projectDir
        );

        return $parser->setProvider($provider);
    }

    /**
     * Check if LLM parsing is available.
     */
    public function isLlmAvailable(string $provider = 'ollama'): bool
    {
        return $this->createLlmParser($provider)->isProviderAvailable();
    }
}
