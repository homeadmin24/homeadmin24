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
     * Get all payments for a WEG filtered by transaction date year.
     *
     * @return Zahlung[]
     */
    public function getPaymentsByWegAndDateYear(\App\Entity\Weg $weg, int $year): array
    {
        return $this->createQueryBuilder('z')
            ->join('z.eigentuemer', 'we')
            ->andWhere('we.weg = :weg')
            ->andWhere('z.abrechnungsjahrZuordnung IS NULL OR z.abrechnungsjahrZuordnung = :year')
            ->setParameter('weg', $weg)
            ->setParameter('year', $year)
            ->orderBy('z.datum', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all payments filtered by transaction date year (no WEG filter).
     *
     * @return Zahlung[]
     */
    public function getAllPaymentsByDateYear(int $year): array
    {
        $startDate = new \DateTime($year . '-01-01');
        $endDate = new \DateTime($year . '-12-31');

        return $this->createQueryBuilder('z')
            ->leftJoin('z.hauptkategorie', 'hk')
            ->andWhere('(z.abrechnungsjahrZuordnung = :year OR (z.abrechnungsjahrZuordnung IS NULL AND z.datum BETWEEN :startDate AND :endDate))')
            ->andWhere('z.eigentuemer IS NULL')
            ->andWhere('z.betrag < 0')
            ->andWhere('hk.name != :kategorie_umbuchung OR hk.name IS NULL')
            ->setParameter('year', $year)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('kategorie_umbuchung', 'Umbuchung')
            ->orderBy('z.kostenkonto', 'ASC')
            ->addOrderBy('z.datum', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get summed income/expense for a calendar year (by booking date).
     *
     * @return array{income: float, expense: float}
     */
    public function getPaymentTotalsByDateYear(int $year): array
    {
        $startDate = new \DateTime($year . '-01-01');
        $endDate = new \DateTime($year . '-12-31');

        $result = $this->createQueryBuilder('z')
            ->leftJoin('z.kostenkonto', 'k')
            ->select(
                'SUM(CASE WHEN z.betrag > 0 THEN z.betrag ELSE 0 END) AS income',
                'SUM(CASE WHEN z.betrag < 0 AND (z.bankkontoTyp != :ruecklage AND (k.kategorisierungsTyp != :ruecklage OR k.kategorisierungsTyp IS NULL)) THEN z.betrag ELSE 0 END) AS expense',
                'SUM(CASE WHEN z.betrag < 0 AND (z.bankkontoTyp = :ruecklage OR k.kategorisierungsTyp = :ruecklage) THEN z.betrag ELSE 0 END) AS reserve_transfer'
            )
            ->andWhere('z.datum >= :startDate')
            ->andWhere('z.datum <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('ruecklage', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->getQuery()
            ->getSingleResult();

        return [
            'income' => (float) ($result['income'] ?? 0),
            'expense' => (float) ($result['expense'] ?? 0),
            'reserve_transfer' => (float) ($result['reserve_transfer'] ?? 0),
        ];
    }

    /**
     * Get payment summary for Vermoegen page (Hausgeld/Ruecklage).
     *
     * @return array<string, float>
     */
    public function getVermoegenPaymentSummary(int $year, ?\DateTimeInterface $endDateOverride = null): array
    {
        $startDate = new \DateTime($year . '-01-01');
        $endDate = $endDateOverride ? \DateTime::createFromInterface($endDateOverride) : new \DateTime($year . '-12-31');

        $result = $this->createQueryBuilder('z')
            ->select(
                'SUM(CASE WHEN z.betrag > 0 AND z.bankkontoTyp = :hausgeld AND (k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ) THEN z.betrag ELSE 0 END) AS hausgeld_income',
                'SUM(CASE WHEN z.betrag < 0 AND z.bankkontoTyp = :hausgeld AND (k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ) THEN z.betrag ELSE 0 END) AS hausgeld_expense',
                'SUM(CASE WHEN z.betrag > 0 AND z.bankkontoTyp = :ruecklage AND (k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ) THEN z.betrag ELSE 0 END) AS ruecklage_income',
                'SUM(CASE WHEN z.betrag < 0 AND z.bankkontoTyp = :ruecklage AND (k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ) THEN z.betrag ELSE 0 END) AS ruecklage_expense',
                'SUM(CASE WHEN z.betrag > 0 AND z.bankkontoTyp = :hausgeld AND hk.name = :kategorie_hausgeld THEN z.betrag ELSE 0 END) AS hausgeld_income_monthly',
                'SUM(CASE WHEN z.betrag > 0 AND z.bankkontoTyp = :hausgeld AND hk.name = :kategorie_sonderumlage THEN z.betrag ELSE 0 END) AS hausgeld_income_sonderumlage',
                'SUM(CASE WHEN z.betrag > 0 AND z.bankkontoTyp = :hausgeld AND hk.name = :kategorie_nachzahlung THEN z.betrag ELSE 0 END) AS hausgeld_income_nachzahlung',
                'SUM(CASE WHEN z.betrag < 0 AND z.bankkontoTyp = :hausgeld AND hk.name = :kategorie_erstattung THEN z.betrag ELSE 0 END) AS hausgeld_income_erstattung',
                'SUM(CASE WHEN k.kategorisierungsTyp = :ruecklage_typ AND z.zahlungTyp = :ruecklage_zufuehrung THEN ABS(z.betrag) ELSE 0 END) AS ruecklage_transfer_in',
                'SUM(CASE WHEN k.kategorisierungsTyp = :ruecklage_typ AND z.zahlungTyp = :ruecklage_aufloesung THEN ABS(z.betrag) ELSE 0 END) AS ruecklage_transfer_out'
            )
            ->leftJoin('z.kostenkonto', 'k')
            ->leftJoin('z.hauptkategorie', 'hk')
            ->andWhere('(z.abrechnungsjahrZuordnung = :year OR (z.abrechnungsjahrZuordnung IS NULL AND z.datum BETWEEN :startDate AND :endDate))')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('year', $year)
            ->setParameter('hausgeld', 'hausgeld')
            ->setParameter('ruecklage', 'ruecklage')
            ->setParameter('ruecklage_typ', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->setParameter('kategorie_hausgeld', 'Hausgeld-Zahlung')
            ->setParameter('kategorie_sonderumlage', 'Sonderumlage')
            ->setParameter('kategorie_nachzahlung', 'Nachzahlung')
            ->setParameter('kategorie_erstattung', 'Rückzahlung an Eigentümer')
            ->setParameter('ruecklage_zufuehrung', 'ruecklage_zufuehrung')
            ->setParameter('ruecklage_aufloesung', 'ruecklage_aufloesung')
            ->getQuery()
            ->getSingleResult();

        $paidNotAssignedResult = $this->createQueryBuilder('z')
            ->select(
                'SUM(CASE WHEN z.betrag < 0 AND z.bankkontoTyp = :hausgeld AND z.abrechnungsjahrZuordnung IS NOT NULL AND z.abrechnungsjahrZuordnung != :year AND (k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ) THEN z.betrag ELSE 0 END) AS paid_not_assigned_expense',
                'SUM(CASE WHEN z.betrag > 0 AND z.bankkontoTyp = :hausgeld AND z.abrechnungsjahrZuordnung IS NOT NULL AND z.abrechnungsjahrZuordnung != :year AND (k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ) THEN z.betrag ELSE 0 END) AS paid_not_assigned_income'
            )
            ->leftJoin('z.kostenkonto', 'k')
            ->andWhere('z.datum BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('year', $year)
            ->setParameter('hausgeld', 'hausgeld')
            ->setParameter('ruecklage_typ', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->getQuery()
            ->getSingleResult();

        $paidNotAssignedExpensePrev = $this->createQueryBuilder('z')
            ->select(
                'SUM(CASE WHEN z.betrag < 0 AND z.bankkontoTyp = :hausgeld AND z.abrechnungsjahrZuordnung < :year AND (k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ) THEN z.betrag ELSE 0 END) AS paid_not_assigned_expense_prev_year'
            )
            ->leftJoin('z.kostenkonto', 'k')
            ->andWhere('z.datum BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('year', $year)
            ->setParameter('hausgeld', 'hausgeld')
            ->setParameter('ruecklage_typ', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->getQuery()
            ->getSingleResult();

        $paidNotAssignedExpenseNext = $this->createQueryBuilder('z')
            ->select(
                'SUM(CASE WHEN z.betrag < 0 AND z.bankkontoTyp = :hausgeld AND z.abrechnungsjahrZuordnung > :year AND (k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ) THEN z.betrag ELSE 0 END) AS paid_not_assigned_expense_next_year'
            )
            ->leftJoin('z.kostenkonto', 'k')
            ->andWhere('z.datum BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('year', $year)
            ->setParameter('hausgeld', 'hausgeld')
            ->setParameter('ruecklage_typ', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->getQuery()
            ->getSingleResult();

        $paidNotAssignedIncomePrev = $this->createQueryBuilder('z')
            ->select(
                'SUM(CASE WHEN z.betrag > 0 AND z.bankkontoTyp = :hausgeld AND z.abrechnungsjahrZuordnung < :year AND (k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ) THEN z.betrag ELSE 0 END) AS paid_not_assigned_income_prev_year'
            )
            ->leftJoin('z.kostenkonto', 'k')
            ->andWhere('z.datum BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('year', $year)
            ->setParameter('hausgeld', 'hausgeld')
            ->setParameter('ruecklage_typ', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->getQuery()
            ->getSingleResult();

        $paidNotAssignedIncomeNext = $this->createQueryBuilder('z')
            ->select(
                'SUM(CASE WHEN z.betrag > 0 AND z.bankkontoTyp = :hausgeld AND z.abrechnungsjahrZuordnung > :year AND (k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ) THEN z.betrag ELSE 0 END) AS paid_not_assigned_income_next_year'
            )
            ->leftJoin('z.kostenkonto', 'k')
            ->andWhere('z.datum BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('year', $year)
            ->setParameter('hausgeld', 'hausgeld')
            ->setParameter('ruecklage_typ', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->getQuery()
            ->getSingleResult();

        $nettoDateResult = $this->createQueryBuilder('z')
            ->select(
                'SUM(CASE WHEN z.bankkontoTyp = :hausgeld AND z.betrag > 0 AND (k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ) AND (hk.name IS NULL OR hk.name != :kategorie_umbuchung) THEN z.betrag ELSE 0 END) AS hausgeld_income_date',
                'SUM(CASE WHEN z.bankkontoTyp = :hausgeld AND z.betrag < 0 AND (k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ) AND (hk.name IS NULL OR hk.name != :kategorie_umbuchung) THEN z.betrag ELSE 0 END) AS hausgeld_expense_date',
                'SUM(CASE WHEN z.bankkontoTyp = :hausgeld AND (k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ) AND (hk.name IS NULL OR hk.name != :kategorie_umbuchung) THEN z.betrag ELSE 0 END) AS hausgeld_netto_date'
            )
            ->leftJoin('z.kostenkonto', 'k')
            ->leftJoin('z.hauptkategorie', 'hk')
            ->andWhere('z.datum BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('hausgeld', 'hausgeld')
            ->setParameter('ruecklage_typ', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->setParameter('kategorie_umbuchung', 'Umbuchung')
            ->getQuery()
            ->getSingleResult();

        $paidNotAssignedIncomeRows = $this->createQueryBuilder('z')
            ->select('hk.name AS kategorie', 'k.bezeichnung AS kostenkonto', 'SUM(z.betrag) AS betrag')
            ->leftJoin('z.kostenkonto', 'k')
            ->leftJoin('z.hauptkategorie', 'hk')
            ->andWhere('z.datum BETWEEN :startDate AND :endDate')
            ->andWhere('z.bankkontoTyp = :hausgeld')
            ->andWhere('z.betrag > 0')
            ->andWhere('z.abrechnungsjahrZuordnung IS NOT NULL AND z.abrechnungsjahrZuordnung != :year')
            ->andWhere('k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ')
            ->andWhere('hk.name != :kategorie_umbuchung OR hk.name IS NULL')
            ->groupBy('hk.name, k.bezeichnung')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('year', $year)
            ->setParameter('hausgeld', 'hausgeld')
            ->setParameter('ruecklage_typ', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->setParameter('kategorie_umbuchung', 'Umbuchung')
            ->getQuery()
            ->getResult();

        $paidNotAssignedIncomeNextRows = $this->createQueryBuilder('z')
            ->select('hk.name AS kategorie', 'k.bezeichnung AS kostenkonto', 'SUM(z.betrag) AS betrag')
            ->leftJoin('z.kostenkonto', 'k')
            ->leftJoin('z.hauptkategorie', 'hk')
            ->andWhere('z.datum BETWEEN :startDate AND :endDate')
            ->andWhere('z.bankkontoTyp = :hausgeld')
            ->andWhere('z.betrag > 0')
            ->andWhere('z.abrechnungsjahrZuordnung > :year')
            ->andWhere('k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ')
            ->andWhere('hk.name != :kategorie_umbuchung OR hk.name IS NULL')
            ->groupBy('hk.name, k.bezeichnung')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('year', $year)
            ->setParameter('hausgeld', 'hausgeld')
            ->setParameter('ruecklage_typ', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->setParameter('kategorie_umbuchung', 'Umbuchung')
            ->getQuery()
            ->getResult();

        $paidNotAssignedExpenseRows = $this->createQueryBuilder('z')
            ->select('hk.name AS kategorie', 'SUM(z.betrag) AS betrag')
            ->leftJoin('z.kostenkonto', 'k')
            ->leftJoin('z.hauptkategorie', 'hk')
            ->andWhere('z.datum BETWEEN :startDate AND :endDate')
            ->andWhere('z.bankkontoTyp = :hausgeld')
            ->andWhere('z.betrag < 0')
            ->andWhere('z.abrechnungsjahrZuordnung IS NOT NULL AND z.abrechnungsjahrZuordnung != :year')
            ->andWhere('k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ')
            ->andWhere('hk.name != :kategorie_umbuchung OR hk.name IS NULL')
            ->groupBy('hk.name, k.bezeichnung')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('year', $year)
            ->setParameter('hausgeld', 'hausgeld')
            ->setParameter('ruecklage_typ', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->setParameter('kategorie_umbuchung', 'Umbuchung')
            ->getQuery()
            ->getResult();

        $paidNotAssignedExpensePrevRows = $this->createQueryBuilder('z')
            ->select('hk.name AS kategorie', 'k.bezeichnung AS kostenkonto', 'SUM(z.betrag) AS betrag')
            ->leftJoin('z.kostenkonto', 'k')
            ->leftJoin('z.hauptkategorie', 'hk')
            ->andWhere('z.datum BETWEEN :startDate AND :endDate')
            ->andWhere('z.bankkontoTyp = :hausgeld')
            ->andWhere('z.betrag < 0')
            ->andWhere('z.abrechnungsjahrZuordnung < :year')
            ->andWhere('k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ')
            ->andWhere('hk.name != :kategorie_umbuchung OR hk.name IS NULL')
            ->groupBy('hk.name, k.bezeichnung')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('year', $year)
            ->setParameter('hausgeld', 'hausgeld')
            ->setParameter('ruecklage_typ', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->setParameter('kategorie_umbuchung', 'Umbuchung')
            ->getQuery()
            ->getResult();

        $paidNotAssignedExpenseNextRows = $this->createQueryBuilder('z')
            ->select('hk.name AS kategorie', 'k.bezeichnung AS kostenkonto', 'SUM(z.betrag) AS betrag')
            ->leftJoin('z.kostenkonto', 'k')
            ->leftJoin('z.hauptkategorie', 'hk')
            ->andWhere('z.datum BETWEEN :startDate AND :endDate')
            ->andWhere('z.bankkontoTyp = :hausgeld')
            ->andWhere('z.betrag < 0')
            ->andWhere('z.abrechnungsjahrZuordnung > :year')
            ->andWhere('k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ')
            ->andWhere('hk.name != :kategorie_umbuchung OR hk.name IS NULL')
            ->groupBy('hk.name, k.bezeichnung')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('year', $year)
            ->setParameter('hausgeld', 'hausgeld')
            ->setParameter('ruecklage_typ', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->setParameter('kategorie_umbuchung', 'Umbuchung')
            ->getQuery()
            ->getResult();

        $dateIncomeRows = $this->createQueryBuilder('z')
            ->select('hk.name AS kategorie', 'k.bezeichnung AS kostenkonto', 'SUM(z.betrag) AS betrag')
            ->leftJoin('z.kostenkonto', 'k')
            ->leftJoin('z.hauptkategorie', 'hk')
            ->andWhere('z.datum BETWEEN :startDate AND :endDate')
            ->andWhere('z.bankkontoTyp = :hausgeld')
            ->andWhere('z.betrag > 0')
            ->andWhere('k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ')
            ->andWhere('hk.name != :kategorie_umbuchung OR hk.name IS NULL')
            ->groupBy('hk.name, k.bezeichnung')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('hausgeld', 'hausgeld')
            ->setParameter('ruecklage_typ', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->setParameter('kategorie_umbuchung', 'Umbuchung')
            ->getQuery()
            ->getResult();

        $dateExpenseRows = $this->createQueryBuilder('z')
            ->select('hk.name AS kategorie', 'k.bezeichnung AS kostenkonto', 'SUM(z.betrag) AS betrag')
            ->leftJoin('z.kostenkonto', 'k')
            ->leftJoin('z.hauptkategorie', 'hk')
            ->andWhere('z.datum BETWEEN :startDate AND :endDate')
            ->andWhere('z.bankkontoTyp = :hausgeld')
            ->andWhere('z.betrag < 0')
            ->andWhere('k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ')
            ->andWhere('hk.name != :kategorie_umbuchung OR hk.name IS NULL')
            ->groupBy('hk.name, k.bezeichnung')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('hausgeld', 'hausgeld')
            ->setParameter('ruecklage_typ', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->setParameter('kategorie_umbuchung', 'Umbuchung')
            ->getQuery()
            ->getResult();

        $abjIncomeResult = $this->createQueryBuilder('z')
            ->select(
                'SUM(CASE WHEN z.bankkontoTyp = :hausgeld AND z.betrag > 0 AND z.abrechnungsjahrZuordnung = :year AND (k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ) AND (hk.name IS NULL OR hk.name != :kategorie_umbuchung) THEN z.betrag ELSE 0 END) AS hausgeld_income_abj',
                'SUM(CASE WHEN z.bankkontoTyp = :hausgeld AND z.betrag < 0 AND z.abrechnungsjahrZuordnung = :year AND (k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ) AND (hk.name IS NULL OR hk.name != :kategorie_umbuchung) THEN z.betrag ELSE 0 END) AS hausgeld_expense_abj'
            )
            ->leftJoin('z.kostenkonto', 'k')
            ->leftJoin('z.hauptkategorie', 'hk')
            ->setParameter('year', $year)
            ->setParameter('hausgeld', 'hausgeld')
            ->setParameter('ruecklage_typ', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->setParameter('kategorie_umbuchung', 'Umbuchung')
            ->getQuery()
            ->getSingleResult();

        $abjIncomeRows = $this->createQueryBuilder('z')
            ->select('hk.name AS kategorie', 'k.bezeichnung AS kostenkonto', 'SUM(z.betrag) AS betrag')
            ->leftJoin('z.kostenkonto', 'k')
            ->leftJoin('z.hauptkategorie', 'hk')
            ->andWhere('z.bankkontoTyp = :hausgeld')
            ->andWhere('z.betrag > 0')
            ->andWhere('z.abrechnungsjahrZuordnung = :year')
            ->andWhere('k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ')
            ->andWhere('hk.name != :kategorie_umbuchung OR hk.name IS NULL')
            ->groupBy('hk.name, k.bezeichnung')
            ->setParameter('year', $year)
            ->setParameter('hausgeld', 'hausgeld')
            ->setParameter('ruecklage_typ', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->setParameter('kategorie_umbuchung', 'Umbuchung')
            ->getQuery()
            ->getResult();

        $abjExpenseRows = $this->createQueryBuilder('z')
            ->select('hk.name AS kategorie', 'k.bezeichnung AS kostenkonto', 'SUM(z.betrag) AS betrag')
            ->leftJoin('z.kostenkonto', 'k')
            ->leftJoin('z.hauptkategorie', 'hk')
            ->andWhere('z.bankkontoTyp = :hausgeld')
            ->andWhere('z.betrag < 0')
            ->andWhere('z.abrechnungsjahrZuordnung = :year')
            ->andWhere('k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ')
            ->andWhere('hk.name != :kategorie_umbuchung OR hk.name IS NULL')
            ->groupBy('hk.name, k.bezeichnung')
            ->setParameter('year', $year)
            ->setParameter('hausgeld', 'hausgeld')
            ->setParameter('ruecklage_typ', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->setParameter('kategorie_umbuchung', 'Umbuchung')
            ->getQuery()
            ->getResult();

        $paidNotAssignedIncomeByCategory = [];
        $paidNotAssignedIncomeGutschriftByKostenkonto = [];
        foreach ($paidNotAssignedIncomeRows as $row) {
            if (!isset($row['kategorie'])) {
                continue;
            }
            $category = $row['kategorie'];
            $amount = (float) ($row['betrag'] ?? 0);
            $paidNotAssignedIncomeByCategory[$category] = $amount;
            if ('Gutschrift Dienstleister' === $category) {
                $label = $this->normalizeKostenkontoLabel($row['kostenkonto'] ?? null);
                if (null !== $label) {
                    $paidNotAssignedIncomeGutschriftByKostenkonto[$label] = ($paidNotAssignedIncomeGutschriftByKostenkonto[$label] ?? 0.0) + $amount;
                }
            }
        }

        $paidNotAssignedIncomeNextByCategory = [];
        $paidNotAssignedIncomeNextGutschriftByKostenkonto = [];
        foreach ($paidNotAssignedIncomeNextRows as $row) {
            if (!isset($row['kategorie'])) {
                continue;
            }
            $category = $row['kategorie'];
            $amount = (float) ($row['betrag'] ?? 0);
            $paidNotAssignedIncomeNextByCategory[$category] = $amount;
            if ('Gutschrift Dienstleister' === $category) {
                $label = $this->normalizeKostenkontoLabel($row['kostenkonto'] ?? null);
                if (null !== $label) {
                    $paidNotAssignedIncomeNextGutschriftByKostenkonto[$label] = ($paidNotAssignedIncomeNextGutschriftByKostenkonto[$label] ?? 0.0) + $amount;
                }
            }
        }

        $paidNotAssignedExpenseByCategory = [];
        foreach ($paidNotAssignedExpenseRows as $row) {
            if (!isset($row['kategorie'])) {
                continue;
            }
            $paidNotAssignedExpenseByCategory[$row['kategorie']] = (float) ($row['betrag'] ?? 0);
        }

        $paidNotAssignedExpensePrevByCategory = [];
        $paidNotAssignedExpensePrevByKostenkonto = [];
        foreach ($paidNotAssignedExpensePrevRows as $row) {
            if (!isset($row['kategorie'])) {
                continue;
            }
            $category = $row['kategorie'];
            $amount = (float) ($row['betrag'] ?? 0);
            $paidNotAssignedExpensePrevByCategory[$category] = $amount;
            $label = $this->normalizeKostenkontoLabel($row['kostenkonto'] ?? null);
            if (null !== $label) {
                $paidNotAssignedExpensePrevByKostenkonto[$category][$label] = ($paidNotAssignedExpensePrevByKostenkonto[$category][$label] ?? 0.0) + $amount;
            }
        }

        $paidNotAssignedExpenseNextByCategory = [];
        $paidNotAssignedExpenseNextByKostenkonto = [];
        foreach ($paidNotAssignedExpenseNextRows as $row) {
            if (!isset($row['kategorie'])) {
                continue;
            }
            $category = $row['kategorie'];
            $amount = (float) ($row['betrag'] ?? 0);
            $paidNotAssignedExpenseNextByCategory[$category] = $amount;
            $label = $this->normalizeKostenkontoLabel($row['kostenkonto'] ?? null);
            if (null !== $label) {
                $paidNotAssignedExpenseNextByKostenkonto[$category][$label] = ($paidNotAssignedExpenseNextByKostenkonto[$category][$label] ?? 0.0) + $amount;
            }
        }

        $dateIncomeByCategory = [];
        $dateIncomeGutschriftByKostenkonto = [];
        foreach ($dateIncomeRows as $row) {
            if (!isset($row['kategorie'])) {
                continue;
            }
            $category = $row['kategorie'];
            $amount = (float) ($row['betrag'] ?? 0);
            $dateIncomeByCategory[$category] = $amount;
            if ('Gutschrift Dienstleister' === $category) {
                $label = $this->normalizeKostenkontoLabel($row['kostenkonto'] ?? null);
                if (null !== $label) {
                    $dateIncomeGutschriftByKostenkonto[$label] = ($dateIncomeGutschriftByKostenkonto[$label] ?? 0.0) + $amount;
                }
            }
        }

        $dateExpenseByCategory = [];
        $dateExpenseByKostenkonto = [];
        foreach ($dateExpenseRows as $row) {
            if (!isset($row['kategorie'])) {
                continue;
            }
            $category = $row['kategorie'];
            $amount = (float) ($row['betrag'] ?? 0);
            $dateExpenseByCategory[$category] = $amount;
            $label = $this->normalizeKostenkontoLabel($row['kostenkonto'] ?? null);
            if (null !== $label) {
                $dateExpenseByKostenkonto[$category][$label] = ($dateExpenseByKostenkonto[$category][$label] ?? 0.0) + $amount;
            }
        }

        $abjIncomeByCategory = [];
        $abjIncomeGutschriftByKostenkonto = [];
        foreach ($abjIncomeRows as $row) {
            if (!isset($row['kategorie'])) {
                continue;
            }
            $category = $row['kategorie'];
            $amount = (float) ($row['betrag'] ?? 0);
            $abjIncomeByCategory[$category] = $amount;
            if ('Gutschrift Dienstleister' === $category) {
                $label = $this->normalizeKostenkontoLabel($row['kostenkonto'] ?? null);
                if (null !== $label) {
                    $abjIncomeGutschriftByKostenkonto[$label] = ($abjIncomeGutschriftByKostenkonto[$label] ?? 0.0) + $amount;
                }
            }
        }

        $abjExpenseByCategory = [];
        $abjExpenseByKostenkonto = [];
        foreach ($abjExpenseRows as $row) {
            if (!isset($row['kategorie'])) {
                continue;
            }
            $category = $row['kategorie'];
            $amount = (float) ($row['betrag'] ?? 0);
            $abjExpenseByCategory[$category] = $amount;
            $label = $this->normalizeKostenkontoLabel($row['kostenkonto'] ?? null);
            if (null !== $label) {
                $abjExpenseByKostenkonto[$category][$label] = ($abjExpenseByKostenkonto[$category][$label] ?? 0.0) + $amount;
            }
        }

        $expenseYearResult = $this->createQueryBuilder('z')
            ->select(
                'SUM(CASE WHEN z.betrag < 0 AND z.bankkontoTyp = :hausgeld AND (k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ) AND (hk.name IS NULL OR hk.name != :kategorie_umbuchung) THEN z.betrag ELSE 0 END) AS hausgeld_expense_year'
            )
            ->leftJoin('z.kostenkonto', 'k')
            ->leftJoin('z.hauptkategorie', 'hk')
            ->andWhere('(z.abrechnungsjahrZuordnung = :year OR (z.abrechnungsjahrZuordnung IS NULL AND z.datum BETWEEN :startDate AND :endDate))')
            ->setParameter('year', $year)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('hausgeld', 'hausgeld')
            ->setParameter('ruecklage_typ', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->setParameter('kategorie_umbuchung', 'Umbuchung')
            ->getQuery()
            ->getSingleResult();

        $incomeByCategoryResult = $this->createQueryBuilder('z')
            ->select(
                'hk.name AS kategorie',
                'SUM(z.betrag) AS betrag'
            )
            ->leftJoin('z.kostenkonto', 'k')
            ->leftJoin('z.hauptkategorie', 'hk')
            ->andWhere('(z.abrechnungsjahrZuordnung = :year OR (z.abrechnungsjahrZuordnung IS NULL AND z.datum BETWEEN :startDate AND :endDate))')
            ->andWhere('z.bankkontoTyp = :hausgeld')
            ->andWhere('z.betrag > 0')
            ->andWhere('hk.name != :kategorie_umbuchung')
            ->andWhere('k.kategorisierungsTyp IS NULL OR k.kategorisierungsTyp != :ruecklage_typ')
            ->groupBy('hk.name')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('year', $year)
            ->setParameter('hausgeld', 'hausgeld')
            ->setParameter('kategorie_umbuchung', 'Umbuchung')
            ->setParameter('ruecklage_typ', \App\Entity\KategorisierungsTyp::RUECKLAGENZUFUEHRUNG)
            ->getQuery()
            ->getResult();

        $incomeByCategory = [];
        foreach ($incomeByCategoryResult as $row) {
            if (!isset($row['kategorie'])) {
                continue;
            }
            $incomeByCategory[$row['kategorie']] = (float) ($row['betrag'] ?? 0);
        }

        $transferIn = (float) ($result['ruecklage_transfer_in'] ?? 0);
        $transferOut = (float) ($result['ruecklage_transfer_out'] ?? 0);
        $hausgeldIncome = (float) ($result['hausgeld_income'] ?? 0);
        $hausgeldExpense = (float) ($expenseYearResult['hausgeld_expense_year'] ?? 0);
        $ruecklageIncome = (float) ($result['ruecklage_income'] ?? 0);
        $ruecklageExpense = (float) ($result['ruecklage_expense'] ?? 0);
        $hausgeldMonthlyIncome = (float) ($result['hausgeld_income_monthly'] ?? 0);
        $hausgeldSonderumlageIncome = (float) ($result['hausgeld_income_sonderumlage'] ?? 0);
        $hausgeldNachzahlungIncome = (float) ($result['hausgeld_income_nachzahlung'] ?? 0);
        $hausgeldErstattung = (float) ($result['hausgeld_income_erstattung'] ?? 0);
        $paidNotAssignedExpense = (float) ($paidNotAssignedResult['paid_not_assigned_expense'] ?? 0);
        $paidNotAssignedIncome = (float) ($paidNotAssignedResult['paid_not_assigned_income'] ?? 0);
        $paidNotAssignedExpensePrevYear = (float) ($paidNotAssignedExpensePrev['paid_not_assigned_expense_prev_year'] ?? 0);
        $paidNotAssignedExpenseNextYear = (float) ($paidNotAssignedExpenseNext['paid_not_assigned_expense_next_year'] ?? 0);
        $paidNotAssignedIncomePrevYear = (float) ($paidNotAssignedIncomePrev['paid_not_assigned_income_prev_year'] ?? 0);
        $paidNotAssignedIncomeNextYear = (float) ($paidNotAssignedIncomeNext['paid_not_assigned_income_next_year'] ?? 0);
        $hausgeldNettoDate = (float) ($nettoDateResult['hausgeld_netto_date'] ?? 0);
        $hausgeldIncomeDate = (float) ($nettoDateResult['hausgeld_income_date'] ?? 0);
        $hausgeldExpenseDate = (float) ($nettoDateResult['hausgeld_expense_date'] ?? 0);
        $hausgeldIncomeAbj = (float) ($abjIncomeResult['hausgeld_income_abj'] ?? 0);
        $hausgeldExpenseAbj = (float) ($abjIncomeResult['hausgeld_expense_abj'] ?? 0);

        if ($transferIn > 0) {
            $hausgeldExpense -= $transferIn;
            $ruecklageIncome += $transferIn;
        }
        if ($transferOut > 0) {
            $hausgeldIncome += $transferOut;
            $ruecklageExpense -= $transferOut;
        }

        return [
            'hausgeld_income' => $hausgeldIncome,
            'hausgeld_expense' => $hausgeldExpense,
            'hausgeld_income_monthly' => $hausgeldMonthlyIncome,
            'hausgeld_income_sonderumlage' => $hausgeldSonderumlageIncome,
            'hausgeld_income_nachzahlung' => $hausgeldNachzahlungIncome,
            'hausgeld_income_erstattung' => $hausgeldErstattung,
            'hausgeld_income_by_category' => $incomeByCategory,
            'ruecklage_income' => $ruecklageIncome,
            'ruecklage_expense' => $ruecklageExpense,
            'paid_not_assigned_expense' => $paidNotAssignedExpense,
            'paid_not_assigned_income' => $paidNotAssignedIncome,
            'paid_not_assigned_expense_prev_year' => $paidNotAssignedExpensePrevYear,
            'paid_not_assigned_expense_next_year' => $paidNotAssignedExpenseNextYear,
            'paid_not_assigned_income_prev_year' => $paidNotAssignedIncomePrevYear,
            'paid_not_assigned_income_next_year' => $paidNotAssignedIncomeNextYear,
            'hausgeld_netto_date' => $hausgeldNettoDate,
            'hausgeld_income_date' => $hausgeldIncomeDate,
            'hausgeld_expense_date' => $hausgeldExpenseDate,
            'hausgeld_income_abj' => $hausgeldIncomeAbj,
            'hausgeld_expense_abj' => $hausgeldExpenseAbj,
            'paid_not_assigned_expense_by_category' => $paidNotAssignedExpenseByCategory,
            'paid_not_assigned_income_by_category' => $paidNotAssignedIncomeByCategory,
            'hausgeld_income_date_by_category' => $dateIncomeByCategory,
            'hausgeld_expense_date_by_category' => $dateExpenseByCategory,
            'paid_not_assigned_expense_prev_year_by_category' => $paidNotAssignedExpensePrevByCategory,
            'paid_not_assigned_expense_next_year_by_category' => $paidNotAssignedExpenseNextByCategory,
            'hausgeld_income_gutschrift_by_kostenkonto' => $dateIncomeGutschriftByKostenkonto,
            'paid_not_assigned_income_gutschrift_by_kostenkonto' => $paidNotAssignedIncomeGutschriftByKostenkonto,
            'hausgeld_expense_date_by_kostenkonto' => $dateExpenseByKostenkonto,
            'paid_not_assigned_expense_prev_year_by_kostenkonto' => $paidNotAssignedExpensePrevByKostenkonto,
            'paid_not_assigned_expense_next_year_by_kostenkonto' => $paidNotAssignedExpenseNextByKostenkonto,
            'paid_not_assigned_income_next_year_by_category' => $paidNotAssignedIncomeNextByCategory,
            'paid_not_assigned_income_next_year_gutschrift_by_kostenkonto' => $paidNotAssignedIncomeNextGutschriftByKostenkonto,
            'hausgeld_income_abj_by_category' => $abjIncomeByCategory,
            'hausgeld_expense_abj_by_category' => $abjExpenseByCategory,
            'hausgeld_income_abj_gutschrift_by_kostenkonto' => $abjIncomeGutschriftByKostenkonto,
            'hausgeld_expense_abj_by_kostenkonto' => $abjExpenseByKostenkonto,
        ];
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

    /**
     * Find payments with flexible filtering including date range.
     *
     * @param array<string, mixed> $criteria
     *
     * @return Zahlung[]
     */
    public function findByFilters(array $criteria, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('z');

        // Apply simple criteria filters
        foreach ($criteria as $field => $value) {
            if (null !== $value && '' !== $value) {
                $qb->andWhere("z.{$field} = :{$field}")
                   ->setParameter($field, $value);
            }
        }

        // Apply date range filter
        if (null !== $startDate) {
            $qb->andWhere('z.datum >= :startDate')
               ->setParameter('startDate', $startDate);
        }
        if (null !== $endDate) {
            $qb->andWhere('z.datum <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return $qb->orderBy('z.datum', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find Zahlungen by WEG and date range (for Vermögensabgrenzung).
     *
     * Includes both:
     * - Unit-specific payments (filtered by eigentuemer.weg)
     * - WEG-wide payments (eigentuemer IS NULL)
     *
     * @return Zahlung[]
     */
    public function findByWegAndDateRange(\App\Entity\Weg $weg, \DateTimeInterface $startDate, \DateTimeInterface $endDate, string $bankkontoTyp = 'hausgeld'): array
    {
        return $this->createQueryBuilder('z')
            ->leftJoin('z.eigentuemer', 'we')
            ->where('we.weg = :weg OR z.eigentuemer IS NULL')
            ->andWhere('z.datum >= :startDate')
            ->andWhere('z.datum <= :endDate')
            ->andWhere('z.bankkontoTyp = :bankkontoTyp')
            ->andWhere('z.isSimulation = false')
            ->setParameter('weg', $weg)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('bankkontoTyp', $bankkontoTyp)
            ->orderBy('z.datum', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function normalizeKostenkontoLabel(?string $label): ?string
    {
        if (null === $label) {
            return null;
        }
        $label = preg_replace('/^\s*\d+\s*/', '', $label);
        $label = mb_trim($label ?? '');

        return '' === $label ? null : $label;
    }
}
