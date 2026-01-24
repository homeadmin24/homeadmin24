<?php

namespace App\Controller;

use App\Entity\Dokument;
use App\Entity\Weg;
use App\Entity\WegEinheit;
use App\Entity\WegKontostand;
use App\Form\AbrechnungGenerateType;
use App\Form\WegKontostandType;
use App\Repository\WegEinheitRepository;
use App\Repository\WegKontostandRepository;
use App\Repository\WegRepository;
use App\Repository\WirtschaftsplanConfigRepository;
use App\Service\Hga\Calculation\PaymentCalculationService;
use App\Service\Hga\HgaServiceInterface;
use App\Service\Hga\Report\PdfReportGenerator;
use App\Service\SystemConfigService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/abrechnung')]
class AbrechnungController extends AbstractController
{
    public function __construct(
        private HgaServiceInterface $hgaService,
        private PdfReportGenerator $pdfReportGenerator,
        private EntityManagerInterface $entityManager,
        private WegRepository $wegRepository,
        private WegEinheitRepository $wegEinheitRepository,
        private WegKontostandRepository $wegKontostandRepository,
        private WirtschaftsplanConfigRepository $wirtschaftsplanConfigRepository,
        private PaymentCalculationService $paymentCalculationService,
    ) {
    }

    #[Route('/', name: 'app_abrechnung_index')]
    public function index(Request $request, SystemConfigService $systemConfigService): Response
    {
        $form = $this->createForm(AbrechnungGenerateType::class);
        $form->handleRequest($request);

        $generatedFiles = [];
        $errors = [];

        if ($form->isSubmitted()) {
            // Debug form submission
            if (!$form->isValid()) {
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    if ($error instanceof \Symfony\Component\Form\FormError) {
                        $errors[] = $error->getMessage();
                    }
                }
                foreach ($form->all() as $child) {
                    if (!$child->isValid()) {
                        foreach ($child->getErrors() as $error) {
                            if ($error instanceof \Symfony\Component\Form\FormError) {
                                $errors[] = $child->getName() . ': ' . $error->getMessage();
                            }
                        }
                    }
                }
                $this->addFlash('error', 'Form validation errors: ' . implode(', ', $errors));
                $this->addFlash('error', 'Form data: ' . json_encode($request->request->all()));

                return $this->redirectToRoute('app_abrechnung_index');
            }
            $data = $form->getData();
            $weg = $data['weg'];
            $jahr = $data['jahr'];
            $format = 'pdf';

            // Get einheiten from request data since it's unmapped
            $einheitenIds = $request->request->all('abrechnung_generate')['einheiten'] ?? [];
            $einheiten = [];
            foreach ($einheitenIds as $einheitId) {
                $einheit = $this->wegEinheitRepository->find($einheitId);
                if ($einheit) {
                    $einheiten[] = $einheit;
                }
            }

            try {
                $generatedFiles = $this->generateAbrechnungen($weg, $jahr, $format, $einheiten);
                $this->addFlash('success', \sprintf(
                    '%d Hausgeldabrechnungen erfolgreich generiert!',
                    \count($generatedFiles)
                ));

                // Redirect to prevent form resubmission and satisfy Turbo
                return $this->redirectToRoute('app_abrechnung_index');
            } catch (\Exception $e) {
                $errors[] = 'Fehler bei der Generierung: ' . $e->getMessage();
                $this->addFlash('error', 'Fehler bei der Generierung der Abrechnungen.');

                // Redirect even on error to satisfy Turbo
                return $this->redirectToRoute('app_abrechnung_index');
            }
        }

        $hgaYear = (int) ($request->query->get('hga_year') ?: date('Y'));

        $wirtschaftsplanConfigs = $this->wirtschaftsplanConfigRepository->findBy([], ['year' => 'DESC']);
        $wirtschaftsplanRows = [];
        foreach ($wirtschaftsplanConfigs as $config) {
            $wirtschaftsplanRows[] = [
                'id' => $config->getId(),
                'year' => $config->getYear(),
                'json' => json_encode($config->getData() ?? [], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE),
                'updatedAt' => $config->getUpdatedAt(),
                'createdAt' => $config->getCreatedAt(),
            ];
        }

