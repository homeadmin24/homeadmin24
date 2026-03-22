<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Weg;
use App\Service\Hga\HgaServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:calc-vorauszahlung',
    description: 'Calculate new Vorauszahlung based on actual HGA costs',
)]
class CalcVorauszahlungCommand extends Command
{
    public function __construct(
        private HgaServiceInterface $hgaService,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('weg', InputArgument::REQUIRED, 'WEG ID')
            ->addArgument('year', InputArgument::REQUIRED, 'Year to base calculation on');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $wegId = (int) $input->getArgument('weg');
        $year = (int) $input->getArgument('year');

        $weg = $this->em->getRepository(Weg::class)->find($wegId);
        if (!$weg) {
            $io->error("WEG {$wegId} not found");

            return Command::FAILURE;
        }

        $io->title(\sprintf('Vorauszahlung-Berechnung basierend auf HGA %d - %s', $year, $weg->getBezeichnung()));

        $rows = [];
        $totalWeg = 0.0;
        $totalUnit = 0.0;

        foreach ($weg->getEinheiten() as $unit) {
            $data = $this->hgaService->generateReportData($unit, $year);
            $totals = $data['calculated_totals'];
            $unitKosten = $totals['unit']['gesamtkosten'];
            $wegKosten = $totals['weg']['gesamtkosten'];
            $saldo = $totals['balance']['saldo'];

            $totalUnit += $unitKosten;
            $totalWeg = $wegKosten; // same for all units

            $rows[] = [
                $unit->getNummer(),
                $unit->getMiteigentuemer(),
                $unit->getMiteigentumsanteile(),
                number_format($unitKosten, 2, ',', '.') . ' €',
                number_format($unitKosten / 12, 2, ',', '.') . ' €',
                number_format($unitKosten * 1.05 / 12, 2, ',', '.') . ' €',
                number_format($unitKosten * 1.10 / 12, 2, ',', '.') . ' €',
                number_format($saldo, 2, ',', '.') . ' €',
            ];
        }

        $io->table(
            ['Einheit', 'Eigentümer', 'MEA', 'Ist-Kosten', '/Monat', '+5%/Mo', '+10%/Mo', 'Saldo'],
            $rows
        );

        $io->section('Zusammenfassung');
        $io->listing([
            \sprintf('WEG Gesamtkosten %d: %s €', $year, number_format($totalWeg, 2, ',', '.')),
            \sprintf('Summe Einheiten-Anteile: %s €', number_format($totalUnit, 2, ',', '.')),
            \sprintf('Monatlich gesamt: %s €', number_format($totalUnit / 12, 2, ',', '.')),
            \sprintf('Monatlich +5%%: %s €', number_format($totalUnit * 1.05 / 12, 2, ',', '.')),
            \sprintf('Monatlich +10%%: %s €', number_format($totalUnit * 1.10 / 12, 2, ',', '.')),
        ]);

        return Command::SUCCESS;
    }
}
