<?php

namespace App\Repository;

use App\Entity\Umlageschluessel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Umlageschluessel>
 */
class UmlageschluesselRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Umlageschluessel::class);
    }

    public function findBySchluessel(string $schluessel): ?Umlageschluessel
    {
        return $this->findOneBy(['schluessel' => $schluessel]);
    }
}