        // Load all Kontostände for all WEGs
        $wegs = $this->wegRepository->findAll();
        $kontostaende = [];
        foreach ($wegs as $weg) {
            $kontostaendeForWeg = $this->wegKontostandRepository->findBy(
                ['weg' => $weg],
                ['year' => 'DESC']
            );
            foreach ($kontostaendeForWeg as $kontostand) {
                $kontostaende[] = $kontostand;
            }
        }

        // Create new Kontostand form
        $kontostandForm = $this->createForm(WegKontostandType::class);
        $kontostandForm->handleRequest($request);

        if ($kontostandForm->isSubmitted() && $kontostandForm->isValid()) {
            $kontostand = $kontostandForm->getData();
            $this->entityManager->persist($kontostand);
            $this->entityManager->flush();

            $this->addFlash('success', 'Kontostand erfolgreich gespeichert!');

            return $this->redirectToRoute('app_abrechnung_index', ['tab' => 'kontostaende']);
        }

        $kontostandEditForms = [];
        foreach ($kontostaende as $kontostand) {
            $editForm = $this->createForm(WegKontostandType::class, $kontostand, [
                'action' => $this->generateUrl('app_abrechnung_kontostand_edit', ['id' => $kontostand->getId()]),
                'method' => 'POST',
            ]);
            $kontostandEditForms[$kontostand->getId()] = $editForm->createView();
        }

        return $this->render('abrechnung/index.html.twig', [
            'form' => $form->createView(),
            'generatedFiles' => $generatedFiles,
            'errors' => $errors,
            'hgaYear' => $hgaYear,
            'hgaSectionHeadersJson' => json_encode(
                $systemConfigService->getArray('hga.section_headers', []),
                \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE
            ),
            'hgaStandardTextsJson' => json_encode(
                $systemConfigService->getArray('hga.standard_texts', []),
                \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE
            ),
            'kontostaende' => $kontostaende,
            'kontostandForm' => $kontostandForm,
            'kontostandEditForms' => $kontostandEditForms,
            'wirtschaftsplanRows' => $wirtschaftsplanRows,
            'wirtschaftsplanTemplateJson' => json_encode([
                'bank_balances' => [],
                'planned_expenses' => [
                    'umlagefaehig' => [],
                    'nicht_umlagefaehig' => [],
                ],
                'planned_income' => [],
                'balance_overrides' => [],
            ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE),
        ]);
    }

