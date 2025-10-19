<?php

namespace App\Repository;

use App\Entity\Dienstleister;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Dienstleister>
 */
class DienstleisterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dienstleister::class);
    }

    public function save(Dienstleister $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Dienstleister $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all Dienstleister excluding property owners (Eigentümer).
     *
     * @return array<Dienstleister>
     */
    public function findServiceProvidersOnly(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.artDienstleister != :eigentuemer OR d.artDienstleister IS NULL')
            ->setParameter('eigentuemer', 'Eigentümer')
            ->orderBy('d.bezeichnung', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
