<?php

namespace App\DataFixtures\Demo;

use App\Entity\WegEinheit;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class DemoEinheitFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Demo WEG Musterhausen (4 units)
        $einheiten1 = [
            ['0001', 'EG links', 'Max Mustermann', 'Musterstraße 123, 12345 Musterstadt', '+49 123 456789', '190', null, '2/6', true],
            ['0002', 'EG rechts', 'Erika Beispiel', 'Musterstraße 123, 12345 Musterstadt', '+49 123 456790', '250', null, '2/6', false],
            ['0003', '1. OG links', 'Hans Schmidt', 'Beispielstraße 42, 12345 Musterstadt', '+49 123 456791', '290', null, '1/6', true],
            ['0004', '1. OG rechts', 'Anna Müller', 'Testweg 15, 12345 Musterstadt', '+49 123 456792', '270', null, '1/6', false],
        ];

        // Demo WEG Berlin (6 units)
        $einheiten2 = [
            ['0001', 'EG', 'Klaus Berlin', 'Beispielweg 456, 10115 Berlin', '+49 30 123456', '166', null, '1/6', true],
            ['0002', '1. OG links', 'Petra Wagner', 'Alexanderplatz 1, 10115 Berlin', '+49 30 123457', '166', null, '1/6', false],
            ['0003', '1. OG rechts', 'Thomas Fischer', 'Unter den Linden 20, 10115 Berlin', '+49 30 123458', '166', null, '1/6', true],
            ['0004', '2. OG links', 'Maria Lopez', 'Potsdamer Platz 5, 10115 Berlin', '+49 30 123459', '167', null, '1/6', false],
            ['0005', '2. OG rechts', 'Stefan Meyer', 'Friedrichstraße 100, 10115 Berlin', '+49 30 123460', '167', null, '1/6', true],
            ['0006', 'DG', 'Julia Weber', 'Kurfürstendamm 50, 10115 Berlin', '+49 30 123461', '168', null, '1/6', false],
        ];

        // Test WEG Hamburg (2 units)
        $einheiten3 = [
            ['0001', 'Wohnung A', 'Peter Hamburg', 'Teststraße 789, 20095 Hamburg', '+49 40 987654', '500', null, '1/2', true],
            ['0002', 'Wohnung B', 'Sabine Nordsee', 'Reeperbahn 1, 20095 Hamburg', '+49 40 987655', '500', null, '1/2', false],
        ];

        $allEinheiten = [
            ['demo-weg-1', $einheiten1],
            ['demo-weg-2', $einheiten2],
            ['demo-weg-3', $einheiten3],
        ];

        foreach ($allEinheiten as [$wegRef, $einheiten]) {
            $weg = $this->getReference($wegRef, \App\Entity\Weg::class);

            foreach ($einheiten as $index => $einheitData) {
                [$nummer, $bezeichnung, $miteigentuemer, $adresse, $telefon, $mea, $stimme, $hebeanlage, $hauptwohneinheit] = $einheitData;

                $einheit = new WegEinheit();
                $einheit->setWeg($weg);
                $einheit->setNummer($nummer);
                $einheit->setBezeichnung($bezeichnung);
                $einheit->setMiteigentuemer($miteigentuemer);
                $einheit->setAdresse($adresse);
                $einheit->setTelefon($telefon);
                $einheit->setMiteigentumsanteile($mea);
                $einheit->setStimme($stimme);
                $einheit->setHebeanlage($hebeanlage);
                $einheit->setHauptwohneinheit($hauptwohneinheit);

                $manager->persist($einheit);

                // Add reference for other fixtures
                $this->addReference('demo-einheit-' . $wegRef . '-' . $nummer, $einheit);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            DemoWegFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['demo-data', 'demo-only', 'opensource'];
    }
}
