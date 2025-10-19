<?php

namespace App\Repository;

use App\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    /**
     * Find all active roles.
     *
     * @return array<Role>
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('r.displayName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find role by name.
     */
    public function findByName(string $name): ?Role
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get role hierarchy for permissions.
     *
     * @return array<string, string>
     */
    public function getRoleHierarchy(): array
    {
        $roles = $this->findActive();
        $hierarchy = [];

        foreach ($roles as $role) {
            $hierarchy[$role->getName()] = $role->getDisplayName();
        }

        return $hierarchy;
    }
}
