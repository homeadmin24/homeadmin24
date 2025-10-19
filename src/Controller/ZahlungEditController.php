<?php

namespace App\Controller;

use App\Entity\Zahlung;
use App\Form\ZahlungType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/zahlung')]
class ZahlungEditController extends AbstractController
{
    #[Route('/{id}/edit', name: 'app_zahlung_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Zahlung $zahlung, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ZahlungType::class, $zahlung);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Zahlung wurde erfolgreich bearbeitet.');

            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true]);
            }

            return $this->redirectToRoute('app_zahlung_index');
        }

        return $this->render('zahlung/edit.html.twig', [
            'zahlung' => $zahlung,
            'form' => $form->createView(),
        ]);
    }
}
