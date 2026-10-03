<?php

namespace App\Command;

use App\Repository\KostenkontoRepository;
use App\Repository\ZahlungskategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:remove-inactive-kostenkonto-from-filters',
    description: 'Remove inactive Kostenkontos from kostenkonto_filter in Zahlungskategorien',
)]
class RemoveInactiveKostenkontoFromFiltersCommand extends Command
{
    public function __construct(
        private readonly ZahlungskategorieRepository $zahlungskategorieRepository,
        private readonly KostenkontoRepository $kostenkontoRepository,
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

        $io->title('Entferne inaktive Kostenkontos aus Filtern');

        // Get all inactive Kostenkontos
        $inactiveKostenkontos = $this->kostenkontoRepository->findBy(['isActive' => false]);
        $inactiveNummern = array_map(static fn ($k) => $k->getNummer(), $inactiveKostenkontos);

        $io->section(\sprintf('Gefundene inaktive Kostenkontos: %d', \count($inactiveNummern)));
        if (\count($inactiveNummern) > 0) {
            $io->listing(array_map(
                static fn ($k) => \sprintf('%s - %s', $k->getNummer(), $k->getBezeichnung()),
                $inactiveKostenkontos
            ));
        }

        // Check all Zahlungskategorien
        $allKategorien = $this->zahlungskategorieRepository->findAll();
        $cleaned = 0;
        $changes = [];

        foreach ($allKategorien as $kategorie) {
            $fieldConfig = $kategorie->getFieldConfig() ?? [];
            $kostenkontoFilter = $fieldConfig['kostenkonto_filter'] ?? [];

            if (empty($kostenkontoFilter)) {
                continue; // Skip if no filter defined
            }

            // Find inactive Kostenkontos in filter
            $inactiveInFilter = array_intersect($kostenkontoFilter, $inactiveNummern);

            if (!empty($inactiveInFilter)) {
                // Remove inactive Kostenkontos
                $newFilter = array_values(array_diff($kostenkontoFilter, $inactiveNummern));

                $changes[] = [
                    'name' => $kategorie->getName(),
                    'removed' => implode(', ', $inactiveInFilter),
                    'old_count' => \count($kostenkontoFilter),
                    'new_count' => \count($newFilter),
                ];

                if (!$isDryRun) {
                    $fieldConfig['kostenkonto_filter'] = $newFilter;
                    $kategorie->setFieldConfig($fieldConfig);
                    ++$cleaned;
                }
            }
        }

        // Show changes
        if (\count($changes) > 0) {
            $io->section('Änderungen');
            $io->table(
                ['Kategorie', 'Entfernte Kostenkontos', 'Vorher', 'Nachher'],
                array_map(static fn ($c) => [$c['name'], $c['removed'], $c['old_count'], $c['new_count']], $changes)
            );

            if ($isDryRun) {
                $io->warning('DRY RUN: Keine Änderungen wurden gespeichert. Führen Sie ohne --dry-run aus.');
            } else {
                $this->entityManager->flush();
                $io->success(\sprintf('✅ %d Zahlungskategorie(n) bereinigt.', $cleaned));
            }
        } else {
            $io->success('✅ Keine inaktiven Kostenkontos in Filtern gefunden. Alles sauber!');
        }

        // Show warning if any filter becomes empty
        $emptyFilters = array_filter($changes, static fn ($c) => 0 === $c['new_count']);
        if (\count($emptyFilters) > 0) {
            $io->warning([
                'Achtung: Folgende Kategorien haben nach der Bereinigung LEERE Filter:',
                '',
                ...array_map(static fn ($c) => '  • ' . $c['name'], $emptyFilters),
                '',
                'Das bedeutet: "Alle aktiven Kostenkontos erlaubt"',
            ]);
        }

        return Command::SUCCESS;
    }
}
