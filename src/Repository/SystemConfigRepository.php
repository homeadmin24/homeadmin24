<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SystemConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SystemConfig>
 */
class SystemConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemConfig::class);
    }

    /**
     * Get configuration value by key.
     *
     * @return array<string, mixed>|null
     */
    public function getConfigValue(string $key): ?array
    {
        $config = $this->findOneBy(['configKey' => $key, 'isActive' => true]);

        return $config?->getConfigValue();
    }

    /**
     * Get configuration as float.
     */
    public function getConfigFloat(string $key): ?float
    {
        $config = $this->findOneBy(['configKey' => $key, 'isActive' => true]);

        return $config?->getValueAsFloat();
    }

    /**
     * Get configuration as string.
     */
    public function getConfigString(string $key): ?string
    {
        $config = $this->findOneBy(['configKey' => $key, 'isActive' => true]);

        return $config?->getValueAsString();
    }

    /**
     * Get configuration as int.
     */
    public function getConfigInt(string $key): ?int
    {
        $config = $this->findOneBy(['configKey' => $key, 'isActive' => true]);

        return $config?->getValueAsInt();
    }

    /**
     * Set configuration value.
     */
    public function setConfigValue(string $key, mixed $value, string $category = 'general', ?string $description = null): SystemConfig
    {
        $config = $this->findOneBy(['configKey' => $key]) ?? new SystemConfig();

        $config->setConfigKey($key)
            ->setConfigValue(\is_array($value) ? $value : [$value])
            ->setCategory($category)
            ->setIsActive(true);

        if ($description) {
            $config->setDescription($description);
        }

        $this->getEntityManager()->persist($config);
        $this->getEntityManager()->flush();

        return $config;
    }

    /**
     * Get all configs by category.
     *
     * @return SystemConfig[]
     */
    public function findByCategory(string $category): array
    {
        return $this->findBy(['category' => $category, 'isActive' => true], ['configKey' => 'ASC']);
    }

    /**
     * Check if config exists and is active.
     */
    public function configExists(string $key): bool
    {
        return null !== $this->findOneBy(['configKey' => $key, 'isActive' => true]);
    }
}
