<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:analyze-kostenkonto-filter',
    description: 'Analyze kostenkonto_filter usage in Zahlungskategorien',
)]
class AnalyzeKostenkontoFilterCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get all Zahlungskategorien with their field_config
        $connection = $this->entityManager->getConnection();
        $sql = '
            SELECT
                id,
                name,
                field_config,
                is_active
            FROM zahlungskategorie
            ORDER BY name
        ';

        $result = $connection->executeQuery($sql)->fetchAllAssociative();

        $withFilter = [];
        $withoutFilter = [];
        $filterStats = [];

        foreach ($result as $row) {
            $fieldConfig = json_decode($row['field_config'], true) ?? [];
            $kostenkontoFilter = $fieldConfig['kostenkonto_filter'] ?? [];

            if (!empty($kostenkontoFilter)) {
                $withFilter[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'is_active' => (bool) $row['is_active'],
                    'filter' => $kostenkontoFilter,
                ];

                // Count how many unique kostenkonto numbers are in filters
                foreach ($kostenkontoFilter as $kontoNummer) {
                    $filterStats[$kontoNummer] = ($filterStats[$kontoNummer] ?? 0) + 1;
                }
            } else {
                $withoutFilter[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'is_active' => (bool) $row['is_active'],
                ];
            }
        }

        // Show categories WITH filters
        if (\count($withFilter) > 0) {
            $io->section('✅ Zahlungskategorien MIT Kostenkonto-Filter (' . \count($withFilter) . ')');
            $io->table(
                ['ID', 'Name', 'Aktiv', 'Erlaubte Kostenkontos'],
                array_map(static fn ($row) => [
                    $row['id'],
                    $row['name'],
                    $row['is_active'] ? '✓' : '✗',
                    implode(', ', $row['filter']),
                ], $withFilter)
            );
        } else {
            $io->warning('⚠️  Keine Zahlungskategorie hat einen kostenkonto_filter definiert!');
        }

        // Show categories WITHOUT filters
        if (\count($withoutFilter) > 0) {
            $io->section('Zahlungskategorien OHNE Filter (' . \count($withoutFilter) . ') - "Alle Kostenkontos erlaubt"');
            $io->table(
                ['ID', 'Name', 'Aktiv'],
                array_map(static fn ($row) => [
                    $row['id'],
                    $row['name'],
                    $row['is_active'] ? '✓' : '✗',
                ], $withoutFilter)
            );
        }

        // Show filter usage statistics
        if (\count($filterStats) > 0) {
            $io->section('Kostenkonto-Nummern in Filtern verwendet');
            arsort($filterStats);
            $io->table(
                ['Kostenkonto-Nummer', 'Verwendet in X Kategorien'],
                array_map(static fn ($nummer, $count) => [$nummer, $count], array_keys($filterStats), $filterStats)
            );
        }

        // Summary and recommendation
        $io->section('Zusammenfassung & Empfehlung');
        $totalCategories = \count($result);
        $withFilterCount = \count($withFilter);
        $withoutFilterCount = \count($withoutFilter);
        $percentageWithFilter = $totalCategories > 0 ? round(($withFilterCount / $totalCategories) * 100, 1) : 0;

        $io->text([
            \sprintf('Total Zahlungskategorien: %d', $totalCategories),
            \sprintf('Mit Filter: %d (%s%%)', $withFilterCount, $percentageWithFilter),
            \sprintf('Ohne Filter (alle erlaubt): %d (%s%%)', $withoutFilterCount, 100 - $percentageWithFilter),
        ]);

        $io->newLine();

        if (0 === $withFilterCount) {
            $io->error([
                '❌ EMPFEHLUNG: Kostenkonto-Filter-Feature NICHT GENUTZT',
                '',
                'Alle Zahlungskategorien erlauben alle Kostenkontos.',
                'Das Filter-Feature im Frontend (zahlung_form_controller.js) wird nicht genutzt.',
                '',
                'Optionen:',
                '1. Feature aktivieren: Laden Sie die System-Config Fixtures neu:',
                '   docker compose exec web php bin/console doctrine:fixtures:load --group=system-config',
                '',
                '2. Feature entfernen: Vereinfachen Sie den Code (nicht empfohlen, da Feature sinnvoll ist)',
                '',
                'Das Feature ist SINNVOLL für:',
                '- Hausgeld-Zahlungen → nur Wohngeld-Konto (099900)',
                '- Umbuchungen → nur Geldverkehr-Konto (049000)',
                '- Verhindert falsche Kontobuchungen',
            ]);
        } elseif ($percentageWithFilter < 20) {
            $io->warning([
                '⚠️  EMPFEHLUNG: Feature wird kaum genutzt',
                '',
                \sprintf('Nur %d von %d Kategorien (%s%%) haben Filter.', $withFilterCount, $totalCategories, $percentageWithFilter),
                'Das Feature existiert, wird aber fast nicht verwendet.',
                '',
                'Prüfen Sie, ob weitere Kategorien Filter benötigen, z.B.:',
                '- Hausgeld-Zahlung → nur Wohngeld',
                '- Nachzahlung → nur Wohngeld',
                '- Umbuchung → nur Geldverkehr',
            ]);
        } else {
            $io->success([
                '✅ Feature wird aktiv genutzt',
                '',
                \sprintf('%d von %d Kategorien (%s%%) haben Filter definiert.', $withFilterCount, $totalCategories, $percentageWithFilter),
                'Das Filter-Feature macht Sinn und sollte beibehalten werden.',
                'Es verhindert falsche Kontobuchungen durch Einschränkung der Auswahl.',
            ]);
        }

        return Command::SUCCESS;
    }
}
