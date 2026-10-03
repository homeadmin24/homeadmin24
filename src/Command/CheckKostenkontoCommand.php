<?php

namespace App\Command;

use App\Repository\KostenkontoRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-kostenkonto',
    description: 'Check if specific Kostenkontos exist',
)]
class CheckKostenkontoCommand extends Command
{
    public function __construct(
        private readonly KostenkontoRepository $kostenkontoRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $searchNumbers = ['049000', '099900'];

        $io->title('Prüfe Kostenkontos');

        foreach ($searchNumbers as $nummer) {
            $konto = $this->kostenkontoRepository->findOneBy(['nummer' => $nummer]);

            if ($konto) {
                $io->success(\sprintf(
                    'Gefunden: %s - %s (ID: %d, Aktiv: %s)',
                    $konto->getNummer(),
                    $konto->getBezeichnung(),
                    $konto->getId(),
                    $konto->isActive() ? 'Ja' : 'Nein'
                ));
            } else {
                $io->error(\sprintf('NICHT GEFUNDEN: Kostenkonto mit Nummer %s', $nummer));
            }
        }

        $io->section('Alle Kostenkontos (aktiv und inaktiv)');
        $allKostenkontos = $this->kostenkontoRepository->findBy([], ['nummer' => 'ASC']);

        $tableData = [];
        foreach ($allKostenkontos as $konto) {
            $tableData[] = [
                $konto->getId(),
                $konto->getNummer(),
                $konto->getBezeichnung(),
                $konto->isActive() ? '✓' : '✗',
            ];
        }

        $io->table(['ID', 'Nummer', 'Bezeichnung', 'Aktiv'], $tableData);

        return Command::SUCCESS;
    }
}
