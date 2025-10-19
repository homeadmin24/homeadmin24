<?php

namespace App\Controller;

use App\Entity\Zahlung;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/zahlung')]
class ZahlungShowController extends AbstractController
{
    #[Route('/{id}', name: 'app_zahlung_show', methods: ['GET'])]
    public function show(Zahlung $zahlung): Response
    {
        return $this->render('zahlung/show.html.twig', [
            'zahlung' => $zahlung,
        ]);
    }
}