    #[Route('/hga-config/update', name: 'app_abrechnung_hga_config_update', methods: ['POST'])]
    public function updateHgaConfig(
        Request $request,
        SystemConfigService $systemConfigService,
    ): Response {
        $tab = 'hga-config';

        try {
            $sectionHeaders = $this->decodeJson($request->request->get('hga_section_headers', ''));
            $standardTexts = $this->decodeJson($request->request->get('hga_standard_texts', ''));

            $systemConfigService->set('hga.section_headers', $sectionHeaders, 'hausgeldabrechnung', 'HGA section headers');
            $systemConfigService->set('hga.standard_texts', $standardTexts, 'hausgeldabrechnung', 'HGA standard texts');

            $systemConfigService->clearCache();
            $this->addFlash('success', 'HGA-Konfiguration gespeichert.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Ungültiges JSON: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_abrechnung_index', [
            'tab' => $tab,
        ]);
    }

    #[Route('/wirtschaftsplan-config/create', name: 'app_abrechnung_wirtschaftsplan_config_create', methods: ['POST'])]
    public function createWirtschaftsplanConfig(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('wirtschaftsplan_create', $request->request->get('_token'))) {
            $this->addFlash('error', 'Ungültiger Token.');

            return $this->redirectToRoute('app_abrechnung_index', ['tab' => 'wirtschaftsplan']);
        }

        $year = (int) $request->request->get('plan_year', 0);
        $jsonInput = (string) $request->request->get('plan_json', '');

        if ($year <= 0) {
            $this->addFlash('error', 'Bitte ein gültiges Plan-Jahr angeben.');

            return $this->redirectToRoute('app_abrechnung_index', ['tab' => 'wirtschaftsplan']);
        }

        if ($this->wirtschaftsplanConfigRepository->findOneBy(['year' => $year])) {
            $this->addFlash('error', 'Für dieses Plan-Jahr existiert bereits eine Konfiguration.');

            return $this->redirectToRoute('app_abrechnung_index', ['tab' => 'wirtschaftsplan']);
        }

        try {
            $data = $this->decodeJson($jsonInput);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Ungültiges JSON: ' . $e->getMessage());

            return $this->redirectToRoute('app_abrechnung_index', ['tab' => 'wirtschaftsplan']);
        }

        $config = new \App\Entity\WirtschaftsplanConfig();
        $config->setYear($year);
        $config->setData($data);
        $this->entityManager->persist($config);
        $this->entityManager->flush();

        $this->addFlash('success', 'Wirtschaftsplan gespeichert.');

        return $this->redirectToRoute('app_abrechnung_index', ['tab' => 'wirtschaftsplan']);
    }

