<?php

declare(strict_types=1);

namespace App\Service\AI;

use App\Service\OllamaService;

/**
 * Ollama AI Provider (Local LLM)
 *
 * Adapter for OllamaService to implement AIProviderInterface.
 * Uses local llama3.1:8b model for DSGVO-compliant queries.
 */
class OllamaProvider implements AIProviderInterface
{
    public function __construct(
        private readonly OllamaService $ollamaService,
    ) {
    }

    public function answerQuery(string $query, array $context): string
    {
        return $this->ollamaService->answerQuery($query, $context);
    }

    public function getProviderName(): string
    {
        return 'ollama';
    }

    public function isAvailable(): bool
    {
        return $this->ollamaService->isEnabled() && $this->ollamaService->isOllamaAvailable();
    }

    public function getEstimatedCost(): float
    {
        return 0.0; // Local, no cost
    }
}
