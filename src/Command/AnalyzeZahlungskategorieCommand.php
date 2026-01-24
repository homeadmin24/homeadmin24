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
    name: 'app:analyze-zahlungskategorie',
    description: 'Analyze and optionally deactivate unused Zahlungskategorien',
)]
class AnalyzeZahlungskategorieCommand extends Command
{
    public function __construct(
        private readonly ZahlungskategorieRepository $zahlungskategorieRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show which Zahlungskategorien would be deactivated without actually deactivating them')
            ->addOption('deactivate-unused', null, InputOption::VALUE_NONE, 'Deactivate Zahlungskategorien that are not assigned to any Zahlung')
            ->addOption('reactivate-used', null, InputOption::VALUE_NONE, 'Reactivate Zahlungskategorien that have Zahlungen assigned');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');
        $deactivateUnused = $input->getOption('deactivate-unused');
        $reactivateUsed = $input->getOption('reactivate-used');

        // Get all Zahlungskategorien with their Zahlung count
        $connection = $this->entityManager->getConnection();
        $sql = '
            SELECT
                zk.id,
                zk.name,
                zk.beschreibung,
                zk.ist_positiver_betrag,
                zk.is_active,
                zk.sort_order,
                COUNT(z.id) as zahlungen_count
            FROM zahlungskategorie zk
            LEFT JOIN zahlung z ON zk.id = z.hauptkategorie_id
            GROUP BY zk.id
            ORDER BY zahlungen_count DESC, zk.sort_order, zk.name
        ';

        $result = $connection->executeQuery($sql)->fetchAllAssociative();

        // Show all Zahlungskategorien with usage statistics
        $io->section('All Zahlungskategorien with Usage Statistics');
        $tableData = array_map(fn ($row) => [
            $row['id'],
            $row['name'],
            $row['ist_positiver_betrag'] ? 'Einnahme' : 'Ausgabe',
            $row['is_active'] ? '✓' : '✗',
            $row['zahlungen_count'],
            mb_strlen($row['beschreibung'] ?? '') > 50 ? mb_substr($row['beschreibung'], 0, 47) . '...' : ($row['beschreibung'] ?? ''),
        ], $result);

        $io->table(
            ['ID', 'Name', 'Typ', 'Aktiv', 'Zahlungen', 'Beschreibung'],
            $tableData
        );

        $toDeactivate = [];
        $toReactivate = [];

        foreach ($result as $row) {
            $zahlungenCount = (int) $row['zahlungen_count'];
            $isActive = (bool) $row['is_active'];

            // Find Zahlungskategorien to deactivate (no Zahlungen and currently active)
            if (0 === $zahlungenCount && $isActive) {
                $toDeactivate[] = $row;
            }

            // Find Zahlungskategorien to reactivate (has Zahlungen but currently inactive)
            if ($reactivateUsed && $zahlungenCount > 0 && !$isActive) {
                $toReactivate[] = $row;
            }
        }

        // Show unused categories
        if (\count($toDeactivate) > 0) {
            $io->section('⚠️  UNUSED Zahlungskategorien (no Zahlungen assigned)');
            $io->table(
                ['ID', 'Name', 'Typ', 'Beschreibung'],
                array_map(fn ($row) => [
                    $row['id'],
                    $row['name'],
                    $row['ist_positiver_betrag'] ? 'Einnahme' : 'Ausgabe',
                    mb_strlen($row['beschreibung'] ?? '') > 60 ? mb_substr($row['beschreibung'], 0, 57) . '...' : ($row['beschreibung'] ?? ''),
                ], $toDeactivate)
            );

            if (!$deactivateUnused) {
                $io->note('To deactivate these, run with --deactivate-unused');
            }
        } else {
            $io->success('✓ No unused Zahlungskategorien found. All categories are either in use or already inactive.');
        }

        // Show inactive but used categories
        if ($reactivateUsed && \count($toReactivate) > 0) {
            $io->section('Inactive Zahlungskategorien with Zahlungen (should be reactivated)');
            $io->table(
                ['ID', 'Name', 'Typ', 'Zahlungen Count'],
                array_map(fn ($row) => [
                    $row['id'],
                    $row['name'],
                    $row['ist_positiver_betrag'] ? 'Einnahme' : 'Ausgabe',
                    $row['zahlungen_count'],
                ], $toReactivate)
            );
        }

        // Dry run - don't actually make changes
        if ($isDryRun) {
            $io->note('DRY RUN: No changes were made. Run without --dry-run to apply changes.');

            return Command::SUCCESS;
        }

        // Apply deactivations
        if ($deactivateUnused && \count($toDeactivate) > 0) {
            $deactivatedCount = 0;
            foreach ($toDeactivate as $row) {
                $kategorie = $this->zahlungskategorieRepository->find($row['id']);
                if ($kategorie) {
                    $kategorie->setIsActive(false);
                    ++$deactivatedCount;
                }
            }

            $this->entityManager->flush();
            $io->success(\sprintf('Successfully deactivated %d Zahlungskategorie(n).', $deactivatedCount));
        }

        // Apply reactivations
        if ($reactivateUsed && \count($toReactivate) > 0) {
            $reactivatedCount = 0;
            foreach ($toReactivate as $row) {
                $kategorie = $this->zahlungskategorieRepository->find($row['id']);
                if ($kategorie) {
                    $kategorie->setIsActive(true);
                    ++$reactivatedCount;
                }
            }

            $this->entityManager->flush();
            $io->success(\sprintf('Successfully reactivated %d Zahlungskategorie(n).', $reactivatedCount));
        }

        // Show summary statistics
        $io->section('Summary');
        $activeCount = \count(array_filter($result, fn ($row) => (bool) $row['is_active']));
        $inactiveCount = \count($result) - $activeCount;
        $usedCount = \count(array_filter($result, fn ($row) => (int) $row['zahlungen_count'] > 0));
        $unusedCount = \count($result) - $usedCount;
        $einnahmenCount = \count(array_filter($result, fn ($row) => (bool) $row['ist_positiver_betrag']));
        $ausgabenCount = \count($result) - $einnahmenCount;

        $io->text([
            \sprintf('Total Zahlungskategorien: %d', \count($result)),
            \sprintf('  Active: %d | Inactive: %d', $activeCount, $inactiveCount),
            \sprintf('  Used (has Zahlungen): %d | Unused (no Zahlungen): %d', $usedCount, $unusedCount),
            \sprintf('  Einnahmen: %d | Ausgaben: %d', $einnahmenCount, $ausgabenCount),
        ]);

        return Command::SUCCESS;
    }
}
