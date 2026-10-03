<?php

namespace App\Repository;

use App\Entity\BankkontoTyp;
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

    public function findByWegYearAndType(Weg $weg, int $year, BankkontoTyp $bankkontoTyp = BankkontoTyp::HAUSGELD): ?WegKontostand
    {
        return $this->findOneBy([
            'weg' => $weg,
            'year' => $year,
            'bankkontoTyp' => $bankkontoTyp,
        ]);
    }

    /**
     * @return WegKontostand[]
     */
    public function findByWeg(Weg $weg, BankkontoTyp $bankkontoTyp = BankkontoTyp::HAUSGELD): array
    {
        return $this->findBy(
            ['weg' => $weg, 'bankkontoTyp' => $bankkontoTyp],
            ['year' => 'DESC']
        );
    }

    public function findLatestByWeg(Weg $weg, BankkontoTyp $bankkontoTyp = BankkontoTyp::HAUSGELD): ?WegKontostand
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

    public function exists(Weg $weg, int $year, BankkontoTyp $bankkontoTyp = BankkontoTyp::HAUSGELD): bool
    {
        return null !== $this->findByWegYearAndType($weg, $year, $bankkontoTyp);
    }
}
