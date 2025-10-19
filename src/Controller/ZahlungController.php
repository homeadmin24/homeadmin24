<?php

namespace App\Controller;

use App\Entity\WegEinheit;
use App\Repository\DienstleisterRepository;
use App\Repository\KostenkontoRepository;
use App\Repository\ZahlungRepository;
use App\Repository\ZahlungskategorieRepository;
use App\Service\ZahlungKategorisierungService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/zahlung')]
class ZahlungController extends AbstractController
{
    #[Route('/', name: 'app_zahlung_index', methods: ['GET'])]
    public function index(Request $request, ZahlungRepository $zahlungRepository, KostenkontoRepository $kostenkontoRepository, ZahlungskategorieRepository $zahlungskategorieRepository, DienstleisterRepository $dienstleisterRepository, EntityManagerInterface $entityManager): Response
    {
        // Get filter parameters
        $kostenkontoId = $request->query->get('kostenkonto');
        $zahlungskategorieId = $request->query->get('zahlungskategorie');
        $dienstleisterId = $request->query->get('dienstleister');
        $wegEinheitId = $request->query->get('weg_einheit');
        $onlyUncategorized = $request->query->getBoolean('only_uncategorized');

        // Build criteria for filtering
        if ($onlyUncategorized) {
            // Find payments where hauptkategorie OR kostenkonto is missing
            $zahlungen = $zahlungRepository->findUncategorized();
        } else {
            $criteria = [];
            if ($kostenkontoId) {
                $criteria['kostenkonto'] = $kostenkontoId;
            }
            if ($zahlungskategorieId) {
                $criteria['hauptkategorie'] = $zahlungskategorieId;
            }
            if ($dienstleisterId) {
                $criteria['dienstleister'] = $dienstleisterId;
            }
            if ($wegEinheitId) {
                $criteria['eigentuemer'] = $wegEinheitId;
            }

            // Get filtered payments
            $zahlungen = $zahlungRepository->findBy(
                $criteria,
                ['datum' => 'DESC']
            );
        }

        // Get all data for filter dropdowns
        $kostenkontos = $kostenkontoRepository->findBy(['isActive' => true], ['nummer' => 'ASC']);
        $zahlungskategorien = $zahlungskategorieRepository->findBy([], ['name' => 'ASC']);
        $dienstleister = $dienstleisterRepository->findServiceProvidersOnly();
        $wegEinheiten = $entityManager->getRepository(WegEinheit::class)->findBy([], ['nummer' => 'ASC']);

        return $this->render('zahlung/index.html.twig', [
            'zahlungen' => $zahlungen,
            'kostenkontos' => $kostenkontos,
            'zahlungskategorien' => $zahlungskategorien,
            'dienstleister' => $dienstleister,
            'wegEinheiten' => $wegEinheiten,
            'selectedKostenkonto' => $kostenkontoId,
            'selectedZahlungskategorie' => $zahlungskategorieId,
            'selectedDienstleister' => $dienstleisterId,
            'selectedWegEinheit' => $wegEinheitId,
            'onlyUncategorized' => $onlyUncategorized,
        ]);
    }

    #[Route('/bulk-kategorisieren', name: 'app_zahlung_bulk_kategorisieren', methods: ['POST'])]
    public function bulkKategorisieren(
        Request $request,
        ZahlungRepository $zahlungRepository,
        ZahlungKategorisierungService $kategorisierungService,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('bulk_kategorisieren', $request->request->get('_token'))) {
            $this->addFlash('error', 'Ungültiges CSRF-Token.');

            return $this->redirectToRoute('app_zahlung_index');
        }

        // Get all uncategorized payments (missing hauptkategorie OR kostenkonto)
        $zahlungen = $zahlungRepository->findUncategorized();

        $categorized = 0;
        foreach ($zahlungen as $zahlung) {
            if ($kategorisierungService->kategorisieren($zahlung)) {
                ++$categorized;
            }
        }

        $entityManager->flush();

        $uncategorized = \count($zahlungen) - $categorized;

        if ($categorized > 0) {
            $this->addFlash('success', \sprintf(
                '%d Zahlungen wurden automatisch kategorisiert.',
                $categorized
            ));
        }

        if ($uncategorized > 0) {
            $this->addFlash('warning', \sprintf(
                '%d Zahlungen konnten nicht automatisch kategorisiert werden und benötigen manuelle Kategorisierung.',
                $uncategorized
            ));
        }

        if (0 === $categorized && 0 === $uncategorized) {
            $this->addFlash('info', 'Alle Zahlungen sind bereits kategorisiert.');
        }

        return $this->redirectToRoute('app_zahlung_index');
    }
}
