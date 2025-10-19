<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Dokument;
use App\Service\BankStatementParsingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/csv-import')]
class CsvImportController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private string $projectDir,
    ) {
    }

    #[Route('/', name: 'app_csv_import', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('csv_import/index.html.twig');
    }

    #[Route('/upload', name: 'app_csv_upload', methods: ['POST'])]
    public function upload(Request $request, BankStatementParsingService $parsingService): Response
    {
        if (!$this->isCsrfTokenValid('csv_upload', $request->request->get('_token'))) {
            return $this->json(['error' => true, 'message' => 'Ungültiges CSRF-Token.'], 400);
        }

        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->files->get('csv_file');
        if (!$uploadedFile) {
            return $this->json(['error' => true, 'message' => 'Keine Datei hochgeladen.'], 400);
        }

        // Validate file type
        $mimeType = $uploadedFile->getMimeType();
        $allowedMimes = ['text/csv', 'text/plain', 'application/csv'];
        if (!\in_array($mimeType, $allowedMimes, true)) {
            return $this->json(['error' => true, 'message' => 'Nur CSV-Dateien sind erlaubt.'], 400);
        }

        try {
            // Get file info before moving
            $originalName = $uploadedFile->getClientOriginalName();
            $fileSize = $uploadedFile->getSize();
            $originalFilename = pathinfo($originalName, \PATHINFO_FILENAME);
            $originalExtension = pathinfo($originalName, \PATHINFO_EXTENSION);

            // Generate safe filename
            $safeFilename = $this->slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . mb_strtolower($originalExtension);

            // Ensure target directory exists
            $targetDirectory = $this->projectDir . '/data/dokumente/bank-statements';
            if (!file_exists($targetDirectory)) {
                mkdir($targetDirectory, 0755, true);
            }

            // Move the file
            $uploadedFile->move($targetDirectory, $newFilename);

            // Create temporary document entity for parsing
            $dokument = new Dokument();
            $dokument->setDateiname($originalName);
            $dokument->setKategorie('bank-statements');
            $dokument->setDateipfad('bank-statements/' . $newFilename);
            $dokument->setDategroesse($fileSize);
            $dokument->setUploadDatum(new \DateTime());

            // Parse CSV and return preview data
            $previewData = $parsingService->parseCSVPreview($dokument);
            $previewData['filename'] = $newFilename;
            $previewData['originalName'] = $originalName;

            return $this->json($previewData);
        } catch (\Exception $e) {
            error_log('CSV Upload Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->json(['error' => true, 'message' => 'Fehler beim Verarbeiten der CSV: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/import', name: 'app_csv_import_execute', methods: ['POST'])]
    public function import(Request $request, BankStatementParsingService $parsingService): Response
    {
        if (!$this->isCsrfTokenValid('csv_import', $request->request->get('_token'))) {
            $this->addFlash('error', 'Ungültiges CSRF-Token.');

            return $this->redirectToRoute('app_csv_import');
        }

        $filename = $request->request->get('filename');
        if (!$filename) {
            $this->addFlash('error', 'Keine Datei angegeben.');

            return $this->redirectToRoute('app_csv_import');
        }

        try {
            // Create and save document entity
            $dokument = new Dokument();
            $dokument->setDateiname($request->request->get('original_name', 'import.csv'));
            $dokument->setKategorie('bank-statements');
            $dokument->setDateipfad('bank-statements/' . $filename);
            $dokument->setDategroesse(filesize($this->projectDir . '/data/dokumente/bank-statements/' . $filename));
            $dokument->setUploadDatum(new \DateTime());
            $dokument->setBeschreibung('CSV Import - ' . date('d.m.Y H:i'));

            $this->entityManager->persist($dokument);
            $this->entityManager->flush();

            // Import transactions
            $options = [
                'import_mode' => $request->request->get('import_mode', 'all'),
                'create_providers' => $request->request->getBoolean('create_providers', true),
            ];

            $result = $parsingService->importTransactions($dokument, $options);

            // Update document description with import info
            $dokument->setBeschreibung($dokument->getBeschreibung() . "\n[Importiert: " . date('d.m.Y H:i') .
                " - {$result['imported']} Zahlungen, {$result['skipped']} Duplikate, {$result['newProviders']} neue Dienstleister, {$result['categorized']} kategorisiert]");
            $this->entityManager->flush();

            $successMessage = \sprintf(
                'CSV erfolgreich importiert: %d neue Zahlungen erstellt, %d Duplikate übersprungen, %d neue Dienstleister angelegt.',
                $result['imported'],
                $result['skipped'],
                $result['newProviders']
            );

            if ($result['categorized'] > 0) {
                $successMessage .= \sprintf(' %d Zahlungen wurden automatisch kategorisiert.', $result['categorized']);
            }

            if ($result['uncategorized'] > 0) {
                $this->addFlash('warning', \sprintf(
                    '%d Zahlungen konnten nicht automatisch kategorisiert werden. Bitte kategorisieren Sie diese manuell.',
                    $result['uncategorized']
                ));
            }

            $this->addFlash('success', $successMessage);

            return $this->redirectToRoute('app_zahlung_index');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Fehler beim Import: ' . $e->getMessage());

            return $this->redirectToRoute('app_csv_import');
        }
    }
}
