<?php

namespace App\Controller;

use App\Repository\ZahlungRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ZahlungRepository $zahlungRepository): Response
    {
        // You could add statistics for the dashboard here
        // For example, get the total amounts for expenses and income
        $allPayments = $zahlungRepository->findAll();

        $totalIncome = 0;
        $totalExpenses = 0;
        $openInvoicesCount = 0; // Placeholder for future functionality

        foreach ($allPayments as $payment) {
            $amount = (float) $payment->getBetrag();
            if ($amount > 0) {
                $totalIncome += $amount;
            } else {
                $totalExpenses += abs($amount);
            }
        }

        $totalCosts = $totalExpenses; // In a real app, this might be calculated differently

        return $this->render('index.html.twig', [
            'totalCosts' => $totalCosts,
            'totalIncome' => $totalIncome,
            'totalExpenses' => $totalExpenses,
            'openInvoicesCount' => $openInvoicesCount,
        ]);
    }
}