    #[Route('/wirtschaftsplan-config/{id}/update', name: 'app_abrechnung_wirtschaftsplan_config_update', methods: ['POST'])]
    public function updateWirtschaftsplanConfig(Request $request, \App\Entity\WirtschaftsplanConfig $config): Response
    {
        if (!$this->isCsrfTokenValid('wirtschaftsplan_update' . $config->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Ungültiger Token.');

            return $this->redirectToRoute('app_abrechnung_index', ['tab' => 'wirtschaftsplan']);
        }

        $year = (int) $request->request->get('plan_year', 0);
        $jsonInput = (string) $request->request->get('plan_json', '');

        if ($year <= 0) {
            $this->addFlash('error', 'Bitte ein gültiges Plan-Jahr angeben.');

            return $this->redirectToRoute('app_abrechnung_index', ['tab' => 'wirtschaftsplan']);
        }

        $existing = $this->wirtschaftsplanConfigRepository->findOneBy(['year' => $year]);
        if ($existing && $existing->getId() !== $config->getId()) {
            $this->addFlash('error', 'Für dieses Plan-Jahr existiert bereits eine andere Konfiguration.');

            return $this->redirectToRoute('app_abrechnung_index', ['tab' => 'wirtschaftsplan']);
        }

        try {
            $data = $this->decodeJson($jsonInput);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Ungültiges JSON: ' . $e->getMessage());

            return $this->redirectToRoute('app_abrechnung_index', ['tab' => 'wirtschaftsplan']);
        }

        $config->setYear($year);
        $config->setData($data);
        $this->entityManager->flush();

        $this->addFlash('success', 'Wirtschaftsplan aktualisiert.');

        return $this->redirectToRoute('app_abrechnung_index', ['tab' => 'wirtschaftsplan']);
    }

    #[Route('/wirtschaftsplan-config/{id}/delete', name: 'app_abrechnung_wirtschaftsplan_config_delete', methods: ['POST'])]
    public function deleteWirtschaftsplanConfig(Request $request, \App\Entity\WirtschaftsplanConfig $config): Response
    {
        if ($this->isCsrfTokenValid('wirtschaftsplan_delete' . $config->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($config);
            $this->entityManager->flush();
            $this->addFlash('success', 'Wirtschaftsplan gelöscht.');
        }

        return $this->redirectToRoute('app_abrechnung_index', ['tab' => 'wirtschaftsplan']);
    }

    #[Route('/vermoegen-config/update', name: 'app_abrechnung_vermoegen_config_update', methods: ['POST'])]
    public function updateVermoegenConfig(
        Request $request,
        SystemConfigService $systemConfigService,
    ): Response {
        $tab = 'vermoegen-config';
        $year = (int) $request->request->get('hga_year', date('Y'));

        try {
            $rows = $request->request->all('vermoegen_rows');
            $bankBalancesInput = $request->request->all('bank_balances');

            $balanceOverrides = [];
            foreach ($rows as $row) {
                $rowYear = mb_trim((string) ($row['year'] ?? ''));
                if ('' === $rowYear) {
                    continue;
                }

                $parsedYear = (int) $rowYear;
                if ($parsedYear <= 0) {
                    continue;
                }

                $hausgeldStart = $this->normalizeAmount($row['hausgeld_start'] ?? null);
                $hausgeldEnd = $this->normalizeAmount($row['hausgeld_end'] ?? null);
                $ruecklagenStart = $this->normalizeAmount($row['ruecklagen_start'] ?? null);
                $ruecklagenEnd = $this->normalizeAmount($row['ruecklagen_end'] ?? null);

                if (null === $hausgeldStart && null === $hausgeldEnd && null === $ruecklagenStart && null === $ruecklagenEnd) {
                    continue;
                }

                $balanceOverrides[(string) $parsedYear] = [
                    'hausgeld' => [
                        'start' => $hausgeldStart,
                        'end' => $hausgeldEnd,
                    ],
                    'ruecklagen' => [
                        'start' => $ruecklagenStart,
                        'end' => $ruecklagenEnd,
                    ],
                ];
            }

            $bankBalances = [
                'balance_date' => mb_trim((string) ($bankBalancesInput['balance_date'] ?? '')),
                'hausgeld_konto' => $this->normalizeAmount($bankBalancesInput['hausgeld_konto'] ?? null),
                'ruecklagen_konto' => $this->normalizeAmount($bankBalancesInput['ruecklagen_konto'] ?? null),
            ];
            $prefix = "wirtschaftsplan.{$year}";

            $systemConfigService->set(
                "{$prefix}.bank_balances",
                $bankBalances,
                'hausgeldabrechnung',
                'Wirtschaftsplan bank balances'
            );
            $systemConfigService->set(
                "{$prefix}.balance_overrides",
                $balanceOverrides,
                'hausgeldabrechnung',
                'Wirtschaftsplan balance overrides'
            );

            $systemConfigService->clearCache();
            $this->addFlash('success', 'Vermoegen-Konfiguration gespeichert.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Vermoegen-Konfiguration ungültig: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_abrechnung_index', [
            'tab' => $tab,
            'hga_year' => $year,
        ]);
    }

    #[Route('/einheiten/{wegId}', name: 'app_abrechnung_einheiten', methods: ['GET'])]
    public function getEinheiten(int $wegId): JsonResponse
    {
        $weg = $this->wegRepository->find($wegId);
        if (!$weg) {
            return new JsonResponse(['error' => 'WEG nicht gefunden'], 404);
        }

        $einheiten = $this->wegEinheitRepository->findBy(['weg' => $weg], ['nummer' => 'ASC']);

        $result = [];
        foreach ($einheiten as $einheit) {
            $result[] = [
                'id' => $einheit->getId(),
                'nummer' => $einheit->getNummer(),
                'bezeichnung' => $einheit->getBezeichnung(),
                'miteigentuemer' => $einheit->getMiteigentuemer(),
            ];
        }

        return new JsonResponse(['einheiten' => $result]);
    }

    #[Route('/{year}/preview/{unitNumber}', name: 'app_abrechnung_preview', methods: ['GET'])]
    public function preview(Request $request, int $year, string $unitNumber): Response
    {
        try {
            // Find the unit by number (search across all WEGs)
            $einheit = $this->wegEinheitRepository->findOneBy([
                'nummer' => $unitNumber,
            ]);

            if (!$einheit) {
                throw new \Exception(\sprintf('Unit %s not found', $unitNumber));
            }

            $weg = $einheit->getWeg();
            if (!$weg) {
                throw new \Exception(\sprintf('WEG for unit %s not found', $unitNumber));
            }

            // Validate inputs
            $errors = $this->hgaService->validateCalculationInputs($einheit, $year);
            if (!empty($errors)) {
                throw new \Exception('Validation failed: ' . implode(', ', $errors));
            }

            $reportData = $this->hgaService->generateReportData($einheit, $year);

            return $this->render('hga/pdf_report.html.twig', [
                'data' => $reportData,
                'generatedAt' => new \DateTime(),
                'previewMode' => true,
            ]);
        } catch (\Exception $e) {
            // Return error page
            return $this->render('abrechnung/preview_error.html.twig', [
                'error' => $e->getMessage(),
                'year' => $year,
                'unitNumber' => $unitNumber,
                'generatedAt' => new \DateTime(),
            ]);
        }
    }

    #[Route('/kontostand/{id}/edit', name: 'app_abrechnung_kontostand_edit', methods: ['GET', 'POST'])]
    public function editKontostand(Request $request, WegKontostand $kontostand): Response
    {
        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_abrechnung_index', [
                'tab' => 'kontostaende',
                'edit_kontostand' => $kontostand->getId(),
            ]);
        }

        $form = $this->createForm(WegKontostandType::class, $kontostand);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existing = $this->wegKontostandRepository->findOneBy([
                'weg' => $kontostand->getWeg(),
                'year' => $kontostand->getYear(),
                'bankkontoTyp' => $kontostand->getBankkontoTyp(),
            ]);

            if ($existing && $existing->getId() !== $kontostand->getId()) {
                $this->addFlash('error', 'Ein Kontostand für WEG, Jahr und Konto-Typ existiert bereits. Bitte bearbeite den vorhandenen Eintrag.');
            } else {
                $this->entityManager->flush();
                $this->addFlash('success', 'Kontostand erfolgreich aktualisiert!');
            }

            return $this->redirectToRoute('app_abrechnung_index', ['tab' => 'kontostaende']);
        }

