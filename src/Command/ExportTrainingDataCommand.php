<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\AiQueryResponseRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Export AI training data for fine-tuning Ollama
 *
 * Usage:
 *   php bin/console app:ai:export-training-data > training.jsonl
 *   php bin/console app:ai:export-training-data --min-rating=good --limit=100 > training.jsonl
 */
#[AsCommand(
    name: 'app:ai:export-training-data',
    description: 'Export good Claude answers as training data for Ollama fine-tuning'
)]
class ExportTrainingDataCommand extends Command
{
    public function __construct(
        private readonly AiQueryResponseRepository $repository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'min-rating',
                null,
                InputOption::VALUE_OPTIONAL,
                'Minimum rating (good/bad/null)',
                'good'
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Maximum number of examples',
                1000
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output format (jsonl/csv)',
                'jsonl'
            )
            ->addOption(
                'mark-used',
                null,
                InputOption::VALUE_NONE,
                'Mark exported examples as used_for_training'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $minRating = $input->getOption('min-rating');
        $limit = (int) $input->getOption('limit');
        $format = $input->getOption('format');
        $markUsed = $input->getOption('mark-used');

        // Fetch examples
        $criteria = ['provider' => 'claude'];
        if ('null' !== $minRating) {
            $criteria['userRating'] = $minRating;
        }

        $examples = $this->repository->findBy(
            $criteria,
            ['createdAt' => 'ASC'],
            $limit
        );

        if (empty($examples)) {
            $io->warning('No training examples found matching criteria.');

            return Command::SUCCESS;
        }

        $io->comment(sprintf('Found %d examples. Exporting...', count($examples)), OutputInterface::VERBOSITY_VERBOSE);

        // Export based on format
        match ($format) {
            'jsonl' => $this->exportJsonl($examples, $output),
            'csv' => $this->exportCsv($examples, $output),
            default => $io->error('Invalid format. Use "jsonl" or "csv".')
        };

        // Optionally mark as used
        if ($markUsed) {
            $ids = array_map(fn ($ex) => $ex->getId(), $examples);
            $this->repository->markAsUsedForTraining($ids);
            $io->success(sprintf('Marked %d examples as used_for_training', count($ids)));
        }

        $io->comment(sprintf(
            "\n✅ Exported %d training examples\n" .
            "📊 Stats: %d good ratings from Claude\n" .
            "🔥 Next: Use for Ollama fine-tuning or few-shot learning",
            count($examples),
            count(array_filter($examples, fn ($ex) => 'good' === $ex->getUserRating()))
        ), OutputInterface::VERBOSITY_VERBOSE);

        return Command::SUCCESS;
    }

    /**
     * Export as JSONL (one JSON object per line)
     * Format for Ollama fine-tuning
     */
    private function exportJsonl(array $examples, OutputInterface $output): void
    {
        foreach ($examples as $example) {
            $context = $example->getContext();

            // Format context more compactly for training
            $contextStr = $this->formatContextForTraining($context);

            $training = [
                'prompt' => sprintf(
                    "Du bist ein Finanzassistent für WEG-Verwaltung.\n\n" .
                    "Frage: %s\n\n" .
                    "Verfügbare Daten:\n%s\n\n" .
                    "Antworte präzise auf Deutsch mit deutscher Zahlenformatierung:",
                    $example->getQuery(),
                    $contextStr
                ),
                'response' => $example->getResponse(),
                'metadata' => [
                    'id' => $example->getId(),
                    'created_at' => $example->getCreatedAt()->format('Y-m-d H:i:s'),
                    'rating' => $example->getUserRating(),
                    'response_time' => $example->getResponseTime(),
                ],
            ];

            $output->writeln(json_encode($training, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Export as CSV for analysis
     */
    private function exportCsv(array $examples, OutputInterface $output): void
    {
        // Header
        $output->writeln('id,query,response,rating,response_time,created_at');

        foreach ($examples as $example) {
            $output->writeln(sprintf(
                '%d,"%s","%s","%s",%f,"%s"',
                $example->getId(),
                str_replace('"', '""', $example->getQuery()),
                str_replace('"', '""', substr($example->getResponse(), 0, 200)), // Truncate for CSV
                $example->getUserRating() ?? 'null',
                $example->getResponseTime(),
                $example->getCreatedAt()->format('Y-m-d H:i:s')
            ));
        }
    }

    /**
     * Format context data for training (more compact)
     */
    private function formatContextForTraining(array $context): string
    {
        $formatted = [];

        // Include only essential context
        if (isset($context['type'])) {
            $formatted[] = "Abfragetyp: {$context['type']}";
        }

        if (isset($context['year'])) {
            $formatted[] = "Jahr: {$context['year']}";
        }

        if (isset($context['payments']['total'])) {
            $formatted[] = sprintf('Gesamtbetrag: %.2f €', $context['payments']['total']);
        }

        if (isset($context['payments']['by_category'])) {
            $formatted[] = 'Kategorien:';
            foreach ($context['payments']['by_category'] as $nummer => $cat) {
                $formatted[] = sprintf('  - %s: %.2f € (%d Zahlungen)', $nummer, $cat['total'], $cat['count']);
            }
        }

        if (isset($context['payments']['count'])) {
            $formatted[] = sprintf('Anzahl Zahlungen: %d', $context['payments']['count']);
        }

        return implode("\n", $formatted);
    }
}
