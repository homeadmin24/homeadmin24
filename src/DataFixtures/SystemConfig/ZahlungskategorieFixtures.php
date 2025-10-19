<?php

namespace App\DataFixtures\SystemConfig;

use App\Entity\Zahlungskategorie;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ZahlungskategorieFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $kategorien = [
            [
                'name' => 'Rechnung von Dienstleister',
                'beschreibung' => 'Rechnungen von externen Dienstleistern mit Kostenkontozuordnung',
                'ist_positiver_betrag' => false,
                'field_config' => [
                    'show' => ['kostenkonto', 'dienstleister', 'rechnung', 'mehrwertsteuer'],
                    'required' => ['kostenkonto', 'dienstleister'],
                    'kostenkonto_filter' => [63, 64, 70, 72, 97, 82, 100, 85, 87, 89, 123, 94, 95, 96, 102, 109],
                ],
                'validation_rules' => [
                    'betrag' => ['max' => 0],
                    'kostenkonto' => ['not_equals' => '099900'],
                ],
                'help_text' => 'Für alle Rechnungen von externen Dienstleistern mit Kostenkontozuordnung',
                'sort_order' => 1,
                'is_active' => true,
                'allows_zero_amount' => false,
            ],
            [
                'name' => 'Direktbuchung Kostenkonto',
                'beschreibung' => 'Direkte Buchungen auf Kostenkonten ohne Dienstleister',
                'ist_positiver_betrag' => false,
                'field_config' => [
                    'show' => ['kostenkonto'],
                    'required' => ['kostenkonto'],
                    'kostenkonto_filter' => [63, 64, 70, 72, 97, 82, 100, 85, 87, 89, 123, 94, 95, 96, 102, 109],
                ],
                'validation_rules' => [
                    'betrag' => ['max' => 0],
                    'kostenkonto' => ['not_equals' => '099900'],
                ],
                'help_text' => 'Für direkte Kostenbuchungen ohne Dienstleisterbezug',
                'sort_order' => 2,
                'is_active' => true,
                'allows_zero_amount' => false,
            ],
            [
                'name' => 'Auslagenerstattung Eigentümer',
                'beschreibung' => 'Erstattung von Auslagen an Eigentümer',
                'ist_positiver_betrag' => false,
                'field_config' => [
                    'show' => ['kostenkonto', 'eigentuemer'],
                    'required' => ['kostenkonto', 'eigentuemer'],
                    'kostenkonto_filter' => [63, 64, 70, 72, 97, 82, 100, 85, 87, 89, 123, 94, 95, 96, 102, 109],
                ],
                'validation_rules' => [
                    'betrag' => ['max' => 0],
                    'kostenkonto' => ['not_equals' => '099900'],
                ],
                'help_text' => 'Für Erstattungen von Auslagen an Eigentümer',
                'sort_order' => 3,
                'is_active' => true,
                'allows_zero_amount' => false,
            ],
            [
                'name' => 'Rückzahlung an Eigentümer',
                'beschreibung' => 'Rückzahlungen von Guthaben an Eigentümer',
                'ist_positiver_betrag' => false,
                'field_config' => [
                    'show' => ['eigentuemer'],
                    'auto_set' => ['kostenkonto' => '099900'],
                    'required' => ['eigentuemer'],
                    'kostenkonto_filter' => [124],
                ],
                'validation_rules' => [
                    'betrag' => ['max' => 0],
                ],
                'help_text' => 'Für Rückzahlungen von Guthaben an Eigentümer',
                'sort_order' => 4,
                'is_active' => true,
                'allows_zero_amount' => false,
            ],
            [
                'name' => 'Bankgebühren',
                'beschreibung' => 'Bankgebühren und Kontoführungskosten',
                'ist_positiver_betrag' => false,
                'field_config' => [
                    'show' => ['kostenkonto'],
                    'auto_set' => ['kostenkonto' => '049000'],
                    'required' => [],
                    'kostenkonto_filter' => [106],
                ],
                'validation_rules' => [
                    'betrag' => ['max' => 0],
                ],
                'help_text' => 'Für Bankgebühren und Kontoführungskosten',
                'sort_order' => 5,
                'is_active' => true,
                'allows_zero_amount' => false,
            ],
            [
                'name' => 'Hausgeld-Zahlung',
                'beschreibung' => 'Reguläre monatliche Zahlungen der Eigentümer',
                'ist_positiver_betrag' => true,
                'field_config' => [
                    'show' => ['eigentuemer'],
                    'auto_set' => ['kostenkonto' => '124'],
                    'required' => ['eigentuemer'],
                    'kostenkonto_filter' => [124],
                ],
                'validation_rules' => [
                    'betrag' => ['min' => 0.01],
                ],
                'help_text' => 'Reguläre monatliche Zahlungen der Eigentümer',
                'sort_order' => 6,
                'is_active' => true,
                'allows_zero_amount' => false,
            ],
            [
                'name' => 'Sonderumlage',
                'beschreibung' => 'Sonderumlagen für außergewöhnliche Reparaturen und Investitionen',
                'ist_positiver_betrag' => true,
                'field_config' => [
                    'show' => ['eigentuemer'],
                    'auto_set' => ['kostenkonto' => '099900'],
                    'required' => ['eigentuemer'],
                    'kostenkonto_filter' => [124],
                ],
                'validation_rules' => [
                    'betrag' => ['min' => 0.01],
                ],
                'help_text' => 'Sonderumlagen für außergewöhnliche Reparaturen und Investitionen',
                'sort_order' => 7,
                'is_active' => true,
                'allows_zero_amount' => false,
            ],
            [
                'name' => 'Gutschrift Dienstleister',
                'beschreibung' => 'Rückerstattungen und Gutschriften von Dienstleistern',
                'ist_positiver_betrag' => true,
                'field_config' => [
                    'show' => ['kostenkonto', 'dienstleister'],
                    'required' => ['kostenkonto', 'dienstleister'],
                    'kostenkonto_filter' => [63, 64, 70, 72, 97, 82, 100, 85, 87, 89, 123, 94, 95, 96, 102, 109],
                ],
                'validation_rules' => [
                    'betrag' => ['min' => 0.01],
                    'kostenkonto' => ['not_equals' => '099900'],
                ],
                'help_text' => 'Rückerstattungen und Gutschriften von Dienstleistern',
                'sort_order' => 8,
                'is_active' => true,
                'allows_zero_amount' => false,
            ],
            [
                'name' => 'Zinserträge',
                'beschreibung' => 'Zinserträge aus Bankguthaben',
                'ist_positiver_betrag' => true,
                'field_config' => [
                    'show' => ['kostenkonto'],
                    'auto_set' => ['kostenkonto' => '049000'],
                    'required' => [],
                    'kostenkonto_filter' => [106],
                ],
                'validation_rules' => [
                    'betrag' => ['min' => 0.01],
                ],
                'help_text' => 'Zinserträge aus Bankguthaben',
                'sort_order' => 9,
                'is_active' => true,
                'allows_zero_amount' => false,
            ],
            [
                'name' => 'Sonstige Einnahme',
                'beschreibung' => 'Sonstige Einnahmen und Erträge',
                'ist_positiver_betrag' => true,
                'field_config' => [
                    'show' => ['kostenkonto'],
                    'required' => ['kostenkonto'],
                ],
                'validation_rules' => [
                    'betrag' => ['min' => 0.01],
                    'kostenkonto' => ['not_equals' => '099900'],
                ],
                'help_text' => 'Sonstige Einnahmen und Erträge',
                'sort_order' => 10,
                'is_active' => true,
                'allows_zero_amount' => false,
            ],
            [
                'name' => 'Umbuchung',
                'beschreibung' => 'Umbuchungen zwischen Kostenkonten',
                'ist_positiver_betrag' => false,
                'field_config' => [
                    'show' => ['kostenkonto', 'kostenkontoTo'],
                    'required' => ['kostenkonto', 'kostenkontoTo'],
                ],
                'validation_rules' => [],
                'help_text' => 'Umbuchungen zwischen Kostenkonten',
                'sort_order' => 11,
                'is_active' => true,
                'allows_zero_amount' => true,
            ],
            [
                'name' => 'Korrektur',
                'beschreibung' => 'Korrekturbuchungen und Stornierungen',
                'ist_positiver_betrag' => false,
                'field_config' => [
                    'show' => ['kostenkonto', 'eigentuemer'],
                    'required' => [],
                ],
                'validation_rules' => [],
                'help_text' => 'Korrekturbuchungen und Stornierungen',
                'sort_order' => 12,
                'is_active' => true,
                'allows_zero_amount' => true,
            ],
        ];

        foreach ($kategorien as $data) {
            $kategorie = new Zahlungskategorie();
            $kategorie->setName($data['name']);
            $kategorie->setBeschreibung($data['beschreibung']);
            $kategorie->setIstPositiverBetrag($data['ist_positiver_betrag']);
            $kategorie->setFieldConfig($data['field_config']);
            $kategorie->setValidationRules($data['validation_rules']);
            $kategorie->setHelpText($data['help_text']);
            $kategorie->setSortOrder($data['sort_order']);
            $kategorie->setIsActive($data['is_active']);
            $kategorie->setAllowsZeroAmount($data['allows_zero_amount']);

            $manager->persist($kategorie);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['system-config', 'demo-data', 'opensource'];
    }
}
