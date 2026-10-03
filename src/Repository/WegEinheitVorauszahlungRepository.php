<?php

namespace App\Repository;

use App\Entity\WegEinheit;
use App\Entity\WegEinheitVorauszahlung;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WegEinheitVorauszahlung>
 */
class WegEinheitVorauszahlungRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WegEinheitVorauszahlung::class);
    }

    public function save(WegEinheitVorauszahlung $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WegEinheitVorauszahlung $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find configuration for a specific unit and year.
     */
    public function findByWegEinheitAndYear(WegEinheit $wegEinheit, int $year): ?WegEinheitVorauszahlung
    {
        return $this->findOneBy([
            'wegEinheit' => $wegEinheit,
            'year' => $year,
            'isActive' => true,
        ]);
    }

    /**
     * Find all configurations for a specific year.
     *
     * @return WegEinheitVorauszahlung[]
     */
    public function findByYear(int $year): array
    {
        return $this->findBy([
            'year' => $year,
            'isActive' => true,
        ]);
    }

    /**
     * Find configurations for all units in a WEG for a specific year.
     *
     * @return WegEinheitVorauszahlung[]
     */
    public function findByWegAndYear(int $wegId, int $year): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.wegEinheit', 'e')
            ->where('e.weg = :wegId')
            ->andWhere('p.year = :year')
            ->andWhere('p.isActive = true')
            ->setParameter('wegId', $wegId)
            ->setParameter('year', $year)
            ->orderBy('e.nummer', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get monthly amount for a unit and year.
     * Returns null if no configuration found (no hardcoded fallback).
     */
    public function getMonthlyAmount(WegEinheit $wegEinheit, int $year): ?float
    {
        $config = $this->findByWegEinheitAndYear($wegEinheit, $year);

        return $config ? $config->getMonthlyAmountAsFloat() : null;
    }

    /**
     * Create or update configuration for a unit.
     */
    public function createOrUpdate(
        WegEinheit $wegEinheit,
        int $year,
        float $monthlyAmount,
        ?float $yearlyAdvancePayment = null,
    ): WegEinheitVorauszahlung {
        $config = $this->findByWegEinheitAndYear($wegEinheit, $year);

        if (!$config) {
            $config = new WegEinheitVorauszahlung();
            $config->setWegEinheit($wegEinheit);
            $config->setYear($year);
        }

        $config->setMonthlyAmount((string) $monthlyAmount);

        if (null !== $yearlyAdvancePayment) {
            $config->setYearlyAdvancePayment((string) $yearlyAdvancePayment);
        }

        $this->save($config, true);

        return $config;
    }

    /**
     * Get all monthly amounts for all units in a WEG for a specific year.
     * Returns array keyed by unit number.
     *
     * @return array<string, float>
     */
    public function getMonthlyAmountsByWegAndYear(int $wegId, int $year): array
    {
        $configs = $this->findByWegAndYear($wegId, $year);
        $amounts = [];

        foreach ($configs as $config) {
            $unitNumber = $config->getWegEinheit()->getNummer();
            $amounts[$unitNumber] = $config->getMonthlyAmountAsFloat();
        }

        return $amounts;
    }
}
