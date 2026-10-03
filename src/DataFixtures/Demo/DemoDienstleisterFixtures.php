<?php

namespace App\DataFixtures\Demo;

use App\Entity\Dienstleister;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class DemoDienstleisterFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $dienstleister = [
            [
                'bezeichnung' => 'Mustermann Hausmeister GmbH',
                'art_dienstleister' => 'Hausmeister',
                'preis_pro_jahr' => '4800.00',
                'vertrag' => 'Hausmeistervertrag 2023-2025',
                'datum_inkrafttreten' => '2023-01-01',
                'vertragsreferenz' => 'HM-2023-001',
            ],
            [
                'bezeichnung' => 'Beispiel Heizungsservice',
                'art_dienstleister' => 'Heizungstechnik',
                'preis_pro_jahr' => '2400.00',
                'vertrag' => 'Wartungsvertrag Heizung',
                'datum_inkrafttreten' => '2024-01-01',
                'vertragsreferenz' => 'HZ-2024-001',
            ],
            [
                'bezeichnung' => 'Demo Versicherungen AG',
                'art_dienstleister' => 'Versicherung',
                'preis_pro_jahr' => '1200.00',
                'vertrag' => 'Gebäudeversicherung 2024',
                'datum_inkrafttreten' => '2024-01-01',
                'vertragsreferenz' => 'VER-2024-001',
            ],
            [
                'bezeichnung' => 'Test Elektro Service',
                'art_dienstleister' => 'Elektrik',
                'preis_pro_jahr' => null,
                'vertrag' => null,
                'datum_inkrafttreten' => null,
                'vertragsreferenz' => null,
            ],
            [
                'bezeichnung' => 'Beispiel Schornsteinfeger',
                'art_dienstleister' => 'Schornsteinfeger',
                'preis_pro_jahr' => '300.00',
                'vertrag' => 'Kehrordnung 2024',
                'datum_inkrafttreten' => '2024-01-01',
                'vertragsreferenz' => 'SF-2024-001',
            ],
            [
                'bezeichnung' => 'Demo Gartenpflege GmbH',
                'art_dienstleister' => 'Gartenpflege',
                'preis_pro_jahr' => '1800.00',
                'vertrag' => 'Gartenvertrag Saison 2024',
                'datum_inkrafttreten' => '2024-04-01',
                'vertragsreferenz' => 'GP-2024-001',
            ],
            [
                'bezeichnung' => 'Test Reinigungsservice',
                'art_dienstleister' => 'Reinigung',
                'preis_pro_jahr' => null,
                'vertrag' => null,
                'datum_inkrafttreten' => null,
                'vertragsreferenz' => null,
            ],
            [
                'bezeichnung' => 'Muster Aufzugsservice',
                'art_dienstleister' => 'Aufzugstechnik',
                'preis_pro_jahr' => '600.00',
                'vertrag' => 'Wartungsvertrag Aufzug',
                'datum_inkrafttreten' => '2024-01-01',
                'vertragsreferenz' => 'AZ-2024-001',
            ],
        ];

        foreach ($dienstleister as $index => $data) {
            $entity = new Dienstleister();
            $entity->setBezeichnung($data['bezeichnung']);
            $entity->setArtDienstleister($data['art_dienstleister']);
            $entity->setPreisProJahr($data['preis_pro_jahr']);

            if ($data['vertrag']) {
                $entity->setVertrag($data['vertrag']);
            }
            if ($data['datum_inkrafttreten']) {
                $entity->setDatumInkrafttreten(new \DateTime($data['datum_inkrafttreten']));
            }
            if ($data['vertragsreferenz']) {
                $entity->setVertragsreferenz($data['vertragsreferenz']);
            }

            $manager->persist($entity);

            // Add reference for other fixtures
            $this->addReference('demo-dienstleister-' . ($index + 1), $entity);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['demo-data', 'demo-only', 'opensource'];
    }
}
