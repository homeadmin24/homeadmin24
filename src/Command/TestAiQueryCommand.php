<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AiQueryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-ai-query',
    description: 'Test natural language financial queries'
)]
class TestAiQueryCommand extends Command
{
    public function __construct(
        private readonly AiQueryService $aiQueryService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'query',
            InputArgument::OPTIONAL,
            'The question to ask (in German)',
            'Was haben wir 2024 ausgegeben?'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $query = $input->getArgument('query');

        $io->title('AI Financial Query Test');
        $io->section('Question');
        $io->text($query);

        $io->section('Processing...');

        try {
            $result = $this->aiQueryService->answerQuery($query);

            if ($result['success']) {
                $io->section('Answer');
                $io->text($result['answer']);

                $io->newLine();
                $io->info(sprintf('Context size: %d data points', $result['context_size']));

                return Command::SUCCESS;
            } else {
                $io->error('Query failed: ' . $result['error']);
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());

            $io->section('Troubleshooting');
            $io->listing([
                'Ensure Ollama is running: docker ps | grep ollama',
                'Check if model is loaded: docker exec hausman-ollama ollama list',
                'Test basic AI: php bin/console app:test-ai',
                'Check logs: docker compose logs ollama',
            ]);

            return Command::FAILURE;
        }
    }
}
