<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\AiQueryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/ai', name: 'api_ai_')]
#[IsGranted('ROLE_USER')]
class AiQueryController extends AbstractController
{
    public function __construct(
        private readonly AiQueryService $aiQueryService,
    ) {
    }

    /**
     * Answer with Ollama (local, DSGVO-compliant)
     *
     * POST /api/ai/query/ollama
     * Body: {"query": "Wie viel haben wir 2024 für Heizung ausgegeben?"}
     */
    #[Route('/query/ollama', name: 'query_ollama', methods: ['POST'])]
    public function queryOllama(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['query']) || empty(trim($data['query']))) {
            return $this->json([
                'success' => false,
                'error' => 'Query parameter is required',
            ], 400);
        }

        $query = trim($data['query']);

        try {
            $result = $this->aiQueryService->answerWithOllama($query);

            return $this->json($result, $result['success'] ? 200 : 500);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Answer with Claude (cloud API, dev-only)
     *
     * POST /api/ai/query/claude
     * Body: {"query": "Wie viel haben wir 2024 für Heizung ausgegeben?"}
     */
    #[Route('/query/claude', name: 'query_claude', methods: ['POST'])]
    public function queryClaude(Request $request): JsonResponse
    {
        // Only allow in development
        if ('dev' !== $_ENV['APP_ENV']) {
            return $this->json([
                'success' => false,
                'error' => 'Claude queries are only available in development environment',
            ], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['query']) || empty(trim($data['query']))) {
            return $this->json([
                'success' => false,
                'error' => 'Query parameter is required',
            ], 400);
        }

        $query = trim($data['query']);

        try {
            $result = $this->aiQueryService->answerWithClaude($query);

            return $this->json($result, $result['success'] ? 200 : 500);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Compare answers from Ollama and Claude side-by-side
     *
     * POST /api/ai/query/compare
     * Body: {"query": "Wie viel haben wir 2024 für Heizung ausgegeben?"}
     */
    #[Route('/query/compare', name: 'query_compare', methods: ['POST'])]
    public function queryCompare(Request $request): JsonResponse
    {
        // Only allow in development
        if ('dev' !== $_ENV['APP_ENV']) {
            return $this->json([
                'success' => false,
                'error' => 'Comparison queries are only available in development environment',
            ], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['query']) || empty(trim($data['query']))) {
            return $this->json([
                'success' => false,
                'error' => 'Query parameter is required',
            ], 400);
        }

        $query = trim($data['query']);

        try {
            $result = $this->aiQueryService->compareProviders($query);

            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rate an AI response (for learning)
     *
     * POST /api/ai/response/{id}/rate
     * Body: {"rating": "good"} or {"rating": "bad"}
     */
    #[Route('/response/{id}/rate', name: 'rate_response', methods: ['POST'])]
    public function rateResponse(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['rating']) || !\in_array($data['rating'], ['good', 'bad'], true)) {
            return $this->json([
                'success' => false,
                'error' => 'Rating must be "good" or "bad"',
            ], 400);
        }

        $success = $this->aiQueryService->rateResponse($id, $data['rating']);

        return $this->json([
            'success' => $success,
            'message' => $success ? 'Rating saved' : 'Response not found',
        ]);
    }

    /**
     * Legacy endpoint - defaults to Ollama
     *
     * POST /api/ai/query
     * @deprecated Use /api/ai/query/ollama or /api/ai/query/claude
     */
    #[Route('/query', name: 'query', methods: ['POST'])]
    public function query(Request $request): JsonResponse
    {
        return $this->queryOllama($request);
    }

    /**
     * Get example queries for the UI
     */
    #[Route('/query/examples', name: 'query_examples', methods: ['GET'])]
    public function getExamples(): JsonResponse
    {
        $examples = [
            [
                'category' => 'Kosten & Ausgaben',
                'queries' => [
                    'Wie viel haben wir 2024 für Heizung ausgegeben?',
                    'Was haben wir insgesamt 2024 ausgegeben?',
                    'Wie hoch waren die Hausmeisterkosten in 2024?',
                ],
            ],
            [
                'category' => 'Eigentümer',
                'queries' => [
                    'Hat Herr Müller alle Vorauszahlungen für 2024 bezahlt?',
                    'Welche Zahlungen gab es von Einheit 0003?',
                ],
            ],
            [
                'category' => 'Vergleich & Trends',
                'queries' => [
                    'Welche Kostenpositionen sind 2024 am stärksten gestiegen?',
                    'Wie haben sich die Heizkosten im Vergleich zum Vorjahr entwickelt?',
                    'Was kostet uns 2024 mehr als 2023?',
                ],
            ],
        ];

        return $this->json([
            'success' => true,
            'examples' => $examples,
        ]);
    }
}
