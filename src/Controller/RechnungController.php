<?php
// src/Controller/RechnungController.php

namespace App\Controller;

use App\Entity\Dienstleister;
use App\Entity\Rechnung;
use App\Form\RechnungType;
use App\Repository\DienstleisterRepository;
use App\Repository\RechnungRepository;
use App\Repository\ZahlungRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/rechnung')]
class RechnungController extends AbstractController
{
    #[Route('/', name: 'app_rechnung_index', methods: ['GET'])]
    public function index(
        Request $request,
        RechnungRepository $rechnungRepository,
        DienstleisterRepository $dienstleisterRepository,
    ): Response {
        // Get filter parameters
        $selectedDienstleister = $request->query->get('dienstleister');
        $selectedAusstehend = $request->query->get('ausstehend');

        // Build query
        $queryBuilder = $rechnungRepository->createQueryBuilder('r')
            ->leftJoin('r.dienstleister', 'd')
            ->orderBy('r.id', 'DESC');

        if ($selectedDienstleister) {
            $queryBuilder->andWhere('r.dienstleister = :dienstleister')
                ->setParameter('dienstleister', $selectedDienstleister);
        }

        if (null !== $selectedAusstehend && '' !== $selectedAusstehend) {
            $queryBuilder->andWhere('r.ausstehend = :ausstehend')
                ->setParameter('ausstehend', (bool) $selectedAusstehend);
        }

        $rechnungen = $queryBuilder->getQuery()->getResult();

        return $this->render('rechnung/index.html.twig', [
            'rechnungen' => $rechnungen,
            'dienstleister' => $dienstleisterRepository->findBy([], ['bezeichnung' => 'ASC']),
            'selectedDienstleister' => $selectedDienstleister,
            'selectedAusstehend' => $selectedAusstehend,
        ]);
    }

    #[Route('/new', name: 'app_rechnung_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ZahlungRepository $zahlungRepository): Response
    {
        $rechnung = new Rechnung();
        $form = $this->createForm(RechnungType::class, $rechnung);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Save the Rechnung first
            $entityManager->persist($rechnung);
            $entityManager->flush();

            // Check if we need to link this Rechnung to a payment
            $zahlungId = $request->request->get('zahlung_id');
            if ($zahlungId) {
                $zahlung = $zahlungRepository->find($zahlungId);
                if ($zahlung) {
                    // Link the payment to the newly created Rechnung
                    $zahlung->setRechnung($rechnung);
                    $entityManager->flush();

                    $this->addFlash('success', 'Rechnung wurde erfolgreich erstellt und mit der Zahlung verknüpft.');
                } else {
                    $this->addFlash('warning', 'Rechnung wurde erstellt, aber die Zahlung konnte nicht gefunden werden.');
                }
            } else {
                $this->addFlash('success', 'Rechnung wurde erfolgreich erstellt.');
            }

            return $this->redirectToRoute('app_rechnung_index', [], Response::HTTP_SEE_OTHER);
        }

        // Check if this is a modal request
        if ('XMLHttpRequest' === $request->headers->get('X-Requested-With') || $request->query->get('modal')) {
            return $this->render('rechnung/new.html.twig', [
                'rechnung' => $rechnung,
                'form' => $form,
            ]);
        }

        // Redirect to index if accessed directly (template removed)
        return $this->redirectToRoute('app_rechnung_index');
    }

    #[Route('/{id}', name: 'app_rechnung_show', methods: ['GET'])]
    public function show(Request $request, Rechnung $rechnung): Response
    {
        // Check if this is a modal request
        if ('XMLHttpRequest' === $request->headers->get('X-Requested-With') || $request->query->get('modal')) {
            return $this->render('rechnung/show.html.twig', [
                'rechnung' => $rechnung,
            ]);
        }

        // Redirect to index if accessed directly (template removed)
        return $this->redirectToRoute('app_rechnung_index');
    }

    #[Route('/{id}/edit', name: 'app_rechnung_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Rechnung $rechnung, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(RechnungType::class, $rechnung);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Rechnung wurde erfolgreich aktualisiert.');

            return $this->redirectToRoute('app_rechnung_index', [], Response::HTTP_SEE_OTHER);
        }

        // Check if this is a modal request
        if ('XMLHttpRequest' === $request->headers->get('X-Requested-With') || $request->query->get('modal')) {
            return $this->render('rechnung/edit.html.twig', [
                'rechnung' => $rechnung,
                'form' => $form,
            ]);
        }

        // Redirect to index if accessed directly (template removed)
        return $this->redirectToRoute('app_rechnung_index');
    }

    #[Route('/{id}', name: 'app_rechnung_delete', methods: ['POST'])]
    public function delete(Request $request, Rechnung $rechnung, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete', $request->request->get('_token'))) {
            $entityManager->remove($rechnung);
            $entityManager->flush();

            $this->addFlash('success', 'Rechnung wurde erfolgreich gelöscht.');
        }

        return $this->redirectToRoute('app_rechnung_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/by-dienstleister/{id}', name: 'app_rechnung_by_dienstleister', methods: ['GET'])]
    public function getByDienstleister(Dienstleister $dienstleister, RechnungRepository $rechnungRepository): Response
    {
        $rechnungen = $rechnungRepository->findBy(['dienstleister' => $dienstleister], ['id' => 'DESC']);

        // Format for JSON response
        $formattedRechnungen = [];
        foreach ($rechnungen as $rechnung) {
            $formattedRechnungen[] = [
                'id' => $rechnung->getId(),
                'information' => $rechnung->getInformation(),
                'rechnungsnummer' => $rechnung->getRechnungsnummer(),
                'betragMitSteuern' => $rechnung->getBetragMitSteuern(),
            ];
        }

        return $this->json(['rechnungen' => $formattedRechnungen]);
    }
}
