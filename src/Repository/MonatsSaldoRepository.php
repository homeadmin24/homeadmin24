<?php

namespace App\Repository;

use App\Entity\MonatsSaldo;
use App\Entity\Weg;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MonatsSaldo>
 */
class MonatsSaldoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MonatsSaldo::class);
    }

    public function save(MonatsSaldo $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MonatsSaldo $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Get balance for a specific month and WEG.
     */
    public function findByWegAndMonth(Weg $weg, \DateTimeInterface $month): ?MonatsSaldo
    {
        return $this->createQueryBuilder('mb')
            ->andWhere('mb.weg = :weg')
            ->andWhere('mb.balanceMonth = :month')
            ->setParameter('weg', $weg)
            ->setParameter('month', $month)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get balances for a specific year.
     *
     * @return array<MonatsSaldo>
     */
    public function findByWegAndYear(Weg $weg, int $year): array
    {
        $startDate = new \DateTime($year . '-01-01');
        $endDate = new \DateTime($year . '-12-31');

        return $this->createQueryBuilder('mb')
            ->andWhere('mb.weg = :weg')
            ->andWhere('mb.balanceMonth >= :startDate')
            ->andWhere('mb.balanceMonth <= :endDate')
            ->setParameter('weg', $weg)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('mb.balanceMonth', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get year-end balance for a specific year.
     */
    public function findYearEndBalance(Weg $weg, int $year): ?MonatsSaldo
    {
        $startDate = new \DateTime($year . '-01-01');
        $endDate = new \DateTime($year . '-12-31');

        return $this->createQueryBuilder('mb')
            ->andWhere('mb.weg = :weg')
            ->andWhere('mb.balanceMonth >= :startDate')
            ->andWhere('mb.balanceMonth <= :endDate')
            ->setParameter('weg', $weg)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('mb.balanceMonth', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get year-start balance for a specific year.
     */
    public function findYearStartBalance(Weg $weg, int $year): ?MonatsSaldo
    {
        $startDate = new \DateTime($year . '-01-01');
        $endDate = new \DateTime($year . '-12-31');

        return $this->createQueryBuilder('mb')
            ->andWhere('mb.weg = :weg')
            ->andWhere('mb.balanceMonth >= :startDate')
            ->andWhere('mb.balanceMonth <= :endDate')
            ->setParameter('weg', $weg)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('mb.balanceMonth', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get balance change for a specific year.
     */
    public function calculateYearlyBalanceChange(Weg $weg, int $year): ?float
    {
        $startBalance = $this->findYearStartBalance($weg, $year);
        $endBalance = $this->findYearEndBalance($weg, $year);

        if (!$startBalance || !$endBalance) {
            return null;
        }

        return (float) $endBalance->getClosingBalance() - (float) $startBalance->getOpeningBalance();
    }
}
