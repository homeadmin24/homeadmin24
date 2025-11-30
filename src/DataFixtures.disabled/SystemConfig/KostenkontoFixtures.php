<?php

namespace App\DataFixtures\SystemConfig;

use App\Entity\KategorisierungsTyp;
use App\Entity\Kostenkonto;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class KostenkontoFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $konten = [
            // Active accounts based on real data
            // Format: [nummer, bezeichnung, kategorisierungsTyp, umlageschluesselKey, isActive, taxDeductible]
            ['040100', 'Hausmeisterkosten', 'umlagefaehig_sonstige', '05*', true, true],
            ['040101', 'Hausmeister-Sonderleistungen', 'nicht_umlagefaehig', '05*', true, true],
            ['041100', 'Schornsteinfeger', 'nicht_umlagefaehig', '05*', false, true], // Excluded via isActive=false
            ['041300', 'Wartung Heizung', 'nicht_umlagefaehig', '05*', false, false], // Excluded via isActive=false
            ['041400', 'Heizungs-Reparaturen', 'nicht_umlagefaehig', '05*', true, false],
            ['042111', 'Wasseranalytik', 'umlagefaehig_sonstige', '05*', true, true],
            ['043000', 'Allgemeinstrom', 'umlagefaehig_sonstige', '05*', true, false],
            ['043100', 'Gas', 'nicht_umlagefaehig', '05*', false, false], // Excluded via isActive=false
            ['043200', 'Müllentsorgung', 'umlagefaehig_sonstige', '05*', true, false],
            ['043400', 'Abwasser/Kanalgebühren', 'nicht_umlagefaehig', '05*', false, false], // Excluded via isActive=false
            ['043600', 'CO2-Kosten (nicht umlagefähig)', 'nicht_umlagefaehig', '04*', true, false],
            ['044000', 'Brandschutz', 'nicht_umlagefaehig', '05*', true, false],
            ['045100', 'Laufende Instandhaltung', 'nicht_umlagefaehig', '05*', true, true],
            ['046000', 'Versicherung: Gebäude', 'umlagefaehig_sonstige', '05*', true, false],
            ['046200', 'Versicherung: Haftpflicht', 'umlagefaehig_sonstige', '05*', true, false],
            ['047000', 'Hebeanlage', 'nicht_umlagefaehig', '06*', true, true],
            ['048100', 'Mahngebühren WEG', 'nicht_umlagefaehig', '05*', true, false],
            ['049000', 'Nebenkosten Geldverkehr WEG-Konto', 'nicht_umlagefaehig', '05*', true, false],
            ['050000', 'Verwaltervergütung', 'nicht_umlagefaehig', '03*', true, false],
            ['052000', 'Beiratsvergütung', 'nicht_umlagefaehig', '05*', true, false],
            ['053100', 'Vorjahresabschlüsse', 'nicht_umlagefaehig', '05*', false, false], // Excluded via isActive=false
            ['054000', 'Rücklagenzuführung', 'ruecklagenzufuehrung', '05*', true, false],
            ['099900', 'Wohngeld', 'nicht_umlagefaehig', '05*', false, false], // Excluded via isActive=false

            // Additional standard accounts (inactive by default, no taxDeductible)
            ['001000', 'Laufende öffentliche Lasten des Grundstücks', 'umlagefaehig_sonstige', '05*', false, false],
            ['004000', 'Heizkosten', 'umlagefaehig_sonstige', '05*', false, false],
            ['005000', 'Kosten für Warmwasser', 'umlagefaehig_sonstige', '05*', false, false],
            ['006000', 'Kosten für verbundene Heizungs- und Warmwasserversorgungsanlagen', 'umlagefaehig_sonstige', '05*', false, false],
            ['007000', 'Die Kosten für den Aufzug', 'umlagefaehig_sonstige', '05*', false, false],
            ['013000', 'Beiträge für die Sach- und Haftpflichtversicherung', 'umlagefaehig_sonstige', '05*', false, false],
            ['016000', 'Kosten einer maschinellen Wascheinrichtung', 'umlagefaehig_sonstige', '05*', false, false],
            ['017000', 'Sonstige Betriebskosten', 'nicht_umlagefaehig', '05*', false, false],
            ['040200', 'Hausmeistergehalt', 'umlagefaehig_sonstige', '05*', false, true],
            ['040300', 'Reinigungskosten', 'umlagefaehig_sonstige', '05*', false, true],
            ['040400', 'Gartenarbeiten', 'umlagefaehig_sonstige', '05*', false, true],
            ['040500', 'Winterdienst', 'umlagefaehig_sonstige', '05*', false, true],
            ['041000', 'Brennstoffkosten', 'umlagefaehig_sonstige', '05*', false, false],
            ['041200', 'Emissionsmessung', 'umlagefaehig_sonstige', '05*', false, false],
            ['041500', 'Miete Heizungszähler', 'nicht_umlagefaehig', '05*', false, false], // Excluded via isActive=false
            ['041600', 'Miete Kaltwasserzähler', 'umlagefaehig_sonstige', '05*', false, false],
            ['041700', 'Miete Warmwasserzähler', 'umlagefaehig_sonstige', '05*', false, false],
            ['041750', 'CO²-Kosten (nicht umlagefähig)', 'nicht_umlagefaehig', '05*', false, false],
            ['041800', 'Servicekosten-Heizkostenabrechnung', 'umlagefaehig_sonstige', '05*', false, false],
            ['041801', 'Servicekosten-Wasserabrechnung', 'umlagefaehig_sonstige', '05*', false, false],
            ['041805', 'Rauchwarnmelder', 'nicht_umlagefaehig', '05*', false, false],
            ['042000', 'Wasser allgemein', 'umlagefaehig_sonstige', '05*', false, false],
            ['042100', 'Trinkwasser', 'umlagefaehig_sonstige', '05*', false, false],
            ['042200', 'Abwasser', 'umlagefaehig_sonstige', '05*', false, false],
            ['042300', 'Niederschlagswasser', 'umlagefaehig_sonstige', '05*', false, false],
            ['043010', 'Beleuchtung', 'umlagefaehig_sonstige', '05*', false, false],
            ['043020', 'Strom für Heizung', 'umlagefaehig_sonstige', '05*', false, false],
            ['043150', 'Kabel-TV', 'umlagefaehig_sonstige', '05*', false, false],
            ['043300', 'Straßenreinigung', 'umlagefaehig_sonstige', '05*', false, false],
            ['044200', 'Strom für Aufzug', 'umlagefaehig_sonstige', '05*', false, false],
            ['044300', 'Wartung Aufzug', 'umlagefaehig_sonstige', '05*', false, true],
            ['044400', 'Nottelefon Aufzug', 'umlagefaehig_sonstige', '05*', false, false],
            ['044500', 'Reparatur Aufzug', 'nicht_umlagefaehig', '05*', false, true],
            ['045000', 'Grundsteuer', 'umlagefaehig_sonstige', '05*', false, false],
            ['048000', 'Rücklastschrift-Gebühren', 'nicht_umlagefaehig', '05*', false, false],
            ['048200', 'Rechts- und Beratungskosten', 'nicht_umlagefaehig', '05*', false, false],
            ['049200', 'Abgeltungssteuer WEG-Konto', 'nicht_umlagefaehig', '05*', false, false],
            ['049300', 'Solidaritätszuschlag WEG-Konto', 'nicht_umlagefaehig', '05*', false, false],
            ['051000', 'Anschaffungen', 'nicht_umlagefaehig', '05*', false, false],
            ['053000', 'Instandhaltungskosten', 'nicht_umlagefaehig', '05*', false, true],
        ];

        foreach ($konten as [$nummer, $bezeichnung, $kategorisierungsTyp, $umlageschluesselKey, $isActive, $taxDeductible]) {
            $konto = new Kostenkonto();
            $konto->setNummer($nummer);
            $konto->setBezeichnung($bezeichnung);
            $konto->setKategorisierungsTyp(KategorisierungsTyp::from($kategorisierungsTyp));
            $konto->setIsActive($isActive);
            $konto->setTaxDeductible($taxDeductible);

            // Set umlageschluessel reference
            $umlageschluessel = $this->getReference('umlageschluessel-' . $umlageschluesselKey, \App\Entity\Umlageschluessel::class);
            $konto->setUmlageschluessel($umlageschluessel);

            $manager->persist($konto);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UmlageschluesselFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['system-config', 'demo-data', 'opensource'];
    }
}
