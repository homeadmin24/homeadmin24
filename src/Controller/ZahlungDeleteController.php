<?php
// src/Controller/ZahlungDeleteController.php

namespace App\Controller;

use App\Entity\Zahlung;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/zahlung')]
class ZahlungDeleteController extends AbstractController
{
    #[Route('/{id}', name: 'app_zahlung_delete', methods: ['POST'])]
    public function delete(Request $request, Zahlung $zahlung, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete', $request->request->get('_token'))) {
            $entityManager->remove($zahlung);
            $entityManager->flush();

            $this->addFlash('success', 'Zahlung wurde erfolgreich gelöscht.');
        }

        return $this->redirectToRoute('app_zahlung_index');
    }
}
