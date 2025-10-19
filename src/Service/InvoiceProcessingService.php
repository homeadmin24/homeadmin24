<?php

namespace App\Service;

use App\Entity\Dokument;
use App\Entity\Rechnung;
use App\Service\Parser\ParserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class InvoiceProcessingService
{
    public function __construct(
        private ParserFactory $parserFactory,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Process a document and create Rechnung if applicable.
     *
     * @return Rechnung|null The created Rechnung or null if not applicable
     */
    public function processDocument(Dokument $dokument): ?Rechnung
    {
        // Check if document is eligible for parsing
        if ('rechnungen' !== $dokument->getKategorie()) {
            $this->logger->debug('Document is not in rechnungen category, skipping');

            return null;
        }

        $dienstleister = $dokument->getDienstleister();
        if (!$dienstleister) {
            $this->logger->debug('Document has no Dienstleister, skipping');

            return null;
        }

        if (!$dienstleister->isParserEnabled()) {
            $this->logger->debug('Parser is not enabled for Dienstleister: ' . $dienstleister->getBezeichnung());

            return null;
        }

        // Check if document already has a linked Rechnung
        if ($dokument->getRechnung()) {
            $this->logger->info('Document already has a linked Rechnung, skipping');

            return $dokument->getRechnung();
        }

        try {
            // Get appropriate parser
            $parser = $this->parserFactory->createParser($dienstleister);

            // Check if parser can handle this document
            if (!$parser->canParse($dokument)) {
                $this->logger->debug('Parser cannot handle this document');

                return null;
            }

            // Parse the document
            $this->logger->info('Parsing document: ' . $dokument->getDateiname());
            $rechnung = $parser->parse($dokument);

            // Link the Rechnung to the Dokument
            $dokument->setRechnung($rechnung);

            // Persist the Rechnung
            $this->entityManager->persist($rechnung);
            $this->entityManager->flush();

            $this->logger->info('Successfully created Rechnung: ' . $rechnung->getRechnungsnummer());

            return $rechnung;
        } catch (\Exception $e) {
            $this->logger->error('Failed to parse document: ' . $e->getMessage(), [
                'document_id' => $dokument->getId(),
                'filename' => $dokument->getDateiname(),
                'exception' => $e,
            ]);

            throw $e; // Re-throw for controller to handle
        }
    }

    /**
     * Process multiple documents.
     *
     * @param array<Dokument> $dokumente
     *
     * @return array<string, mixed> Results with document ID => Rechnung or error
     */
    public function processMultipleDocuments(array $dokumente): array
    {
        $results = [];

        foreach ($dokumente as $dokument) {
            try {
                $rechnung = $this->processDocument($dokument);
                $results[$dokument->getId()] = [
                    'success' => true,
                    'rechnung' => $rechnung,
                ];
            } catch (\Exception $e) {
                $results[$dokument->getId()] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
