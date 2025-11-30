<?php

declare(strict_types=1);

namespace App\DataFixtures\SystemConfig;

use App\Entity\SystemConfig;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class SystemConfigFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['system-config', 'demo-data', 'opensource'];
    }

    public function load(ObjectManager $manager): void
    {
        // Tax-deductible accounts configuration
        $this->createConfig($manager,
            'hga.tax_deductible_accounts',
            [
                '040100', // Hausmeisterkosten
                '040101', // Hausmeister-Sonderleistungen
                '041100', // Schornsteinfeger
                '042111', // Wasseranalytik
                '045100', // Laufende Instandhaltung
                '047000', // Hebeanlage
            ],
            'hausgeldabrechnung',
            'List of Kostenkonto numbers eligible for §35a EStG tax deduction'
        );

        // HGA section headers
        $this->createConfig($manager,
            'hga.section_headers',
            [
                'main_title' => 'HAUSGELDABRECHNUNG %s - EINZELABRECHNUNG',
                'owner_info' => 'EIGENTÜMER INFORMATION:',
                'summary' => 'ABRECHNUNGSÜBERSICHT:',
                'calculation' => 'BERECHNUNG DES ANTEILS:',
                'umlageschluessel' => 'UMLAGESCHLÜSSEL:',
                'umlagefaehig' => '1. UMLAGEFÄHIGE KOSTEN (Mieter):',
                'nicht_umlagefaehig' => '2. NICHT UMLAGEFÄHIGE KOSTEN (Mieter):',
                'ruecklagen' => '3. RÜCKLAGENZUFÜHRUNG:',
                'tax_deductible' => 'STEUERBEGÜNSTIGTE LEISTUNGEN nach §35a EStG:',
                'payment_overview' => 'EINZELABRECHNUNG DER ZAHLUNGEN %s:',
                'balance_development' => 'KONTOSTANDSENTWICKLUNG %s:',
                'wirtschaftsplan' => 'VERMÖGENSÜBERSICHT UND WIRTSCHAFTSPLAN %s',
                'end' => 'ENDE DER HAUSGELDABRECHNUNG',
            ],
            'hausgeldabrechnung',
            'Standard section headers for HGA reports'
        );

        // HGA standard texts
        $this->createConfig($manager,
            'hga.standard_texts',
            [
                'tax_deductible_info' => '✅ Ihr steuerlich absetzbarer Betrag (100%% der Arbeits-/Fahrtkosten inkl. MwSt.): %s EUR',
                'tax_notice' => [
                    'HINWEIS: Diese Beträge können Sie in Ihrer Steuererklärung als haushaltsnahe',
                    'Dienstleistungen geltend machen (20% davon, max. 1.200 EUR Steuerermäßigung pro Jahr).',
                    '',
                    'Bitte reichen Sie die detaillierte Abrechnung zusammen mit den Handwerkerrechnungen',
                    'beim Finanzamt ein.',
                ],
                'balance_notice' => 'Hinweis: Der aktuelle Kontostand deckt den Mehrbedarf vollständig, daher keine Erhöhung der Hausgeld-Vorschüsse.',
            ],
            'hausgeldabrechnung',
            'Standard text templates for HGA reports'
        );

        // Account category mappings
        $this->createConfig($manager,
            'hga.account_categories',
            [
                'heizung_wasser' => [
                    'name' => 'HEIZUNG/WASSER/ABRECHNUNG',
                    'accounts' => ['041800'],
                ],
                'versicherung' => [
                    'name' => 'Versicherungen',
                    'accounts' => ['046000', '046200'],
                ],
                'verwaltung' => [
                    'name' => 'Verwaltung',
                    'accounts' => ['050000', '052000'],
                ],
                'instandhaltung' => [
                    'name' => 'Instandhaltung/Reparaturen',
                    'accounts' => ['045100', '044000'],
                ],
                'sonstiges' => [
                    'name' => 'Sonstige',
                    'accounts' => [],
                ],
            ],
            'hausgeldabrechnung',
            'Kostenkonto category mappings for report grouping'
        );

        $manager->flush();
    }

    private function createConfig(
        ObjectManager $manager,
        string $key,
        mixed $value,
        string $category,
        string $description,
    ): void {
        $config = new SystemConfig();
        $config->setConfigKey($key)
            ->setConfigValue(\is_array($value) ? $value : [$value])
            ->setCategory($category)
            ->setDescription($description)
            ->setIsActive(true);

        $manager->persist($config);
    }
}
