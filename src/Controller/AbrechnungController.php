<?php

namespace App\Controller;

use App\Entity\Dokument;
use App\Entity\Weg;
use App\Entity\WegEinheit;
use App\Form\AbrechnungGenerateType;
use App\Repository\WegEinheitRepository;
use App\Repository\WegRepository;
use App\Service\Hga\HgaServiceInterface;
use App\Service\Hga\Report\PdfReportGenerator;
use App\Service\Hga\Report\TxtReportGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/abrechnung')]
class AbrechnungController extends AbstractController
{
    public function __construct(
        private HgaServiceInterface $hgaService,
        private TxtReportGenerator $txtReportGenerator,
        private PdfReportGenerator $pdfReportGenerator,
        private EntityManagerInterface $entityManager,
        private WegRepository $wegRepository,
        private WegEinheitRepository $wegEinheitRepository,
    ) {
    }

    #[Route('/', name: 'app_abrechnung_index')]
    public function index(Request $request): Response
    {
        $form = $this->createForm(AbrechnungGenerateType::class);
        $form->handleRequest($request);

        $generatedFiles = [];
        $errors = [];

        if ($form->isSubmitted()) {
            // Debug form submission
            if (!$form->isValid()) {
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    if ($error instanceof \Symfony\Component\Form\FormError) {
                        $errors[] = $error->getMessage();
                    }
                }
                foreach ($form->all() as $child) {
                    if (!$child->isValid()) {
                        foreach ($child->getErrors() as $error) {
                            if ($error instanceof \Symfony\Component\Form\FormError) {
                                $errors[] = $child->getName() . ': ' . $error->getMessage();
                            }
                        }
                    }
                }
                $this->addFlash('error', 'Form validation errors: ' . implode(', ', $errors));
                $this->addFlash('error', 'Form data: ' . json_encode($request->request->all()));

                return $this->redirectToRoute('app_abrechnung_index');
            }
            $data = $form->getData();
            $weg = $data['weg'];
            $jahr = $data['jahr'];
            $format = $data['format'];

            // Get einheiten from request data since it's unmapped
            $einheitenIds = $request->request->all('abrechnung_generate')['einheiten'] ?? [];
            $einheiten = [];
            foreach ($einheitenIds as $einheitId) {
                $einheit = $this->wegEinheitRepository->find($einheitId);
                if ($einheit) {
                    $einheiten[] = $einheit;
                }
            }

            try {
                $generatedFiles = $this->generateAbrechnungen($weg, $jahr, $format, $einheiten);
                $this->addFlash('success', \sprintf(
                    '%d Hausgeldabrechnungen erfolgreich generiert!',
                    \count($generatedFiles)
                ));

                // Redirect to prevent form resubmission and satisfy Turbo
                return $this->redirectToRoute('app_abrechnung_index');
            } catch (\Exception $e) {
                $errors[] = 'Fehler bei der Generierung: ' . $e->getMessage();
                $this->addFlash('error', 'Fehler bei der Generierung der Abrechnungen.');

                // Redirect even on error to satisfy Turbo
                return $this->redirectToRoute('app_abrechnung_index');
            }
        }

