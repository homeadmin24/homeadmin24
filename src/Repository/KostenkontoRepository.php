<?php

namespace App\Repository;

use App\Entity\Kostenkonto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Kostenkonto>
 */
class KostenkontoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Kostenkonto::class);
    }

    /**
     * Find all tax-deductible kostenkonto entries.
     *
     * @return Kostenkonto[]
     */
    public function findTaxDeductible(): array
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.taxDeductible = :taxDeductible')
            ->andWhere('k.isActive = :isActive')
            ->setParameter('taxDeductible', true)
            ->setParameter('isActive', true)
            ->orderBy('k.nummer', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get tax-deductible account numbers only.
     *
     * @return string[]
     */
    public function getTaxDeductibleAccountNumbers(): array
    {
        $result = $this->createQueryBuilder('k')
            ->select('k.nummer')
            ->andWhere('k.taxDeductible = :taxDeductible')
            ->andWhere('k.nummer IS NOT NULL')
            ->setParameter('taxDeductible', true)
            ->orderBy('k.nummer', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'nummer');
    }

    /**
     * Check if a specific account number is tax-deductible.
     */
    public function isAccountTaxDeductible(string $accountNumber): bool
    {
        $count = $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->andWhere('k.nummer = :nummer')
            ->andWhere('k.taxDeductible = :taxDeductible')
            ->andWhere('k.isActive = :isActive')
            ->setParameter('nummer', $accountNumber)
            ->setParameter('taxDeductible', true)
            ->setParameter('isActive', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
