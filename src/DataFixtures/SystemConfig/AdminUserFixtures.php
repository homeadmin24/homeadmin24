<?php

namespace App\DataFixtures\SystemConfig;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminUserFixtures extends Fixture implements FixtureGroupInterface
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
        $admin->setRoles([User::ROLE_SUPER_ADMIN]);

        // Hash password: admin123
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin123');
        $admin->setPassword($hashedPassword);

        $manager->persist($admin);
        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['system-config', 'demo-data', 'opensource'];
    }
}