        return $this->render('abrechnung/index.html.twig', [
            'form' => $form,
            'generatedFiles' => $generatedFiles,
            'errors' => $errors,
        ]);
    }

    #[Route('/einheiten/{wegId}', name: 'app_abrechnung_einheiten', methods: ['GET'])]
    public function getEinheiten(int $wegId): JsonResponse
    {
        $weg = $this->wegRepository->find($wegId);
        if (!$weg) {
            return new JsonResponse(['error' => 'WEG nicht gefunden'], 404);
        }

        $einheiten = $this->wegEinheitRepository->findBy(['weg' => $weg], ['nummer' => 'ASC']);

        $result = [];
        foreach ($einheiten as $einheit) {
            $result[] = [
                'id' => $einheit->getId(),
                'nummer' => $einheit->getNummer(),
                'bezeichnung' => $einheit->getBezeichnung(),
                'miteigentuemer' => $einheit->getMiteigentuemer(),
            ];
        }

        return new JsonResponse(['einheiten' => $result]);
    }

    #[Route('/{year}/preview/{unitNumber}', name: 'app_abrechnung_preview', methods: ['GET'])]
    public function preview(int $year, string $unitNumber): Response
    {
        try {
            // Find the unit by number (assuming it's from WEG 3 - Musterstraße 35)
            $weg = $this->wegRepository->find(3); // WEG Musterstraße 35
            if (!$weg) {
                throw new \Exception('WEG not found');
            }

            $einheit = $this->wegEinheitRepository->findOneBy([
                'weg' => $weg,
                'nummer' => $unitNumber,
            ]);

            if (!$einheit) {
                throw new \Exception(\sprintf('Unit %s not found in WEG %s', $unitNumber, $weg->getBezeichnung()));
            }

            // Validate inputs
            $errors = $this->hgaService->validateCalculationInputs($einheit, $year);
            if (!empty($errors)) {
                throw new \Exception('Validation failed: ' . implode(', ', $errors));
            }

            // Generate report content using TXT generator for preview
            $reportContent = $this->txtReportGenerator->generateReport($einheit, $year, [
                'format' => 'txt',
            ]);

            // Return as HTML with proper formatting
            return $this->render('abrechnung/preview.html.twig', [
                'reportContent' => $reportContent,
                'einheit' => $einheit,
                'weg' => $weg,
                'year' => $year,
                'unitNumber' => $unitNumber,
                'generatedAt' => new \DateTime(),
            ]);
        } catch (\Exception $e) {
            // Return error page
            return $this->render('abrechnung/preview_error.html.twig', [
                'error' => $e->getMessage(),
                'year' => $year,
                'unitNumber' => $unitNumber,
                'generatedAt' => new \DateTime(),
            ]);
        }
    }

    /**
     * @param WegEinheit[] $einheiten
     *
     * @return Dokument[]
     */
    private function generateAbrechnungen(Weg $weg, int $jahr, string $format, array $einheiten): array
    {
        $generatedFiles = [];

        foreach ($einheiten as $einheit) {
            $formats = 'both' === $format ? ['pdf', 'txt'] : [$format];

            foreach ($formats as $currentFormat) {
                try {
                    // Validate inputs first
                    $errors = $this->hgaService->validateCalculationInputs($einheit, $jahr);
                    if (!empty($errors)) {
                        throw new \Exception('Validation failed: ' . implode(', ', $errors));
                    }

                    // Select appropriate generator based on format
                    $generator = match ($currentFormat) {
                        'txt' => $this->txtReportGenerator,
                        'pdf' => $this->pdfReportGenerator,
                        default => throw new \Exception("Unsupported format: $currentFormat"),
                    };

                    // Generate report content using new HGA service
                    $reportContent = $generator->generateReport($einheit, $jahr, [
                        'format' => $currentFormat,
                    ]);

                    // Create temporary file
                    $tempDir = sys_get_temp_dir();
                    $fileName = \sprintf('hausgeldabrechnung_%d_%s_%s.%s',
                        $jahr,
                        $weg->getId(),
                        $einheit->getNummer(),
                        $currentFormat
                    );
                    $filePath = $tempDir . '/' . $fileName;
                    file_put_contents($filePath, $reportContent);

                    // Save to dokument system
                    $dokument = $this->saveToDocumentSystem($filePath, $weg, $einheit, $jahr, $currentFormat);
                    $generatedFiles[] = $dokument;
                } catch (\Exception $e) {
                    // Log error and continue with next file
                    error_log(\sprintf('Error generating %s for unit %s: %s', $currentFormat, $einheit->getNummer(), $e->getMessage()));

                    // Also add flash message for user feedback
                    $this->addFlash('error', \sprintf(
                        'Fehler bei der Generierung von %s für Einheit %s: %s',
                        mb_strtoupper($currentFormat),
                        $einheit->getNummer(),
                        $e->getMessage()
                    ));
                }
            }
        }

        return $generatedFiles;
    }

    private function saveToDocumentSystem(string $filePath, Weg $weg, WegEinheit $einheit, int $jahr, string $format): Dokument
    {
        $fileName = basename($filePath);
        $relativePath = 'hausgeldabrechnung/' . $fileName;

        // Move file to dokument directory
        $projectDir = $this->getParameter('kernel.project_dir');
        $targetDir = (string) $projectDir . '/data/dokumente/hausgeldabrechnung';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . '/' . $fileName;
        if (file_exists($filePath)) {
            copy($filePath, $targetPath);
        }

        // Create dokument record
        $dokument = new Dokument();
        $dokument->setDateiname($fileName)
            ->setDateipfad($relativePath)
            ->setDateityp($format)
            ->setDategroesse(filesize($targetPath) ?: 0)
            ->setKategorie('hausgeldabrechnung')
            ->setBeschreibung(\sprintf(
                'Hausgeldabrechnung %d für %s %s (%s)',
                $jahr,
                $einheit->getNummer(),
                $einheit->getBezeichnung(),
                mb_strtoupper($format)
            ))
            ->setWeg($weg)
            ->setAbrechnungsJahr($jahr)
            ->setEinheitNummer($einheit->getNummer())
            ->setFormat($format);

        $this->entityManager->persist($dokument);
        $this->entityManager->flush();

        return $dokument;
    }
}
