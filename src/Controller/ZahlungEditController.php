<?php

namespace App\Controller;

use App\Entity\Zahlung;
use App\Form\ZahlungType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/zahlung')]
class ZahlungEditController extends AbstractController
{
    #[Route('/{id}/edit', name: 'app_zahlung_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Zahlung $zahlung, EntityManagerInterface $entityManager): Response
    {
        $session = $request->getSession();
        if ($request->isMethod('GET')) {
            $referer = $request->headers->get('referer');
            $refererPath = $referer ? parse_url($referer, \PHP_URL_PATH) : null;
            if ($referer && ('/zahlung' === $refererPath || '/zahlung/' === $refererPath)) {
                $session->set('zahlung_edit_return', $referer);
            }
        }

        $form = $this->createForm(ZahlungType::class, $zahlung);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Zahlung wurde erfolgreich bearbeitet.');

            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true]);
            }

            $returnUrl = $session->get('zahlung_edit_return');
            if (\is_string($returnUrl) && '' !== $returnUrl) {
                $session->remove('zahlung_edit_return');

                return $this->redirect($returnUrl);
            }

            return $this->redirectToRoute('app_zahlung_index');
        }

        return $this->render('zahlung/edit.html.twig', [
            'zahlung' => $zahlung,
            'form' => $form->createView(),
        ]);
    }
}
