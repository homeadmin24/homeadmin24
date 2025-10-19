<?php

namespace App\Command;

use App\Repository\DienstleisterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:configure-parser',
    description: 'Configure parser for Maaß Gebäudemanagement',
)]
class ConfigureParserCommand extends Command
{
    public function __construct(
        private DienstleisterRepository $dienstleisterRepository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Find Maaß Gebäudemanagement
        $maass = $this->dienstleisterRepository->find(4);

        if (!$maass) {
            $io->error('Maaß Gebäudemanagement not found (ID: 4)');

            return Command::FAILURE;
        }

        // Configure parser
        $config = [
            'parser_type' => 'regex',
            'field_mappings' => [
                'rechnungsnummer' => [
                    'pattern' => '/Rechnungsnr\.:\s*([A-Z0-9\-]+)/i',
                    'type' => 'regex',
                ],
                'betrag_mit_steuern' => [
                    'pattern' => '/Gesamtbetrag\s+([0-9.,]+)\s*EUR/i',
                    'type' => 'regex',
                    'transform' => 'german_decimal',
                ],
                'gesamt_mw_st' => [
                    'pattern' => '/MwSt\.\s*19\s*%\s+([0-9.,]+)\s*EUR/i',
                    'type' => 'regex',
                    'transform' => 'german_decimal',
                ],
                'datum_leistung' => [
                    'pattern' => '/Leistungszeitraum:\s*([0-9]{2}\.[0-9]{2}\.[0-9]{4})/',
                    'type' => 'regex',
                    'transform' => 'date',
                ],
            ],
        ];

        $maass->setParserConfig($config);
        $maass->setParserEnabled(true);
        $maass->setParserClass('App\Service\Parser\MaassParser');

        $this->entityManager->flush();

        $io->success('Parser configuration updated for Maaß Gebäudemanagement');
        $io->table(
            ['Field', 'Value'],
            [
                ['ID', $maass->getId()],
                ['Name', $maass->getBezeichnung()],
                ['Parser Enabled', $maass->isParserEnabled() ? 'Yes' : 'No'],
                ['Parser Class', $maass->getParserClass()],
                ['Config Fields', implode(', ', array_keys($config['field_mappings']))],
            ]
        );

        return Command::SUCCESS;
    }
}
