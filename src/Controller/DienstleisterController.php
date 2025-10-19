<?php
// src/Controller/DienstleisterController.php

namespace App\Controller;

use App\Entity\Dienstleister;
use App\Form\DienstleisterType;
use App\Repository\DienstleisterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/dienstleister')]
class DienstleisterController extends AbstractController
{
    #[Route('/', name: 'app_dienstleister_index', methods: ['GET'])]
    public function index(
        Request $request,
        DienstleisterRepository $dienstleisterRepository,
    ): Response {
        // Get filter parameters
        $selectedArtDienstleister = $request->query->get('artDienstleister');

        // Build query
        $queryBuilder = $dienstleisterRepository->createQueryBuilder('d')
            ->orderBy('d.bezeichnung', 'ASC');

        if ($selectedArtDienstleister) {
            $queryBuilder->andWhere('d.artDienstleister = :artDienstleister')
                ->setParameter('artDienstleister', $selectedArtDienstleister);
        }

        $dienstleister = $queryBuilder->getQuery()->getResult();

        // Get unique artDienstleister values for filter
        $artDienstleisterOptions = $dienstleisterRepository->createQueryBuilder('d')
            ->select('DISTINCT d.artDienstleister')
            ->where('d.artDienstleister IS NOT NULL')
            ->orderBy('d.artDienstleister', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('dienstleister/index.html.twig', [
            'dienstleister' => $dienstleister,
            'artDienstleisterOptions' => array_column($artDienstleisterOptions, 'artDienstleister'),
            'selectedArtDienstleister' => $selectedArtDienstleister,
        ]);
    }

    #[Route('/new', name: 'app_dienstleister_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $dienstleister = new Dienstleister();
        $form = $this->createForm(DienstleisterType::class, $dienstleister);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($dienstleister);
            $entityManager->flush();

            $this->addFlash('success', 'Dienstleister wurde erfolgreich erstellt.');

            return $this->redirectToRoute('app_dienstleister_index', [], Response::HTTP_SEE_OTHER);
        }

        // Check if this is a modal request
        if ('XMLHttpRequest' === $request->headers->get('X-Requested-With') || $request->query->get('modal')) {
            return $this->render('dienstleister/new.html.twig', [
                'dienstleister' => $dienstleister,
                'form' => $form,
            ]);
        }

        // Redirect to index if accessed directly (template removed)
        return $this->redirectToRoute('app_dienstleister_index');
    }

    #[Route('/{id}', name: 'app_dienstleister_show', methods: ['GET'])]
    public function show(Request $request, Dienstleister $dienstleister): Response
    {
        // Check if this is a modal request
        if ('XMLHttpRequest' === $request->headers->get('X-Requested-With') || $request->query->get('modal')) {
            return $this->render('dienstleister/show.html.twig', [
                'dienstleister' => $dienstleister,
            ]);
        }

        // Redirect to index if accessed directly (template removed)
        return $this->redirectToRoute('app_dienstleister_index');
    }

    #[Route('/{id}/edit', name: 'app_dienstleister_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Dienstleister $dienstleister, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DienstleisterType::class, $dienstleister);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Dienstleister wurde erfolgreich aktualisiert.');

            return $this->redirectToRoute('app_dienstleister_index', [], Response::HTTP_SEE_OTHER);
        }

        // Check if this is a modal request
        if ('XMLHttpRequest' === $request->headers->get('X-Requested-With') || $request->query->get('modal')) {
            return $this->render('dienstleister/edit.html.twig', [
                'dienstleister' => $dienstleister,
                'form' => $form,
            ]);
        }

        // Redirect to index if accessed directly (template removed)
        return $this->redirectToRoute('app_dienstleister_index');
    }

    #[Route('/{id}', name: 'app_dienstleister_delete', methods: ['POST'])]
    public function delete(Request $request, Dienstleister $dienstleister, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $dienstleister->getId(), $request->request->get('_token'))) {
            $entityManager->remove($dienstleister);
            $entityManager->flush();

            $this->addFlash('success', 'Dienstleister wurde erfolgreich gelöscht.');
        }

        return $this->redirectToRoute('app_dienstleister_index', [], Response::HTTP_SEE_OTHER);
    }
}
