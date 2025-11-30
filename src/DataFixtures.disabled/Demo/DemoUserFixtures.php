<?php

namespace App\DataFixtures\Demo;

use App\DataFixtures\SystemConfig\RoleFixtures;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DemoUserFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Use environment variable for demo password, fallback to secure default
        $demoPassword = $_ENV['DEMO_PASSWORD'] ?? 'ChangeMe123!';

        $demoUsers = [
            [
                'email' => 'viewer@demo.local',
                'firstName' => 'Demo',
                'lastName' => 'Betrachter',
                'password' => $demoPassword,
                'roles' => ['ROLE_VIEWER'],
            ],
            [
                'email' => 'buchhalter@demo.local',
                'firstName' => 'Demo',
                'lastName' => 'Buchhalter',
                'password' => $demoPassword,
                'roles' => ['ROLE_ACCOUNTANT'],
            ],
            [
                'email' => 'hausverwaltung@demo.local',
                'firstName' => 'Demo',
                'lastName' => 'Hausverwaltung',
                'password' => $demoPassword,
                'roles' => ['ROLE_PROPERTY_MANAGER'],
            ],
            [
                'email' => 'wegadmin@demo.local',
                'firstName' => 'Demo',
                'lastName' => 'WEG-Administrator',
                'password' => $demoPassword,
                'roles' => ['ROLE_WEG_ADMIN'],
            ],
            [
                'email' => 'testmanager@demo.local',
                'firstName' => 'Test',
                'lastName' => 'Manager',
                'password' => $demoPassword,
                'roles' => ['ROLE_PROPERTY_MANAGER', 'ROLE_ACCOUNTANT'],
            ],
        ];

        foreach ($demoUsers as $userData) {
            $user = new User();
            $user->setEmail($userData['email']);
            $user->setFirstName($userData['firstName']);
            $user->setLastName($userData['lastName']);
            $user->setIsActive(true);

            // Hash password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $userData['password']);
            $user->setPassword($hashedPassword);

            // Add roles (lookup from database if available)
            foreach ($userData['roles'] as $roleName) {
                try {
                    // Try to get reference first (if loaded in same session)
                    $role = $this->getReference('role-' . $roleName, \App\Entity\Role::class);
                } catch (\Exception $e) {
                    // If reference doesn't exist, look up from database
                    $role = $manager->getRepository(\App\Entity\Role::class)->findOneBy(['name' => $roleName]);
                    if (!$role) {
                        throw new \Exception("Role $roleName not found. Please load system-config fixtures first.");
                    }
                }
                $user->addUserRole($role);
            }

            $manager->persist($user);
        }

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
        return ['demo-data', 'demo-only', 'opensource'];
    }
}
