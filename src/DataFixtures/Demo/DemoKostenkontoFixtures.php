<?php

namespace App\DataFixtures\Demo;

use App\Entity\KategorisierungsTyp;
use App\Entity\Kostenkonto;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class DemoKostenkontoFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $kostenkontos = [
            // Basic accounts for demo
            ['047000', 'Hebeanlage', KategorisierungsTyp::NICHT_UMLAGEFAEHIG, true],
            ['040100', 'Hausmeisterkosten', KategorisierungsTyp::UMLAGEFAEHIG_SONSTIGE, true],
            ['050000', 'Verwaltervergütung', KategorisierungsTyp::NICHT_UMLAGEFAEHIG, true],
            ['046000', 'Versicherung: Gebäude', KategorisierungsTyp::UMLAGEFAEHIG_SONSTIGE, true],
            ['043000', 'Allgemeinstrom', KategorisierungsTyp::UMLAGEFAEHIG_SONSTIGE, true],
            ['043200', 'Müllentsorgung', KategorisierungsTyp::UMLAGEFAEHIG_SONSTIGE, true],
        ];

        foreach ($kostenkontos as [$nummer, $bezeichnung, $kategorisierungsTyp, $isActive]) {
            $kostenkonto = new Kostenkonto();
            $kostenkonto->setNummer($nummer);
            $kostenkonto->setBezeichnung($bezeichnung);
            $kostenkonto->setKategorisierungsTyp($kategorisierungsTyp);
            $kostenkonto->setIsActive($isActive);

            $manager->persist($kostenkonto);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['demo-data', 'demo-only', 'opensource'];
    }
}
