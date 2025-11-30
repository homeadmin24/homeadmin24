<?php

declare(strict_types=1);

namespace App\DataFixtures\Demo;

use App\Entity\Dienstleister;
use App\Entity\Kostenkonto;
use App\Entity\WegEinheit;
use App\Entity\Zahlung;
use App\Entity\Zahlungskategorie;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class DemoZahlungFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            DemoWegFixtures::class,
            DemoEinheitFixtures::class,
            DemoDienstleisterFixtures::class,
            DemoKostenkontoFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        // Get Zahlungskategorien
        $kategorieRepo = $manager->getRepository(Zahlungskategorie::class);
        $hausgeldzahlung = $kategorieRepo->findOneBy(['name' => 'Hausgeld-Zahlung']);
        $rechnungDienstleister = $kategorieRepo->findOneBy(['name' => 'Rechnung von Dienstleister']);
        $direktbuchung = $kategorieRepo->findOneBy(['name' => 'Direktbuchung Kostenkonto']);

        // Get Kostenkontos
        $kostenkontoRepo = $manager->getRepository(Kostenkonto::class);
        $hausmeister = $kostenkontoRepo->findOneBy(['nummer' => '040100']);
        $verwaltung = $kostenkontoRepo->findOneBy(['nummer' => '050000']);
        $versicherung = $kostenkontoRepo->findOneBy(['nummer' => '046000']);
        $allgemeinstrom = $kostenkontoRepo->findOneBy(['nummer' => '043000']);
        $muell = $kostenkontoRepo->findOneBy(['nummer' => '043200']);

        // Create payments for 2024 and 2025
        $this->createHausgeldzahlungen2024($manager, $hausgeldzahlung);
        $this->createHausgeldzahlungen2025($manager, $hausgeldzahlung);
        $this->createDienstleisterRechnungen2024($manager, $rechnungDienstleister, $hausmeister, $verwaltung, $versicherung);
        $this->createDienstleisterRechnungen2025($manager, $rechnungDienstleister, $hausmeister, $verwaltung);
        $this->createDirektbuchungen2024($manager, $direktbuchung, $allgemeinstrom, $muell);
        $this->createDirektbuchungen2025($manager, $direktbuchung, $allgemeinstrom, $muell);

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['demo-data', 'demo-only', 'opensource'];
    }

    private function createHausgeldzahlungen2024(ObjectManager $manager, ?Zahlungskategorie $kategorie): void
    {
        if (!$kategorie) {
            return;
        }

        $einheitRepo = $manager->getRepository(WegEinheit::class);

        // Demo WEG Musterhausen - 4 units with monthly payments
        $einheiten = $einheitRepo->findBy(['weg' => $this->getReference('demo-weg-1', \App\Entity\Weg::class)]);
        $monatlicheBetraege = [285.00, 375.00, 435.00, 405.00]; // Different amounts per unit

        foreach ($einheiten as $index => $einheit) {
            $betrag = $monatlicheBetraege[$index] ?? 300.00;

            // Create monthly payments for 2024 (Jan-Dec)
            for ($monat = 1; $monat <= 12; ++$monat) {
                $zahlung = new Zahlung();
                $zahlung->setDatum(new \DateTime(\sprintf('2024-%02d-05', $monat)));
                $zahlung->setBezeichnung(\sprintf('Hausgeld %02d/2024', $monat));
                $zahlung->setBetrag((string) $betrag);
                $zahlung->setHauptkategorie($kategorie);
                $zahlung->setEigentuemer($einheit);
                $zahlung->setAbrechnungsjahrZuordnung(2024);

                $manager->persist($zahlung);
            }
        }
    }

    private function createHausgeldzahlungen2025(ObjectManager $manager, ?Zahlungskategorie $kategorie): void
    {
        if (!$kategorie) {
            return;
        }

        $einheitRepo = $manager->getRepository(WegEinheit::class);

        // Demo WEG Musterhausen - payments for 2025 (Jan-current month)
        $einheiten = $einheitRepo->findBy(['weg' => $this->getReference('demo-weg-1', \App\Entity\Weg::class)]);
        $monatlicheBetraege = [285.00, 375.00, 435.00, 405.00];

        $currentMonth = (int) date('n');

        foreach ($einheiten as $index => $einheit) {
            $betrag = $monatlicheBetraege[$index] ?? 300.00;

            // Create monthly payments for 2025 up to current month
            for ($monat = 1; $monat <= $currentMonth; ++$monat) {
                $zahlung = new Zahlung();
                $zahlung->setDatum(new \DateTime(\sprintf('2025-%02d-05', $monat)));
                $zahlung->setBezeichnung(\sprintf('Hausgeld %02d/2025', $monat));
                $zahlung->setBetrag((string) $betrag);
                $zahlung->setHauptkategorie($kategorie);
                $zahlung->setEigentuemer($einheit);
                $zahlung->setAbrechnungsjahrZuordnung(2025);

                $manager->persist($zahlung);
            }
        }
    }

    private function createDienstleisterRechnungen2024(
        ObjectManager $manager,
        ?Zahlungskategorie $kategorie,
        ?Kostenkonto $hausmeister,
        ?Kostenkonto $verwaltung,
        ?Kostenkonto $versicherung,
    ): void {
        if (!$kategorie) {
            return;
        }

        $hausmeisterDl = $this->getReference('demo-dienstleister-1', Dienstleister::class);
        $heizungDl = $this->getReference('demo-dienstleister-2', Dienstleister::class);
        $versicherungDl = $this->getReference('demo-dienstleister-3', Dienstleister::class);

        $rechnungen = [
            // Hausmeister - monthly invoices
            ['2024-01-15', 'Hausmeister Januar 2024', -400.00, $hausmeisterDl, $hausmeister, 76.00, 324.00],
            ['2024-02-15', 'Hausmeister Februar 2024', -400.00, $hausmeisterDl, $hausmeister, 76.00, 324.00],
            ['2024-03-15', 'Hausmeister März 2024', -400.00, $hausmeisterDl, $hausmeister, 76.00, 324.00],
            ['2024-04-15', 'Hausmeister April 2024', -400.00, $hausmeisterDl, $hausmeister, 76.00, 324.00],
            ['2024-05-15', 'Hausmeister Mai 2024', -400.00, $hausmeisterDl, $hausmeister, 76.00, 324.00],
            ['2024-06-15', 'Hausmeister Juni 2024', -400.00, $hausmeisterDl, $hausmeister, 76.00, 324.00],
            ['2024-07-15', 'Hausmeister Juli 2024', -400.00, $hausmeisterDl, $hausmeister, 76.00, 324.00],
            ['2024-08-15', 'Hausmeister August 2024', -400.00, $hausmeisterDl, $hausmeister, 76.00, 324.00],
            ['2024-09-15', 'Hausmeister September 2024', -400.00, $hausmeisterDl, $hausmeister, 76.00, 324.00],
            ['2024-10-15', 'Hausmeister Oktober 2024', -400.00, $hausmeisterDl, $hausmeister, 76.00, 324.00],
            ['2024-11-15', 'Hausmeister November 2024', -400.00, $hausmeisterDl, $hausmeister, 76.00, 324.00],
            ['2024-12-15', 'Hausmeister Dezember 2024', -400.00, $hausmeisterDl, $hausmeister, 76.00, 324.00],

            // Versicherung - annual
            ['2024-01-20', 'Gebäudeversicherung 2024', -1200.00, $versicherungDl, $versicherung, null, null],

            // Verwaltung - quarterly
            ['2024-03-31', 'Hausverwaltung Q1 2024', -1035.00, null, $verwaltung, null, null],
            ['2024-06-30', 'Hausverwaltung Q2 2024', -1035.00, null, $verwaltung, null, null],
            ['2024-09-30', 'Hausverwaltung Q3 2024', -540.00, null, $verwaltung, null, null],
            ['2024-12-31', 'Hausverwaltung Q4 2024', -540.00, null, $verwaltung, null, null],
        ];

        foreach ($rechnungen as $data) {
            [$datum, $bezeichnung, $betrag, $dienstleister, $kostenkonto, $mwst, $hnd] = $data;

            $zahlung = new Zahlung();
            $zahlung->setDatum(new \DateTime($datum));
            $zahlung->setBezeichnung($bezeichnung);
            $zahlung->setBetrag((string) $betrag);
            $zahlung->setHauptkategorie($kategorie);
            $zahlung->setKostenkonto($kostenkonto);
            $zahlung->setDienstleister($dienstleister);
            $zahlung->setAbrechnungsjahrZuordnung(2024);

            if (null !== $mwst) {
                $zahlung->setGesamtMwSt((string) $mwst);
            }
            if (null !== $hnd) {
                $zahlung->setHndAnteil((string) $hnd);
            }

            $manager->persist($zahlung);
        }
    }

    private function createDienstleisterRechnungen2025(
        ObjectManager $manager,
        ?Zahlungskategorie $kategorie,
        ?Kostenkonto $hausmeister,
        ?Kostenkonto $verwaltung,
    ): void {
        if (!$kategorie) {
            return;
        }

        $hausmeisterDl = $this->getReference('demo-dienstleister-1', Dienstleister::class);

        $currentMonth = (int) date('n');
        $rechnungen = [];

        // Hausmeister - monthly invoices up to current month
        for ($monat = 1; $monat <= $currentMonth; ++$monat) {
            $rechnungen[] = [
                \sprintf('2025-%02d-15', $monat),
                \sprintf('Hausmeister %s 2025', $this->getMonthName($monat)),
                -400.00,
                $hausmeisterDl,
                $hausmeister,
                76.00,
                324.00,
            ];
        }

        // Verwaltung - quarterly (if month >= 3, 6, 9, 12)
        if ($currentMonth >= 3) {
            $rechnungen[] = ['2025-03-31', 'Hausverwaltung Q1 2025', -540.00, null, $verwaltung, null, null];
        }
        if ($currentMonth >= 6) {
            $rechnungen[] = ['2025-06-30', 'Hausverwaltung Q2 2025', -540.00, null, $verwaltung, null, null];
        }
        if ($currentMonth >= 9) {
            $rechnungen[] = ['2025-09-30', 'Hausverwaltung Q3 2025', -540.00, null, $verwaltung, null, null];
        }
        if ($currentMonth >= 12) {
            $rechnungen[] = ['2025-12-31', 'Hausverwaltung Q4 2025', -540.00, null, $verwaltung, null, null];
        }

        foreach ($rechnungen as $data) {
            [$datum, $bezeichnung, $betrag, $dienstleister, $kostenkonto, $mwst, $hnd] = $data;

            $zahlung = new Zahlung();
            $zahlung->setDatum(new \DateTime($datum));
            $zahlung->setBezeichnung($bezeichnung);
            $zahlung->setBetrag((string) $betrag);
            $zahlung->setHauptkategorie($kategorie);
            $zahlung->setKostenkonto($kostenkonto);
            $zahlung->setDienstleister($dienstleister);
            $zahlung->setAbrechnungsjahrZuordnung(2025);

            if (null !== $mwst) {
                $zahlung->setGesamtMwSt((string) $mwst);
            }
            if (null !== $hnd) {
                $zahlung->setHndAnteil((string) $hnd);
            }

            $manager->persist($zahlung);
        }
    }

    private function createDirektbuchungen2024(
        ObjectManager $manager,
        ?Zahlungskategorie $kategorie,
        ?Kostenkonto $allgemeinstrom,
        ?Kostenkonto $muell,
    ): void {
        if (!$kategorie) {
            return;
        }

        $buchungen = [
            // Allgemeinstrom - quarterly
            ['2024-03-15', 'Allgemeinstrom Q1 2024', -150.00, $allgemeinstrom],
            ['2024-06-15', 'Allgemeinstrom Q2 2024', -180.00, $allgemeinstrom],
            ['2024-09-15', 'Allgemeinstrom Q3 2024', -160.00, $allgemeinstrom],
            ['2024-12-15', 'Allgemeinstrom Q4 2024', -170.00, $allgemeinstrom],

            // Müllentsorgung - quarterly
            ['2024-03-01', 'Müllentsorgung AWM Q1 2024', -175.00, $muell],
            ['2024-06-01', 'Müllentsorgung AWM Q2 2024', -175.00, $muell],
            ['2024-09-01', 'Müllentsorgung AWM Q3 2024', -175.00, $muell],
            ['2024-12-01', 'Müllentsorgung AWM Q4 2024', -175.00, $muell],
        ];

        foreach ($buchungen as $data) {
            [$datum, $bezeichnung, $betrag, $kostenkonto] = $data;

            $zahlung = new Zahlung();
            $zahlung->setDatum(new \DateTime($datum));
            $zahlung->setBezeichnung($bezeichnung);
            $zahlung->setBetrag((string) $betrag);
            $zahlung->setHauptkategorie($kategorie);
            $zahlung->setKostenkonto($kostenkonto);
            $zahlung->setAbrechnungsjahrZuordnung(2024);

            $manager->persist($zahlung);
        }
    }

    private function createDirektbuchungen2025(
        ObjectManager $manager,
        ?Zahlungskategorie $kategorie,
        ?Kostenkonto $allgemeinstrom,
        ?Kostenkonto $muell,
    ): void {
        if (!$kategorie) {
            return;
        }

        $currentMonth = (int) date('n');
        $buchungen = [];

        // Allgemeinstrom - quarterly (Mar, Jun, Sep, Dec)
        if ($currentMonth >= 3) {
            $buchungen[] = ['2025-03-15', 'Allgemeinstrom Q1 2025', -155.00, $allgemeinstrom];
        }
        if ($currentMonth >= 6) {
            $buchungen[] = ['2025-06-15', 'Allgemeinstrom Q2 2025', -165.00, $allgemeinstrom];
        }
        if ($currentMonth >= 9) {
            $buchungen[] = ['2025-09-15', 'Allgemeinstrom Q3 2025', -170.00, $allgemeinstrom];
        }
        if ($currentMonth >= 12) {
            $buchungen[] = ['2025-12-15', 'Allgemeinstrom Q4 2025', -160.00, $allgemeinstrom];
        }

        // Müllentsorgung - quarterly
        if ($currentMonth >= 3) {
            $buchungen[] = ['2025-03-01', 'Müllentsorgung AWM Q1 2025', -180.00, $muell];
        }
        if ($currentMonth >= 6) {
            $buchungen[] = ['2025-06-01', 'Müllentsorgung AWM Q2 2025', -180.00, $muell];
        }
        if ($currentMonth >= 9) {
            $buchungen[] = ['2025-09-01', 'Müllentsorgung AWM Q3 2025', -180.00, $muell];
        }
        if ($currentMonth >= 12) {
            $buchungen[] = ['2025-12-01', 'Müllentsorgung AWM Q4 2025', -180.00, $muell];
        }

        foreach ($buchungen as $data) {
            [$datum, $bezeichnung, $betrag, $kostenkonto] = $data;

            $zahlung = new Zahlung();
            $zahlung->setDatum(new \DateTime($datum));
            $zahlung->setBezeichnung($bezeichnung);
            $zahlung->setBetrag((string) $betrag);
            $zahlung->setHauptkategorie($kategorie);
            $zahlung->setKostenkonto($kostenkonto);
            $zahlung->setAbrechnungsjahrZuordnung(2025);

            $manager->persist($zahlung);
        }
    }

    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];

        return $months[$month] ?? 'Monat';
    }
}
