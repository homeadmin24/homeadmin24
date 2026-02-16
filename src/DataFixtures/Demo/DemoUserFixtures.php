<?php

namespace App\DataFixtures\Demo;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DemoUserFixtures extends Fixture implements FixtureGroupInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Use environment variable for demo password, fallback to demo default
        $demoPassword = $_ENV['DEMO_PASSWORD'] ?? 'demo123';

        $demoUsers = [
            [
                'email' => 'viewer@demo.local',
                'firstName' => 'Demo',
                'lastName' => 'Betrachter',
                'password' => $demoPassword,
                'roles' => [User::ROLE_VIEWER],
            ],
            [
                'email' => 'buchhalter@demo.local',
                'firstName' => 'Demo',
                'lastName' => 'Buchhalter',
                'password' => $demoPassword,
                'roles' => [User::ROLE_ACCOUNTANT],
            ],
            [
                'email' => 'hausverwaltung@demo.local',
                'firstName' => 'Demo',
                'lastName' => 'Hausverwaltung',
                'password' => $demoPassword,
                'roles' => [User::ROLE_PROPERTY_MANAGER],
            ],
            [
                'email' => 'wegadmin@demo.local',
                'firstName' => 'Demo',
                'lastName' => 'WEG-Administrator',
                'password' => $demoPassword,
                'roles' => [User::ROLE_WEG_ADMIN],
            ],
            [
                'email' => 'testmanager@demo.local',
                'firstName' => 'Test',
                'lastName' => 'Manager',
                'password' => $demoPassword,
                'roles' => [User::ROLE_PROPERTY_MANAGER, User::ROLE_ACCOUNTANT],
            ],
        ];

        foreach ($demoUsers as $userData) {
            $user = new User();
            $user->setEmail($userData['email']);
            $user->setFirstName($userData['firstName']);
            $user->setLastName($userData['lastName']);
            $user->setIsActive(true);
            $user->setRoles($userData['roles']);

            // Hash password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $userData['password']);
            $user->setPassword($hashedPassword);

            $manager->persist($user);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['demo-data', 'demo-only', 'opensource'];
    }
}
