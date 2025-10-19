<?php

namespace App\Command;

use App\Entity\Hausgeldabrechnung;
use App\Entity\Weg;
use App\Repository\WegEinheitRepository;
use App\Repository\WegRepository;
use App\Service\Hga\HgaServiceInterface;
use App\Service\Hga\Report\PdfReportGenerator;
use App\Service\Hga\Report\TxtReportGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:hga-generate',
    description: 'Generates Hausgeldabrechnung reports using the new HGA service architecture',
)]
class HgaGenerateCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WegRepository $wegRepository,
        private WegEinheitRepository $wegEinheitRepository,
        private HgaServiceInterface $hgaService,
        private TxtReportGenerator $txtReportGenerator,
        private PdfReportGenerator $pdfReportGenerator,
        private string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('weg-id', InputArgument::REQUIRED, 'ID of the WEG')
            ->addArgument('year', InputArgument::REQUIRED, 'Year for the Hausgeldabrechnung')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (txt, pdf)', 'txt')
            ->addOption('unit', 'u', InputOption::VALUE_OPTIONAL, 'Specific unit number to generate (optional)')
            ->addOption('output-dir', 'o', InputOption::VALUE_OPTIONAL, 'Output directory', null)
            ->addOption('validate-only', null, InputOption::VALUE_NONE, 'Only validate inputs without generating reports')
            ->addOption('verbose-errors', null, InputOption::VALUE_NONE, 'Show detailed error information');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $wegId = (int) $input->getArgument('weg-id');
        $year = (int) $input->getArgument('year');
        $format = $input->getOption('format');
        $unitFilter = $input->getOption('unit');
        $outputDir = $input->getOption('output-dir');
        $validateOnly = $input->getOption('validate-only');
        $verboseErrors = $input->getOption('verbose-errors');

        $io->title(\sprintf('HGA Generator - %s %d for WEG %d',
            $validateOnly ? 'Validating' : 'Generating',
            $year,
            $wegId
        ));

        // Validate inputs
        if (!$this->validateInputs($io, $wegId, $year, $format)) {
            return Command::FAILURE;
        }

        // Get WEG
        $weg = $this->wegRepository->find($wegId);
        if (!$weg) {
            $io->error(\sprintf('WEG with ID %d not found.', $wegId));

            return Command::FAILURE;
        }

        $io->section(\sprintf('Processing WEG: %s', $weg->getBezeichnung()));

        // Get units
        $wegEinheiten = $this->getUnitsToProcess($weg, $unitFilter);
        if (empty($wegEinheiten)) {
            $io->error('No WEG units found or unit filter matched no results.');

            return Command::FAILURE;
        }

        $io->note(\sprintf('Processing %d unit(s)', \count($wegEinheiten)));

        // Validate all units first
        $validationResults = $this->validateAllUnits($io, $wegEinheiten, $year, $verboseErrors);

        if (!$validationResults['all_valid']) {
            $io->error(\sprintf('Validation failed for %d unit(s)', $validationResults['invalid_count']));
            if (!$verboseErrors) {
                $io->note('Use --verbose-errors to see detailed validation messages');
            }

            return Command::FAILURE;
        }

        $io->success('All units passed validation');

        if ($validateOnly) {
            $io->info('Validation complete. Use without --validate-only to generate reports.');

            return Command::SUCCESS;
        }

        // Set up output directory
        $outputDirectory = $this->setupOutputDirectory($outputDir);
        $io->note(\sprintf('Output directory: %s', $outputDirectory));

        // Generate reports
        $successCount = $this->generateReports($io, $wegEinheiten, $year, $format, $outputDirectory, $verboseErrors);

        // Save metadata
        $this->saveGenerationMetadata($weg, $year, $successCount, \count($wegEinheiten));

        if ($successCount > 0) {
            $io->success(\sprintf('Successfully generated %d/%d reports', $successCount, \count($wegEinheiten)));

            return Command::SUCCESS;
        }

        $io->error('Failed to generate any reports.');

        return Command::FAILURE;
    }

    private function validateInputs(SymfonyStyle $io, int $wegId, int $year, string $format): bool
    {
        $errors = [];

        if ($wegId <= 0) {
            $errors[] = 'WEG ID must be a positive integer';
        }

        $currentYear = (int) date('Y');
        if ($year < 2000 || $year > $currentYear + 1) {
            $errors[] = \sprintf('Year must be between 2000 and %d', $currentYear + 1);
        }

        if (!\in_array($format, ['txt', 'pdf'], true)) {
            $errors[] = 'Format must be either "txt" or "pdf"';
        }

        if (!empty($errors)) {
            $io->error('Input validation failed:');
            foreach ($errors as $error) {
                $io->text('  • ' . $error);
            }

            return false;
        }

        return true;
    }

    /**
     * @return array<\App\Entity\WegEinheit>
     */
    private function getUnitsToProcess(Weg $weg, ?string $unitFilter): array
    {
        $criteria = ['weg' => $weg];
        if ($unitFilter) {
            $criteria['nummer'] = $unitFilter;
        }

        return $this->wegEinheitRepository->findBy($criteria, ['nummer' => 'ASC']);
    }

    /**
     * @param array<\App\Entity\WegEinheit> $wegEinheiten
     *
     * @return array<string, mixed>
     */
    private function validateAllUnits(SymfonyStyle $io, array $wegEinheiten, int $year, bool $verbose): array
    {
        $validCount = 0;
        $invalidCount = 0;
        $allErrors = [];

        foreach ($wegEinheiten as $einheit) {
            $errors = $this->hgaService->validateCalculationInputs($einheit, $year);

            if (empty($errors)) {
                ++$validCount;
                if ($verbose) {
                    $io->text(\sprintf('✓ Unit %s: Valid', $einheit->getNummer()));
                }
            } else {
                ++$invalidCount;
                $allErrors[$einheit->getNummer()] = $errors;

                if ($verbose) {
                    $io->text(\sprintf('✗ Unit %s: %s', $einheit->getNummer(), implode(', ', $errors)));
                } else {
                    $io->text(\sprintf('✗ Unit %s: Failed validation', $einheit->getNummer()));
                }
            }
        }

        return [
            'all_valid' => 0 === $invalidCount,
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount,
            'errors' => $allErrors,
        ];
    }

    /**
     * @param array<\App\Entity\WegEinheit> $wegEinheiten
     */
    private function generateReports(SymfonyStyle $io, array $wegEinheiten, int $year, string $format, string $outputDir, bool $verboseErrors): int
    {
        $successCount = 0;

        foreach ($wegEinheiten as $einheit) {
            $unitId = $einheit->getNummer();
            $owner = $einheit->getMiteigentuemer();

            $io->text(\sprintf('Generating %s for unit %s - %s', mb_strtoupper($format), $unitId, $owner));

            try {
                // Select appropriate generator based on format
                $generator = match ($format) {
                    'txt' => $this->txtReportGenerator,
                    'pdf' => $this->pdfReportGenerator,
                    default => throw new \InvalidArgumentException("Unsupported format: $format"),
                };

                // Generate report content
                $content = $generator->generateReport($einheit, $year);

                // Save to file
                $filename = \sprintf('hausgeldabrechnung_%d_%s_%s.%s',
                    $year,
                    $einheit->getWeg()->getId(),
                    $unitId,
                    $format
                );

                $filePath = $outputDir . '/' . $filename;
                file_put_contents($filePath, $content);

                $io->text(\sprintf('  ✓ Saved: %s', $filename));
                ++$successCount;
            } catch (\Exception $e) {
                $io->text(\sprintf('  ✗ Failed: %s', $e->getMessage()));

                if ($verboseErrors) {
                    $io->text(\sprintf('     Error details: %s', $e->getTraceAsString()));
                }
            }
        }

        return $successCount;
    }

    private function setupOutputDirectory(?string $customDir): string
    {
        if ($customDir) {
            $outputDir = $customDir;
        } else {
            $outputDir = $this->projectDir . '/var/hausgeldabrechnung';
        }

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        return $outputDir;
    }

    private function saveGenerationMetadata(Weg $weg, int $year, int $successCount, int $totalCount): void
    {
        try {
            $abrechnung = new Hausgeldabrechnung();
            $abrechnung->setWeg($weg);
            $abrechnung->setJahr($year);
            $abrechnung->setErstellungsdatum(new \DateTime());
            $abrechnung->setGesamtkosten((string) $successCount);
            $abrechnung->setPdfPfad('Generated via HGA command');

            $this->entityManager->persist($abrechnung);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            // Don't fail the command if metadata save fails
            error_log('Failed to save generation metadata: ' . $e->getMessage());
        }
    }
}
