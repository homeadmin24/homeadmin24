<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SystemConfigRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SystemConfigRepository::class)]
#[ORM\Table(name: 'system_config')]
#[ORM\UniqueConstraint(name: 'UNIQ_config_key', columns: ['config_key'])]
class SystemConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $configKey = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private mixed $configValue = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $category = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConfigKey(): ?string
    {
        return $this->configKey;
    }

    public function setConfigKey(string $configKey): static
    {
        $this->configKey = $configKey;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConfigValue(): ?array
    {
        return $this->configValue;
    }

    /**
     * @param array<string, mixed>|null $configValue
     */
    public function setConfigValue(?array $configValue): static
    {
        $this->configValue = $configValue;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get config value as string.
     */
    public function getValueAsString(): ?string
    {
        if (null === $this->configValue) {
            return null;
        }

        $encoded = json_encode($this->configValue);

        return false !== $encoded ? $encoded : null;
    }

    /**
     * Get config value as float.
     */
    public function getValueAsFloat(): ?float
    {
        if (null === $this->configValue) {
            return null;
        }

        if (is_numeric($this->configValue)) {
            return (float) $this->configValue;
        }

        // Try to extract numeric value from array
        if (\is_array($this->configValue)) {
            $firstValue = reset($this->configValue);
            if (is_numeric($firstValue)) {
                return (float) $firstValue;
            }
        }

        return null;
    }

    /**
     * Get config value as int.
     */
    public function getValueAsInt(): ?int
    {
        if (null === $this->configValue) {
            return null;
        }

        if (is_numeric($this->configValue)) {
            return (int) $this->configValue;
        }

        // Try to extract numeric value from array
        if (\is_array($this->configValue)) {
            $firstValue = reset($this->configValue);
            if (is_numeric($firstValue)) {
                return (int) $firstValue;
            }
        }

        return null;
    }
}
