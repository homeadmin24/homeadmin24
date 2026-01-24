<?php

namespace App\Repository;

use App\Entity\Weg;
use App\Entity\WegKontostand;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WegKontostand>
 */
class WegKontostandRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WegKontostand::class);
    }

    /**
     * Get Kontostand for specific WEG, year and account type.
     */
    public function findByWegYearAndType(Weg $weg, int $year, string $bankkontoTyp = 'hausgeld'): ?WegKontostand
    {
        return $this->findOneBy([
            'weg' => $weg,
            'year' => $year,
            'bankkontoTyp' => $bankkontoTyp,
        ]);
    }

    /**
     * Get all Kontostände for a WEG, ordered by year DESC.
     *
     * @return WegKontostand[]
     */
    public function findByWeg(Weg $weg, string $bankkontoTyp = 'hausgeld'): array
    {
        return $this->findBy(
            ['weg' => $weg, 'bankkontoTyp' => $bankkontoTyp],
            ['year' => 'DESC']
        );
    }

    /**
     * Get latest Kontostand for a WEG (most recent year).
     */
    public function findLatestByWeg(Weg $weg, string $bankkontoTyp = 'hausgeld'): ?WegKontostand
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.weg = :weg')
            ->andWhere('k.bankkontoTyp = :typ')
            ->setParameter('weg', $weg)
            ->setParameter('typ', $bankkontoTyp)
            ->orderBy('k.year', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Check if Kontostand exists for given parameters.
     */
    public function exists(Weg $weg, int $year, string $bankkontoTyp = 'hausgeld'): bool
    {
        return null !== $this->findByWegYearAndType($weg, $year, $bankkontoTyp);
    }
}
