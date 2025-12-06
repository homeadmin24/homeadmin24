<?php

declare(strict_types=1);

namespace App\Service\AI;

/**
 * Interface for AI providers (Ollama, Claude, GPT, etc.)
 *
 * Allows unified query handling across different AI backends
 */
interface AIProviderInterface
{
    /**
     * Answer a natural language query with provided context
     *
     * @param string $query User question
     * @param array $context Financial data context
     *
     * @return string AI-generated answer
     *
     * @throws \RuntimeException if provider is unavailable
     */
    public function answerQuery(string $query, array $context): string;

    /**
     * Get provider name for logging and display
     *
     * @return string Provider identifier (e.g., 'ollama', 'claude')
     */
    public function getProviderName(): string;

    /**
     * Check if provider is available and configured
     *
     * @return bool True if provider can handle requests
     */
    public function isAvailable(): bool;

    /**
     * Get estimated cost per query in EUR
     *
     * @return float Cost in euros (0.0 for local providers)
     */
    public function getEstimatedCost(): float;
}
