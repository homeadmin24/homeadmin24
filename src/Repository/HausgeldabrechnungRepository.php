<?php

namespace App\Repository;

use App\Entity\Hausgeldabrechnung;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Hausgeldabrechnung>
 */
class HausgeldabrechnungRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Hausgeldabrechnung::class);
    }
}
