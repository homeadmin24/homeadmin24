<?php

namespace App\Repository;

use App\Entity\Zahlung;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Zahlung>
 */
class ZahlungRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Zahlung::class);
    }

    /*
    public function findZahlungenForWegAndYear(int $wegId, int $year): array
    {
        // Create date range for the given year
        $startDate = new \DateTime($year.'-01-01');
        $endDate = new \DateTime($year.'-12-31');

        return $this->createQueryBuilder('z')
            ->join('z.eigentuemer', 'we')
            ->where('we.id = :wegId')
            ->andWhere('z.datum >= :startDate')
            ->andWhere('z.datum <= :endDate')
            ->setParameter('wegId', $wegId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();
    }
    */

    /**
     * @return Zahlung[]
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        $year = (int) $startDate->format('Y');

        return $this->createQueryBuilder('z')
            ->where('(z.datum >= :startDate AND z.datum <= :endDate AND z.abrechnungsjahrZuordnung IS NULL) OR z.abrechnungsjahrZuordnung = :year')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('year', $year)
            ->orderBy('z.datum', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Zahlung[]
     */
    public function findByDateRangeWithSimulations(\DateTime $startDate, \DateTime $endDate, ?bool $includeSimulations = null, bool $onlySimulations = false): array
    {
        $year = (int) $startDate->format('Y');

        $qb = $this->createQueryBuilder('z')
            ->where('(z.datum >= :startDate AND z.datum <= :endDate AND z.abrechnungsjahrZuordnung IS NULL) OR z.abrechnungsjahrZuordnung = :year')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('year', $year);

        // Handle simulation filtering
        if ($onlySimulations) {
            $qb->andWhere('z.isSimulation = true');
        } elseif (false === $includeSimulations) {
            $qb->andWhere('z.isSimulation = false');
        }
        // If $includeSimulations is true or null, include all payments

        return $qb->orderBy('z.datum', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Zahlung[]
     */
    public function findByAbrechnungsjahrZuordnung(int $year): array
    {
        $startDate = new \DateTime($year . '-01-01');
        $endDate = new \DateTime($year . '-12-31');

        return $this->createQueryBuilder('z')
            ->where('(z.datum >= :startDate AND z.datum <= :endDate AND z.abrechnungsjahrZuordnung IS NULL) OR z.abrechnungsjahrZuordnung = :year')
            ->andWhere('z.isSimulation = false')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('year', $year)
            ->orderBy('z.datum', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all payments for a WEG and year.
     *
     * @return Zahlung[]
     */
    public function getPaymentsByWegAndYear(\App\Entity\Weg $weg, int $year): array
    {
        // For now, get all payments for the year
        // In the future, could filter by WEG if needed
        return $this->findByAbrechnungsjahrZuordnung($year);
    }

    /**
     * Get owner payments for a specific unit and year.
     *
     * @return Zahlung[]
     */
    public function getOwnerPaymentsByYear(\App\Entity\WegEinheit $einheit, int $year): array
    {
        $startDate = new \DateTime($year . '-01-01');
        $endDate = new \DateTime($year . '-12-31');

        return $this->createQueryBuilder('z')
            ->where('(z.datum >= :startDate AND z.datum <= :endDate AND z.abrechnungsjahrZuordnung IS NULL) OR z.abrechnungsjahrZuordnung = :year')
            ->andWhere('z.isSimulation = false')
            ->andWhere('z.betrag > 0') // Only positive payments (income)
            ->andWhere('z.eigentuemer = :eigentuemer')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('year', $year)
            ->setParameter('eigentuemer', $einheit)
            ->orderBy('z.datum', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find payments that are not fully categorized (missing hauptkategorie OR kostenkonto).
     *
     * @return Zahlung[]
     */
    public function findUncategorized(): array
    {
        return $this->createQueryBuilder('z')
            ->where('z.hauptkategorie IS NULL OR z.kostenkonto IS NULL')
            ->orderBy('z.datum', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
