<?php

namespace App\Controller;

use App\Entity\Dokument;
use App\Form\DokumentType;
use App\Repository\DokumentRepository;
use App\Service\InvoiceProcessingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/dokument')]
class DokumentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/', name: 'app_dokument_index', methods: ['GET'])]
    public function index(Request $request, DokumentRepository $dokumentRepository): Response
    {
        $kategorie = $request->query->get('kategorie');

        $queryBuilder = $dokumentRepository->createQueryBuilder('d')
            ->orderBy('d.uploadDatum', 'DESC');

        if ($kategorie) {
            $queryBuilder->andWhere('d.kategorie = :kategorie')
                        ->setParameter('kategorie', $kategorie);
        }

        $dokumente = $queryBuilder->getQuery()->getResult();

        $kategorieOptions = $dokumentRepository->createQueryBuilder('d')
            ->select('DISTINCT d.kategorie')
            ->where('d.kategorie IS NOT NULL')
            ->orderBy('d.kategorie', 'ASC')
            ->getQuery()
            ->getScalarResult();

        $kategorieOptions = array_column($kategorieOptions, 'kategorie');

        return $this->render('dokument/index.html.twig', [
            'dokumente' => $dokumente,
            'kategorieOptions' => $kategorieOptions,
            'selectedKategorie' => $kategorie,
        ]);
    }

    #[Route('/new', name: 'app_dokument_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, InvoiceProcessingService $invoiceProcessor): Response
    {
        $dokument = new Dokument();

        // Pre-fill category from query parameter
        $kategorie = $request->query->get('kategorie');
        if ($kategorie) {
            $dokument->setKategorie($kategorie);
        }

        $form = $this->createForm(DokumentType::class, $dokument);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFile = $form->get('datei')->getData();

            if ($uploadedFile instanceof UploadedFile) {
                $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), \PATHINFO_FILENAME);
                $originalExtension = pathinfo($uploadedFile->getClientOriginalName(), \PATHINFO_EXTENSION);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . mb_strtolower($originalExtension);

                $kategorie = $dokument->getKategorie() ?: 'uploads';
                $uploadPath = $this->getParameter('kernel.project_dir') . '/data/dokumente/' . $kategorie;

                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }

                // Get file metadata before moving the file
                $originalName = $uploadedFile->getClientOriginalName();
                $mimeType = $uploadedFile->getClientMimeType();
                $fileSize = $uploadedFile->getSize();

                try {
                    $uploadedFile->move($uploadPath, $newFilename);

                    // Set metadata after successful move
                    $dokument->setDateiname($originalName);
                    $dokument->setDateipfad($kategorie . '/' . $newFilename);
                    $dokument->setDateityp($mimeType);
                    $dokument->setDategroesse($fileSize);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Fehler beim Hochladen der Datei.');

                    return $this->redirectToRoute('app_dokument_index');
                }
            }

            $entityManager->persist($dokument);
            $entityManager->flush();

            $this->addFlash('success', 'Dokument wurde erfolgreich hochgeladen.');

            if ($request->query->get('modal')) {
                return new Response('', 204);
            }

            return $this->redirectToRoute('app_dokument_index');
        }

        if ($request->query->get('modal')) {
            return $this->render('dokument/new.html.twig', [
                'dokument' => $dokument,
                'form' => $form,
            ]);
        }

        // Full page template for direct access
        return $this->render('dokument/new_page.html.twig', [
            'dokument' => $dokument,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_dokument_show', methods: ['GET'])]
    public function show(Dokument $dokument, Request $request): Response
    {
        if ($request->query->get('modal')) {
            return $this->render('dokument/show.html.twig', [
                'dokument' => $dokument,
            ]);
        }

        // Full page template for direct access
        return $this->render('dokument/show_page.html.twig', [
            'dokument' => $dokument,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_dokument_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Dokument $dokument, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DokumentType::class, $dokument, ['edit_mode' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Dokument wurde erfolgreich aktualisiert.');

            if ($request->query->get('modal')) {
                return new Response('', 204);
            }

            return $this->redirectToRoute('app_dokument_index');
        }

        if ($request->query->get('modal')) {
            return $this->render('dokument/edit.html.twig', [
                'dokument' => $dokument,
                'form' => $form,
            ]);
        }

        // Redirect to index if accessed directly (template removed)
        return $this->redirectToRoute('app_dokument_index');
    }

    #[Route('/{id}', name: 'app_dokument_delete', methods: ['POST'])]
    public function delete(Request $request, Dokument $dokument, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete', $request->request->get('_token'))) {
            // Delete physical file
            $filePath = $dokument->getAbsoluterPfad($this->getParameter('kernel.project_dir'));
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $entityManager->remove($dokument);
            $entityManager->flush();

            $this->addFlash('success', 'Dokument wurde erfolgreich gelöscht.');
        }

        return $this->redirectToRoute('app_dokument_index');
    }

    #[Route('/{id}/parse', name: 'app_dokument_parse', methods: ['POST'])]
    public function parse(Request $request, Dokument $dokument, InvoiceProcessingService $invoiceProcessor): Response
    {
        if (!$this->isCsrfTokenValid('parse', $request->request->get('_token'))) {
            $this->addFlash('error', 'Ungültiges CSRF-Token.');

            return $this->redirectToRoute('app_dokument_show', ['id' => $dokument->getId()]);
        }

        try {
            $rechnung = $invoiceProcessor->processDocument($dokument);

            if ($rechnung) {
                $this->addFlash('success', \sprintf(
                    'Rechnung erfolgreich erstellt: %s (Betrag: %.2f €)',
                    $rechnung->getRechnungsnummer(),
                    $rechnung->getBetragMitSteuern()
                ));

                // Redirect to the Rechnung index page
                return $this->redirectToRoute('app_rechnung_index');
            }
            $this->addFlash('warning', 'Dokument konnte nicht als Rechnung verarbeitet werden.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Fehler beim Parsen: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_dokument_show', ['id' => $dokument->getId()]);
    }

    #[Route('/{id}/download', name: 'app_dokument_download', methods: ['GET'])]
    public function download(Dokument $dokument): Response
    {
        $filePath = $dokument->getAbsoluterPfad($this->getParameter('kernel.project_dir'));

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Datei nicht gefunden.');
        }

        return $this->file($filePath, $dokument->getDateiname());
    }
}