        return $this->redirectToRoute('app_abrechnung_index', ['tab' => 'kontostaende']);
    }

    #[Route('/kontostand/{id}/delete', name: 'app_abrechnung_kontostand_delete', methods: ['POST'])]
    public function deleteKontostand(Request $request, WegKontostand $kontostand): Response
    {
        if ($this->isCsrfTokenValid('delete' . $kontostand->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($kontostand);
            $this->entityManager->flush();

            $this->addFlash('success', 'Kontostand erfolgreich gelöscht!');
        }

        return $this->redirectToRoute('app_abrechnung_index', ['tab' => 'kontostaende']);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $input): array
    {
        $input = mb_trim($input);
        if ('' === $input) {
            return [];
        }

        $decoded = json_decode($input, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            throw new \InvalidArgumentException('JSON must decode to an object or array.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $balanceOverrides
     *
     * @return array<int, array<string, string|int>>
     */
    private function buildVermoegenRows(array $balanceOverrides, int $hgaYear): array
    {
        $rows = [];

        foreach ($balanceOverrides as $yearKey => $yearData) {
            if (!\is_array($yearData)) {
                continue;
            }

            $year = (int) $yearKey;
            if ($year <= 0) {
                continue;
            }

            if (!isset($yearData['hausgeld']) && !isset($yearData['ruecklagen']) && !isset($yearData['ruecklage'])) {
                continue;
            }

            $rows[] = $this->formatVermoegenRow(
                $year,
                $this->readAccountValue($yearData, 'hausgeld', 'start'),
                $this->readAccountValue($yearData, 'hausgeld', 'end'),
                $this->readAccountValue($yearData, 'ruecklagen', 'start'),
                $this->readAccountValue($yearData, 'ruecklagen', 'end'),
            );
        }

        if ([] === $rows) {
            $rows[] = $this->formatVermoegenRow($hgaYear - 1, null, null, null, null);
            $rows[] = $this->formatVermoegenRow($hgaYear, null, null, null, null);
        }

        usort($rows, function (array $left, array $right): int {
            return $left['year'] <=> $right['year'];
        });

        return array_values($rows);
    }

    /**
     * @param array<string, mixed> $balanceOverrides
     *
     * @return array<string, mixed>
     */
    private function buildVermoegenRechnerischerEndbestandHint(array $balanceOverrides, int $year): array
    {
        $yearData = $balanceOverrides[(string) $year] ?? $balanceOverrides[$year] ?? null;
        if (!\is_array($yearData)) {
            return [
                'hasData' => false,
                'year' => $year,
            ];
        }

        $hausgeldStart = $this->readAccountValue($yearData, 'hausgeld', 'start');
        $ruecklagenStart = $this->readAccountValue($yearData, 'ruecklagen', 'start');

        $paymentSummary = $this->paymentCalculationService->getVermoegenPaymentSummary($year);
        $hausgeldIncome = (float) ($paymentSummary['hausgeld_income'] ?? 0);
        $hausgeldExpense = (float) ($paymentSummary['hausgeld_expense'] ?? 0);
        $ruecklagenIncome = (float) ($paymentSummary['ruecklage_income'] ?? 0);
        $ruecklagenExpense = (float) ($paymentSummary['ruecklage_expense'] ?? 0);

        $rechHausgeld = null;
        if (null !== $hausgeldStart) {
            $rechHausgeld = $hausgeldStart + $hausgeldIncome + $hausgeldExpense;
        }

        $rechRuecklage = null;
        if (null !== $ruecklagenStart) {
            $rechRuecklage = $ruecklagenStart + $ruecklagenIncome + $ruecklagenExpense;
        }

        $rechTotal = null;
        if (null !== $rechHausgeld && null !== $rechRuecklage) {
            $rechTotal = $rechHausgeld + $rechRuecklage;
        }

        return [
            'hasData' => null !== $rechHausgeld || null !== $rechRuecklage,
            'year' => $year,
            'hausgeld' => $rechHausgeld,
            'ruecklagen' => $rechRuecklage,
            'gesamt' => $rechTotal,
        ];
    }

    /**
     * @param array<string, mixed> $yearData
     */
    private function readAccountValue(array $yearData, string $accountKey, string $field): ?float
    {
        $accountData = $yearData[$accountKey] ?? ('ruecklagen' === $accountKey ? ($yearData['ruecklage'] ?? null) : null);
        if (!\is_array($accountData)) {
            return null;
        }

        if (!\array_key_exists($field, $accountData)) {
            return null;
        }

        return $this->normalizeAmount($accountData[$field]);
    }

    private function formatVermoegenRow(
        int $year,
        ?float $hausgeldStart,
        ?float $hausgeldEnd,
        ?float $ruecklagenStart,
        ?float $ruecklagenEnd,
    ): array {
        return [
            'year' => $year,
            'hausgeld_start' => $this->formatAmount($hausgeldStart),
            'hausgeld_end' => $this->formatAmount($hausgeldEnd),
            'ruecklagen_start' => $this->formatAmount($ruecklagenStart),
            'ruecklagen_end' => $this->formatAmount($ruecklagenEnd),
        ];
    }

    private function formatAmount(?float $value): string
    {
        if (null === $value) {
            return '';
        }

        return number_format($value, 2, '.', '');
    }

    /**
     * @param mixed $value
     */
    private function normalizeAmount($value): ?float
    {
        if (null === $value) {
            return null;
        }

        $stringValue = mb_trim((string) $value);
        if ('' === $stringValue) {
            return null;
        }

        $stringValue = str_replace(' ', '', $stringValue);
        if (str_contains($stringValue, ',') && str_contains($stringValue, '.')) {
            $stringValue = str_replace('.', '', $stringValue);
        }
        $stringValue = str_replace(',', '.', $stringValue);

        if ('' === $stringValue || '-' === $stringValue) {
            return null;
        }

        return (float) $stringValue;
    }

    /**
     * @param WegEinheit[] $einheiten
     *
     * @return Dokument[]
     */
    private function generateAbrechnungen(Weg $weg, int $jahr, string $format, array $einheiten): array
    {
        $generatedFiles = [];
        $formats = ['pdf'];

        foreach ($einheiten as $einheit) {
            foreach ($formats as $currentFormat) {
                try {
                    // Validate inputs first
                    $errors = $this->hgaService->validateCalculationInputs($einheit, $jahr);
                    if (!empty($errors)) {
                        throw new \Exception('Validation failed: ' . implode(', ', $errors));
                    }

                    // Generate report content using new HGA service
                    $reportContent = $this->pdfReportGenerator->generateReport($einheit, $jahr, [
                        'format' => $currentFormat,
                    ]);

                    // Calculate HGA data
                    $hgaData = $this->hgaService->generateReportData($einheit, $jahr);

                    // Create temporary file
                    $tempDir = sys_get_temp_dir();
                    $fileName = \sprintf('hausgeldabrechnung_%d_%s_%s.%s',
                        $jahr,
                        $weg->getId(),
                        $einheit->getNummer(),
                        $currentFormat
                    );
                    $filePath = $tempDir . '/' . $fileName;
                    file_put_contents($filePath, $reportContent);

                    // Save to dokument system
                    $dokument = $this->saveToDocumentSystem($filePath, $weg, $einheit, $jahr, $currentFormat, $hgaData);
                    $generatedFiles[] = $dokument;
                } catch (\Exception $e) {
                    // Log error and continue with next file
                    error_log(\sprintf('Error generating %s for unit %s: %s', $currentFormat, $einheit->getNummer(), $e->getMessage()));

                    // Also add flash message for user feedback
                    $this->addFlash('error', \sprintf(
                        'Fehler bei der Generierung von %s für Einheit %s: %s',
                        mb_strtoupper($currentFormat),
                        $einheit->getNummer(),
                        $e->getMessage()
                    ));
                }
            }
        }

        return $generatedFiles;
    }

    private function saveToDocumentSystem(string $filePath, Weg $weg, WegEinheit $einheit, int $jahr, string $format, array $hgaData): Dokument
    {
        $fileName = basename($filePath);
        $relativePath = 'hausgeldabrechnung/' . $fileName;

        // Move file to dokument directory
        $projectDir = $this->getParameter('kernel.project_dir');
        $targetDir = (string) $projectDir . '/data/dokumente/hausgeldabrechnung';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . '/' . $fileName;
        if (file_exists($filePath)) {
            copy($filePath, $targetPath);
        }

        // Check if document already exists
        $existingDokument = $this->entityManager->getRepository(Dokument::class)
            ->findOneBy(['dateiname' => $fileName]);

        if ($existingDokument) {
            // Update existing document
            $existingDokument->setHgaData($hgaData);
            $existingDokument->setUploadDatum(new \DateTime());
            $dokument = $existingDokument;
        } else {
            // Create new dokument record
            $dokument = new Dokument();
            $dokument->setDateiname($fileName)
                ->setDateipfad($relativePath)
                ->setDateityp($format)
                ->setDategroesse(filesize($targetPath) ?: 0)
                ->setKategorie('hausgeldabrechnung')
                ->setBeschreibung(\sprintf(
                    'Hausgeldabrechnung %d für %s %s (%s)',
                    $jahr,
                    $einheit->getNummer(),
                    $einheit->getBezeichnung(),
                    mb_strtoupper($format)
                ))
                ->setWeg($weg)
                ->setAbrechnungsJahr($jahr)
                ->setEinheitNummer($einheit->getNummer())
                ->setFormat($format)
                ->setHgaData($hgaData);

            $this->entityManager->persist($dokument);
        }

        $this->entityManager->flush();

        return $dokument;
    }
}
