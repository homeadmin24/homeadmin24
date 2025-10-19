<?php

namespace App\Command;

use App\Repository\DokumentRepository;
use App\Service\InvoiceProcessingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-parser',
    description: 'Test PDF parser with a specific document',
)]
class TestParserCommand extends Command
{
    public function __construct(
        private DokumentRepository $dokumentRepository,
        private InvoiceProcessingService $invoiceProcessor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('document-id', InputArgument::REQUIRED, 'The ID of the document to parse');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $documentId = (int) $input->getArgument('document-id');

        $dokument = $this->dokumentRepository->find($documentId);

        if (!$dokument) {
            $io->error("Document with ID $documentId not found");

            return Command::FAILURE;
        }

        $io->title("Testing Parser for Document ID: $documentId");
        $io->table(
            ['Field', 'Value'],
            [
                ['ID', $dokument->getId()],
                ['Filename', $dokument->getDateiname()],
                ['Category', $dokument->getKategorie()],
                ['File Path', $dokument->getDateipfad()],
                ['Dienstleister', $dokument->getDienstleister()?->getBezeichnung() ?? 'None'],
                ['Parser Enabled', $dokument->getDienstleister()?->isParserEnabled() ? 'Yes' : 'No'],
                ['Existing Rechnung', $dokument->getRechnung() ? 'Yes (ID: ' . $dokument->getRechnung()->getId() . ')' : 'No'],
            ]
        );

        if (!$dokument->getDienstleister()) {
            $io->error('Document has no Dienstleister assigned');

            return Command::FAILURE;
        }

        if (!$dokument->getDienstleister()->isParserEnabled()) {
            $io->error('Parser is not enabled for this Dienstleister');

            return Command::FAILURE;
        }

        // Check if file exists
        $filePath = $dokument->getAbsoluterPfad();
        if (!file_exists($filePath)) {
            $io->error("File not found: $filePath");

            return Command::FAILURE;
        }

        $io->info("File exists: $filePath");

        try {
            $io->section('Attempting to parse document...');

            $rechnung = $this->invoiceProcessor->processDocument($dokument);

            if ($rechnung) {
                $io->success('✅ Parsing successful!');
                $io->table(
                    ['Field', 'Value'],
                    [
                        ['Rechnung ID', $rechnung->getId()],
                        ['Invoice Number', $rechnung->getRechnungsnummer()],
                        ['Amount (incl. tax)', $rechnung->getBetragMitSteuern() . ' €'],
                        ['VAT Amount', $rechnung->getGesamtMwSt() . ' €'],
                        ['Labor Costs', $rechnung->getArbeitsFahrtkosten() . ' €'],
                        ['Service Date', $rechnung->getDatumLeistung()?->format('d.m.Y') ?? 'Not set'],
                        ['Information', $rechnung->getInformation()],
                    ]
                );
            } else {
                $io->warning('⚠️  Parser returned null - document not eligible for parsing');
            }
        } catch (\Exception $e) {
            $io->error('❌ Parsing failed: ' . $e->getMessage());
            $io->text('Stack trace:');
            $io->text($e->getTraceAsString());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
