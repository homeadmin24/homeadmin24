<?php

namespace App\Controller;

use App\Entity\Dokument;
use App\Entity\Weg;
use App\Entity\WegEinheit;
use App\Form\AbrechnungGenerateType;
use App\Repository\WegEinheitRepository;
use App\Repository\WegRepository;
use App\Service\Hga\HgaServiceInterface;
use App\Service\Hga\ReportGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/hga')]
class HgaController extends AbstractController
{
    public function __construct(
        private HgaServiceInterface $hgaService,
        private ReportGeneratorInterface $txtReportGenerator,
        private EntityManagerInterface $entityManager,
        private WegRepository $wegRepository,
        private WegEinheitRepository $wegEinheitRepository,
    ) {
    }

    #[Route('/', name: 'app_hga_index')]
    public function index(Request $request): Response
    {
        $form = $this->createForm(AbrechnungGenerateType::class);
        $form->handleRequest($request);

        $generatedFiles = [];
        $errors = [];

        if ($form->isSubmitted()) {
            // Debug form submission
            if (!$form->isValid()) {
                $errors = $this->extractFormErrors($form);
                $this->addFlash('error', 'Form validation errors: ' . implode(', ', $errors));

                return $this->redirectToRoute('app_hga_index');
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

                return $this->redirectToRoute('app_hga_index');
            } catch (\Exception $e) {
                $errors[] = 'Fehler bei der Generierung: ' . $e->getMessage();
                $this->addFlash('error', 'Fehler bei der Generierung der Abrechnungen: ' . $e->getMessage());

                return $this->redirectToRoute('app_hga_index');
            }
        }

        return $this->render('abrechnung/index.html.twig', [
            'form' => $form,
            'generatedFiles' => $generatedFiles,
            'errors' => $errors,
        ]);
    }

    #[Route('/einheiten/{wegId}', name: 'app_hga_einheiten', methods: ['GET'])]
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

    #[Route('/generate/{einheitId}/{year}', name: 'app_hga_generate_single', methods: ['GET'])]
    public function generateSingle(int $einheitId, int $year): Response
    {
        $einheit = $this->wegEinheitRepository->find($einheitId);
        if (!$einheit) {
            throw $this->createNotFoundException('Unit not found');
        }

        try {
            // Validate inputs using HGA service
            $errors = $this->hgaService->validateCalculationInputs($einheit, $year);
            if (!empty($errors)) {
                throw new \InvalidArgumentException('Validation errors: ' . implode(', ', $errors));
            }

            // Generate TXT report
            $txtContent = $this->txtReportGenerator->generateReport($einheit, $year);

            // Return as download
            $filename = \sprintf('hausgeldabrechnung_%d_%s_%s.txt',
                $year,
                $einheit->getWeg()->getId(),
                $einheit->getNummer()
            );

            return new Response($txtContent, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Fehler bei der Generierung: ' . $e->getMessage());

            return $this->redirectToRoute('app_hga_index');
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
                    // Validate inputs
                    $errors = $this->hgaService->validateCalculationInputs($einheit, $jahr);
                    if (!empty($errors)) {
                        throw new \InvalidArgumentException('Validation errors: ' . implode(', ', $errors));
                    }

                    // Generate report using new HGA service
                    if ('pdf' === $currentFormat) {
                        // For now, use TXT generator until PDF is implemented
                        $content = $this->txtReportGenerator->generateReport($einheit, $jahr);
                        $filePath = $this->saveReportToFile($content, $weg, $einheit, $jahr, 'txt');
                    } else {
                        $content = $this->txtReportGenerator->generateReport($einheit, $jahr);
                        $filePath = $this->saveReportToFile($content, $weg, $einheit, $jahr, $currentFormat);
                    }

                    // Save to document system
                    $dokument = $this->saveToDocumentSystem($filePath, $weg, $einheit, $jahr, $currentFormat);
                    $generatedFiles[] = $dokument;
                } catch (\Exception $e) {
                    // Log error and continue with next file
                    error_log(\sprintf('Error generating %s for unit %s: %s', $currentFormat, $einheit->getNummer(), $e->getMessage()));
                    throw $e; // Re-throw to show user the error
                }
            }
        }

        return $generatedFiles;
    }

    private function saveReportToFile(string $content, Weg $weg, WegEinheit $einheit, int $jahr, string $format): string
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $varDir = $projectDir . '/var/hausgeldabrechnung';

        if (!is_dir($varDir)) {
            mkdir($varDir, 0755, true);
        }

        $filename = \sprintf('hausgeldabrechnung_%d_%s_%s.%s',
            $jahr,
            $weg->getId(),
            $einheit->getNummer(),
            $format
        );

        $filePath = $varDir . '/' . $filename;
        file_put_contents($filePath, $content);

        return $filePath;
    }

    private function saveToDocumentSystem(string $filePath, Weg $weg, WegEinheit $einheit, int $jahr, string $format): Dokument
    {
        $fileName = basename($filePath);
        $relativePath = 'hausgeldabrechnung/' . $fileName;

        // Move file to dokument directory
        $projectDir = $this->getParameter('kernel.project_dir');
        $targetDir = $projectDir . '/data/dokumente/hausgeldabrechnung';
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

    /**
     * @return array<string>
     */
    private function extractFormErrors(\Symfony\Component\Form\FormInterface $form): array
    {
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

        return $errors;
    }
}
