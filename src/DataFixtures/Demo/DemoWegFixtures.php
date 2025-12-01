<?php

namespace App\DataFixtures\Demo;

use App\Entity\Weg;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class DemoWegFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $wegs = [
            [
                'bezeichnung' => 'Demo WEG Musterhausen',
                'adresse' => 'Musterstraße 123, 12345 Musterstadt',
            ],
            [
                'bezeichnung' => 'Beispiel WEG Berlin',
                'adresse' => 'Beispielweg 456, 10115 Berlin',
            ],
            [
                'bezeichnung' => 'Test WEG Hamburg',
                'adresse' => 'Teststraße 789, 20095 Hamburg',
            ],
        ];

        foreach ($wegs as $index => $wegData) {
            $weg = new Weg();
            $weg->setBezeichnung($wegData['bezeichnung']);
            $weg->setAdresse($wegData['adresse']);

            $manager->persist($weg);

            // Add reference for other fixtures
            $this->addReference('demo-weg-' . ($index + 1), $weg);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['demo-data', 'demo-only', 'opensource'];
    }
}
