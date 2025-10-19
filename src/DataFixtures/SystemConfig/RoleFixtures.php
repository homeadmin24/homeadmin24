<?php

namespace App\DataFixtures\SystemConfig;

use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class RoleFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $roles = [
            [
                'name' => 'ROLE_VIEWER',
                'display_name' => 'Betrachter',
                'description' => 'Nur-Lese-Zugriff auf alle Daten',
                'is_active' => true,
            ],
            [
                'name' => 'ROLE_ACCOUNTANT',
                'display_name' => 'Buchhalter',
                'description' => 'Finanzfokussierte Berechtigung für Zahlungen, Rechnungen und Berichte',
                'is_active' => true,
            ],
            [
                'name' => 'ROLE_PROPERTY_MANAGER',
                'display_name' => 'Hausverwaltung',
                'description' => 'Immobilien- und Dienstleisterverwaltung',
                'is_active' => true,
            ],
            [
                'name' => 'ROLE_WEG_ADMIN',
                'display_name' => 'WEG Administrator',
                'description' => 'Vollständige WEG-Datenverwaltung, Zahlungen, Rechnungen und Abrechnungen',
                'is_active' => true,
            ],
            [
                'name' => 'ROLE_SUPER_ADMIN',
                'display_name' => 'Super Administrator',
                'description' => 'Vollzugriff auf alle Systemfunktionen einschließlich Benutzerverwaltung',
                'is_active' => true,
            ],
        ];

        foreach ($roles as $roleData) {
            $role = new Role();
            $role->setName($roleData['name']);
            $role->setDisplayName($roleData['display_name']);
            $role->setDescription($roleData['description']);
            $role->setIsActive($roleData['is_active']);

            $manager->persist($role);

            // Add reference for other fixtures
            $this->addReference('role-' . $roleData['name'], $role);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['system-config', 'demo-data', 'opensource'];
    }
}
