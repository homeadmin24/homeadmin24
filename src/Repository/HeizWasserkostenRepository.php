<?php

namespace App\Repository;

use App\Entity\HeizWasserkosten;
use App\Entity\Weg;
use App\Entity\WegEinheit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HeizWasserkosten>
 *
 * @method HeizWasserkosten|null find($id, $lockMode = null, $lockVersion = null)
 * @method HeizWasserkosten|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method HeizWasserkosten[]    findAll()
 * @method HeizWasserkosten[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class HeizWasserkostenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HeizWasserkosten::class);
    }

    /**
     * Find costs for a specific unit and year.
     */
    public function findByEinheitAndYear(WegEinheit $einheit, int $jahr): ?HeizWasserkosten
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.wegEinheit = :einheit')
            ->andWhere('h.jahr = :jahr')
            ->andWhere('h.isWegGesamt = false')
            ->setParameter('einheit', $einheit)
            ->setParameter('jahr', $jahr)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find total WEG costs for a specific year.
     */
    public function findWegGesamtByYear(Weg $weg, int $jahr): ?HeizWasserkosten
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.weg = :weg')
            ->andWhere('h.jahr = :jahr')
            ->andWhere('h.isWegGesamt = true')
            ->setParameter('weg', $weg)
            ->setParameter('jahr', $jahr)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all costs for a specific year.
     *
     * @return array<HeizWasserkosten>
     */
    public function findByYear(int $jahr): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.jahr = :jahr')
            ->setParameter('jahr', $jahr)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all unit costs for a WEG and year.
     *
     * @return array<HeizWasserkosten>
     */
    public function findUnitCostsByWegAndYear(Weg $weg, int $jahr): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.weg = :weg')
            ->andWhere('h.jahr = :jahr')
            ->andWhere('h.isWegGesamt = false')
            ->setParameter('weg', $weg)
            ->setParameter('jahr', $jahr)
            ->getQuery()
            ->getResult();
    }
}
