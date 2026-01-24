<?php

namespace App\Command;

use App\Repository\ZahlungskategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-kostenkonto-filter',
    description: 'Update kostenkonto_filter for Zahlungskategorien to prevent wrong account assignments',
)]
class UpdateKostenkontoFilterCommand extends Command
{
    public function __construct(
        private readonly ZahlungskategorieRepository $zahlungskategorieRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be changed without applying changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');

        $io->title('Update Kostenkonto-Filter für Zahlungskategorien');

        // Define the filters to apply
        $filterMappings = [
            'Hausgeld-Zahlung' => ['099900'], // Only Wohngeld account
            'Nachzahlung' => ['099900'], // Only Wohngeld account
            'Rückerstattung (Hausgeld)' => ['099900'], // Only Wohngeld account
            'Umbuchung' => ['049000'], // Only Nebenkosten Geldverkehr
            'Sonderumlage' => ['049000'], // Only Nebenkosten Geldverkehr
        ];

        $updated = 0;
        $notFound = [];
        $changes = [];

        foreach ($filterMappings as $kategorieName => $filter) {
            $kategorien = $this->zahlungskategorieRepository->findBy(['name' => $kategorieName]);

            if (empty($kategorien)) {
                $notFound[] = $kategorieName;
                continue;
            }

            foreach ($kategorien as $kategorie) {
                $fieldConfig = $kategorie->getFieldConfig() ?? [];
                $oldFilter = $fieldConfig['kostenkonto_filter'] ?? [];

                // Only update if filter is different
                if ($oldFilter !== $filter) {
                    $fieldConfig['kostenkonto_filter'] = $filter;

                    $changes[] = [
                        'name' => $kategorieName,
                        'old' => empty($oldFilter) ? 'Alle erlaubt' : implode(', ', $oldFilter),
                        'new' => implode(', ', $filter),
                    ];

                    if (!$isDryRun) {
                        $kategorie->setFieldConfig($fieldConfig);
                        ++$updated;
                    }
                }
            }
        }

        // Show changes
        if (\count($changes) > 0) {
            $io->section('Änderungen');
            $io->table(
                ['Kategorie', 'Alt', 'Neu'],
                $changes
            );

            if ($isDryRun) {
                $io->warning('DRY RUN: Keine Änderungen wurden gespeichert. Führen Sie ohne --dry-run aus, um zu speichern.');
            } else {
                $this->entityManager->flush();
                $io->success(\sprintf('✅ %d Zahlungskategorie(n) aktualisiert.', $updated));
            }
        } else {
            $io->success('✅ Alle Filter sind bereits korrekt gesetzt. Keine Änderungen nötig.');
        }

        // Show not found categories
        if (\count($notFound) > 0) {
            $io->warning('⚠️  Folgende Kategorien wurden nicht gefunden:');
            $io->listing($notFound);
            $io->note('Diese Kategorien existieren möglicherweise nicht in Ihrer Datenbank.');
        }

        // Show explanation
        $io->section('Warum diese Filter?');
        $io->text([
            '🎯 Zweck: Verhindert falsche Kontobuchungen',
            '',
            '• Hausgeld-Zahlungen → nur auf Wohngeld-Konto (099900)',
            '  → Verhindert versehentliche Buchung auf andere Konten',
            '',
            '• Umbuchungen → nur auf Geldverkehr-Konto (049000)',
            '  → Umbuchungen sind interne Transaktionen',
            '',
            '• Sonderumlagen → nur auf Geldverkehr-Konto (049000)',
            '  → Ähnlich wie normale Umlagen, separate Verwaltung',
        ]);

        // Show next steps
        $io->section('Nächste Schritte');
        $io->text([
            '1. Testen Sie die Filter im Frontend:',
            '   http://127.0.0.1:8000/zahlung/ → Neue Zahlung erstellen',
            '',
            '2. Wählen Sie "Hausgeld-Zahlung" als Kategorie',
            '   → Nur noch Wohngeld-Konto (099900) sollte auswählbar sein',
            '',
            '3. Prüfen Sie die Mapping-Tabelle:',
            '   http://127.0.0.1:8000/weg → Tab "Zahlungskategorien"',
        ]);

        return Command::SUCCESS;
    }
}
