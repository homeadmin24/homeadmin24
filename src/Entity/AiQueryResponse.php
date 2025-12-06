<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AiQueryResponseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Stores AI query responses for learning and comparison
 *
 * Use cases:
 * - Compare Ollama vs Claude quality
 * - Export good Claude answers as training examples
 * - Track which provider users prefer
 * - A/B testing and metrics
 */
#[ORM\Entity(repositoryClass: AiQueryResponseRepository::class)]
#[ORM\Table(name: 'ai_query_response')]
#[ORM\Index(columns: ['provider'], name: 'idx_provider')]
#[ORM\Index(columns: ['user_rating'], name: 'idx_user_rating')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created_at')]
class AiQueryResponse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $query;

    #[ORM\Column(type: Types::JSON)]
    private array $context = [];

    #[ORM\Column(length: 20)]
    private string $provider; // 'ollama' or 'claude'

    #[ORM\Column(type: Types::TEXT)]
    private string $response;

    #[ORM\Column(type: Types::FLOAT)]
    private float $responseTime; // in seconds

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $cost = null; // in EUR

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $userRating = null; // 'good', 'bad', null

    #[ORM\Column]
    private bool $wasUsedForTraining = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function setQuery(string $query): static
    {
        $this->query = $query;

        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getResponse(): string
    {
        return $this->response;
    }

    public function setResponse(string $response): static
    {
        $this->response = $response;

        return $this;
    }

    public function getResponseTime(): float
    {
        return $this->responseTime;
    }

    public function setResponseTime(float $responseTime): static
    {
        $this->responseTime = $responseTime;

        return $this;
    }

    public function getCost(): ?float
    {
        return $this->cost;
    }

    public function setCost(?float $cost): static
    {
        $this->cost = $cost;

        return $this;
    }

    public function getUserRating(): ?string
    {
        return $this->userRating;
    }

    public function setUserRating(?string $userRating): static
    {
        $this->userRating = $userRating;

        return $this;
    }

    public function isWasUsedForTraining(): bool
    {
        return $this->wasUsedForTraining;
    }

    public function setWasUsedForTraining(bool $wasUsedForTraining): static
    {
        $this->wasUsedForTraining = $wasUsedForTraining;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
