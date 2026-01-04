<?php

namespace App\DataFixtures\SystemConfig;

use App\Entity\Umlageschluessel;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class UmlageschluesselFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $umlageschluessel = [
            [
                'schluessel' => '01*',
                'bezeichnung' => 'ext. berechn. Heiz-/Wasserkosten',
                'beschreibung' => 'Extern berechnete Heiz- und Wasserkosten nach Festbetrag',
            ],
            [
                'schluessel' => '02*',
                'bezeichnung' => 'Selbstverwaltung',
                'beschreibung' => 'Spezialverteilung für Selbstverwaltung',
            ],
            [
                'schluessel' => '03*',
                'bezeichnung' => 'Anzahl Einheit',
                'beschreibung' => 'Anteilig nach Anzahl Einheiten',
            ],
            [
                'schluessel' => '04*',
                'bezeichnung' => 'Festumlage',
                'beschreibung' => 'Festumlage nach Festbetrag',
            ],
            [
                'schluessel' => '05*',
                'bezeichnung' => 'Miteigentumsanteil',
                'beschreibung' => 'Anteilig nach Miteigentumsanteilen (MEA)',
            ],
            [
                'schluessel' => '06*',
                'bezeichnung' => 'Hebeanlage',
                'beschreibung' => 'Spezialverteilung für Hebeanlage (2/6 für 001/002, 1/6 für 003/004)',
            ],
        ];

        foreach ($umlageschluessel as $data) {
            $entity = new Umlageschluessel();
            $entity->setSchluessel($data['schluessel']);
            $entity->setBezeichnung($data['bezeichnung']);
            $entity->setBeschreibung($data['beschreibung']);

            $manager->persist($entity);

            // Add reference for other fixtures
            $this->addReference('umlageschluessel-' . $data['schluessel'], $entity);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['system-config', 'demo-data', 'opensource'];
    }
}
