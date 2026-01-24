<?php

namespace App\Command;

use App\Repository\KostenkontoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:deactivate-unused-kostenkonto',
    description: 'Deactivate Kostenkontos that are not assigned to any Zahlung',
)]
class DeactivateUnusedKostenkontoCommand extends Command
{
    public function __construct(
        private readonly KostenkontoRepository $kostenkontoRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show which Kostenkontos would be deactivated without actually deactivating them')
            ->addOption('reactivate-used', null, InputOption::VALUE_NONE, 'Reactivate Kostenkontos that have Zahlungen assigned');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');
        $reactivateUsed = $input->getOption('reactivate-used');

        // Get all Kostenkontos with their Zahlung count
        $connection = $this->entityManager->getConnection();
        $sql = '
            SELECT
                k.id,
                k.nummer,
                k.bezeichnung,
                k.is_active,
                COUNT(z.id) as zahlungen_count
            FROM kostenkonto k
            LEFT JOIN zahlung z ON k.id = z.kostenkonto_id
            GROUP BY k.id
            ORDER BY zahlungen_count DESC, k.nummer
        ';

        $result = $connection->executeQuery($sql)->fetchAllAssociative();

        $toDeactivate = [];
        $toReactivate = [];

        foreach ($result as $row) {
            $zahlungenCount = (int) $row['zahlungen_count'];
            $isActive = (bool) $row['is_active'];

            // Find Kostenkontos to deactivate (no Zahlungen and currently active)
            if (0 === $zahlungenCount && $isActive) {
                $toDeactivate[] = $row;
            }

            // Find Kostenkontos to reactivate (has Zahlungen but currently inactive)
            if ($reactivateUsed && $zahlungenCount > 0 && !$isActive) {
                $toReactivate[] = $row;
            }
        }

        // Display Kostenkontos to deactivate
        if (\count($toDeactivate) > 0) {
            $io->section('Kostenkontos to DEACTIVATE (no Zahlungen assigned)');
            $io->table(
                ['ID', 'Nummer', 'Bezeichnung', 'Currently Active', 'Zahlungen Count'],
                array_map(fn ($row) => [
                    $row['id'],
                    $row['nummer'],
                    $row['bezeichnung'],
                    $row['is_active'] ? 'Yes' : 'No',
                    $row['zahlungen_count'],
                ], $toDeactivate)
            );
        } else {
            $io->success('No Kostenkontos to deactivate. All inactive Kostenkontos either have Zahlungen or are already inactive.');
        }

        // Display Kostenkontos to reactivate
        if ($reactivateUsed && \count($toReactivate) > 0) {
            $io->section('Kostenkontos to REACTIVATE (have Zahlungen assigned)');
            $io->table(
                ['ID', 'Nummer', 'Bezeichnung', 'Currently Active', 'Zahlungen Count'],
                array_map(fn ($row) => [
                    $row['id'],
                    $row['nummer'],
                    $row['bezeichnung'],
                    $row['is_active'] ? 'Yes' : 'No',
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
        if (\count($toDeactivate) > 0) {
            $deactivatedCount = 0;
            foreach ($toDeactivate as $row) {
                $kostenkonto = $this->kostenkontoRepository->find($row['id']);
                if ($kostenkonto) {
                    $kostenkonto->setIsActive(false);
                    ++$deactivatedCount;
                }
            }

            $this->entityManager->flush();
            $io->success(\sprintf('Successfully deactivated %d Kostenkonto(s).', $deactivatedCount));
        }

        // Apply reactivations
        if ($reactivateUsed && \count($toReactivate) > 0) {
            $reactivatedCount = 0;
            foreach ($toReactivate as $row) {
                $kostenkonto = $this->kostenkontoRepository->find($row['id']);
                if ($kostenkonto) {
                    $kostenkonto->setIsActive(true);
                    ++$reactivatedCount;
                }
            }

            $this->entityManager->flush();
            $io->success(\sprintf('Successfully reactivated %d Kostenkonto(s).', $reactivatedCount));
        }

        // Show summary statistics
        $io->section('Summary');
        $activeCount = \count(array_filter($result, fn ($row) => (bool) $row['is_active']));
        $inactiveCount = \count($result) - $activeCount;
        $usedCount = \count(array_filter($result, fn ($row) => (int) $row['zahlungen_count'] > 0));
        $unusedCount = \count($result) - $usedCount;

        $io->text([
            \sprintf('Total Kostenkontos: %d', \count($result)),
            \sprintf('Active: %d | Inactive: %d', $activeCount, $inactiveCount),
            \sprintf('Used (has Zahlungen): %d | Unused (no Zahlungen): %d', $usedCount, $unusedCount),
        ]);

        return Command::SUCCESS;
    }
}
