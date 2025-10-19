<?php

namespace App\Command;

use App\Service\MonthlyBalanceImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-monthly-balance',
    description: 'Import monthly balance data from saldo report file',
)]
class ImportMonthlyBalanceCommand extends Command
{
    public function __construct(
        private MonthlyBalanceImportService $importService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to monthly saldo report file', 'data/zahlungen/monthly_saldo_report.txt')
            ->addOption('weg-id', 'w', InputOption::VALUE_REQUIRED, 'WEG ID', 1)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $filePath = $input->getOption('file');
        $wegId = (int) $input->getOption('weg-id');

        if (!file_exists($filePath)) {
            $io->error("File not found: $filePath");

            return Command::FAILURE;
        }

        $io->info("Importing monthly balance data from: $filePath");
        $io->info("WEG ID: $wegId");

        try {
            $this->importService->importFromSaldoReport($filePath, $wegId);
            $io->success('Monthly balance data imported successfully!');
        } catch (\Exception $e) {
            $io->error('Import failed: ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
