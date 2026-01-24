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
use Symfony\Component\Routing\Attribute\Route;

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
        $abrechnungsjahr = $request->query->get('abrechnungsjahr');
        $onlyUncategorized = $request->query->getBoolean('only_uncategorized');
        $showSimulations = $request->query->getBoolean('show_simulations', true);
        $transactionType = $request->query->get('transaction_type'); // 'all', 'income', 'expense'

        // Get date range parameters
        $startDateString = $request->query->get('start_datum');
        $endDateString = $request->query->get('end_datum');

        $startDate = null;
        $endDate = null;

        if ($startDateString) {
            try {
                $startDate = new \DateTime($startDateString);
                $startDate->setTime(0, 0, 0);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Ungültiges Startdatum');
            }
        }

        if ($endDateString) {
            try {
                $endDate = new \DateTime($endDateString);
                $endDate->setTime(23, 59, 59);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Ungültiges Enddatum');
            }
        }

        // Build criteria for filtering
        if ($onlyUncategorized) {
            // Find payments where hauptkategorie OR kostenkonto is missing
            $zahlungen = $zahlungRepository->findUncategorized();
        } elseif ($startDate || $endDate) {
            // Use new flexible filtering method when date range is provided
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
            if ($abrechnungsjahr) {
                $criteria['abrechnungsjahrZuordnung'] = $abrechnungsjahr;
            }

            $zahlungen = $zahlungRepository->findByFilters($criteria, $startDate, $endDate);
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
            if ($abrechnungsjahr) {
                $criteria['abrechnungsjahrZuordnung'] = $abrechnungsjahr;
            }

            // Get filtered payments
            $zahlungen = $zahlungRepository->findBy(
                $criteria,
                ['datum' => 'DESC']
            );
        }

        // Apply transaction type filter (income/expense) after fetching
        if ('income' === $transactionType) {
            $zahlungen = array_values(array_filter($zahlungen, fn ($z) => $z->getBetrag() > 0));
        } elseif ('expense' === $transactionType) {
            $zahlungen = array_values(array_filter($zahlungen, fn ($z) => $z->getBetrag() < 0));
        }
        if (!$showSimulations) {
            $zahlungen = array_values(array_filter($zahlungen, fn ($z) => !$z->isSimulation()));
        }

        // Calculate saldo (sum of all filtered payments)
        $saldo = 0;
        $incomeSum = 0.0;
        $expenseSum = 0.0;
        $incomeCount = 0;
        $expenseCount = 0;
        foreach ($zahlungen as $zahlung) {
            $betrag = $zahlung->getBetrag();
            $saldo += $betrag;
            if ($betrag > 0) {
                $incomeSum += $betrag;
                ++$incomeCount;
            } elseif ($betrag < 0) {
                $expenseSum += $betrag;
                ++$expenseCount;
            }
        }

        // Check if any filter is active
        $hasActiveFilter = $kostenkontoId || $zahlungskategorieId || $dienstleisterId || $wegEinheitId || $abrechnungsjahr || $onlyUncategorized || !$showSimulations || $startDateString || $endDateString || $transactionType;

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
            'selectedAbrechnungsjahr' => $abrechnungsjahr,
            'onlyUncategorized' => $onlyUncategorized,
            'showSimulations' => $showSimulations,
            'startDatum' => $startDateString,
            'endDatum' => $endDateString,
            'transactionType' => $transactionType,
            'saldo' => $saldo,
            'incomeSum' => $incomeSum,
            'expenseSum' => $expenseSum,
            'incomeCount' => $incomeCount,
            'expenseCount' => $expenseCount,
            'hasActiveFilter' => $hasActiveFilter,
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
