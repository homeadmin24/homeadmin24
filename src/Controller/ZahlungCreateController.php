<?php
// src/Controller/ZahlungCreateController.php

namespace App\Controller;

use App\Entity\Zahlung;
use App\Form\ZahlungType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/zahlung')]
class ZahlungCreateController extends AbstractController
{
    #[Route('/new', name: 'app_zahlung_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $zahlung = new Zahlung();
        // Set default date to today
        $zahlung->setDatum(new \DateTime());

        $form = $this->createForm(ZahlungType::class, $zahlung);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($zahlung);
            $entityManager->flush();

            $this->addFlash('success', 'Zahlung wurde erfolgreich erstellt.');

            // If it's an AJAX request, return a simple success response
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true]);
            }

            return $this->redirectToRoute('app_zahlung_index');
        }

        return $this->render('zahlung/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
