<?php

namespace App\Repository;

use App\Entity\Rechnung;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rechnung>
 */
class RechnungRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rechnung::class);
    }

    /**
     * @return array<int, array{
     *   id: int,
     *   amount: string|null,
     *   dueDate: \DateTimeInterface|null,
     *   serviceDate: \DateTimeInterface|null,
     *   outstanding: bool|null,
     *   docYear: int|null,
     *   docUpload: \DateTimeInterface|null,
     *   paymentDate: \DateTimeInterface|null
     * }>
     */
    public function getInvoiceStatusRowsForWegYear(int $wegId): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.id AS id')
            ->addSelect('r.betragMitSteuern AS amount')
            ->addSelect('r.faelligkeitsdatum AS dueDate')
            ->addSelect('r.datumLeistung AS serviceDate')
            ->addSelect('r.ausstehend AS outstanding')
            ->addSelect('MAX(d.abrechnungsJahr) AS docYear')
            ->addSelect('MAX(d.uploadDatum) AS docUpload')
            ->addSelect('MAX(z.datum) AS paymentDate')
            ->leftJoin('r.dokumente', 'd')
            ->leftJoin(\App\Entity\Zahlung::class, 'z', 'WITH', 'z.rechnung = r')
            ->leftJoin('z.eigentuemer', 'e')
            ->leftJoin('e.weg', 'w')
            ->where('(d.weg = :wegId OR w.id = :wegId)')
            ->groupBy('r.id')
            ->setParameter('wegId', $wegId)
            ->getQuery()
            ->getResult();
    }
}
