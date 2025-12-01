<?php

namespace App\DataFixtures\SystemConfig;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminUserFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create system administrator
        $admin = new User();
        $admin->setEmail('admin@hausman.local');
        $admin->setFirstName('System');
        $admin->setLastName('Administrator');
        $admin->setIsActive(true);

        // Hash password: admin123
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin123');
        $admin->setPassword($hashedPassword);

        // Add SUPER_ADMIN role
        $superAdminRole = $this->getReference('role-ROLE_SUPER_ADMIN', \App\Entity\Role::class);
        $admin->addUserRole($superAdminRole);

        $manager->persist($admin);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            RoleFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['system-config', 'demo-data', 'opensource'];
    }
}
