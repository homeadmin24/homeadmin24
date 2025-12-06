<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\OllamaService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-ai',
    description: 'Test AI categorization service availability and functionality'
)]
class TestAiCommand extends Command
{
    public function __construct(
        private readonly OllamaService $ollama,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('AI Service Test');

        // Check if AI is enabled
        if (!$this->ollama->isEnabled()) {
            $io->error('AI is disabled in this environment');
            $io->note('To enable AI:');
            $io->listing([
                'Set AI_ENABLED=true in .env',
                'Or use docker-compose.dev.yml which enables it automatically',
            ]);

            return Command::FAILURE;
        }

        $io->success('✓ AI is enabled');

        // Check if Ollama is available
        $io->section('Testing Ollama Connection');

        if (!$this->ollama->isOllamaAvailable()) {
            $io->error('Ollama service is not available');
            $io->note('To start Ollama:');
            $io->listing([
                'docker compose -f docker-compose.yaml -f docker-compose.dev.yml up -d',
                'Wait ~10 seconds for services to start',
                'docker exec -it hausman-ollama ollama pull llama3.1:8b',
            ]);

            return Command::FAILURE;
        }

        $io->success('✓ Ollama is reachable');

        // Test categorization
        $io->section('Testing Payment Categorization');

        try {
            $result = $this->ollama->suggestKostenkonto(
                bezeichnung: 'Abschlag 10/2024 Vertragskonto 123456',
                partner: 'Stadtwerke München',
                betrag: -850.00,
                historicalData: [],
                learningExamples: [],
                availableKategorien: [
                    ['nummer' => '043100', 'bezeichnung' => 'Gas'],
                    ['nummer' => '043000', 'bezeichnung' => 'Allgemeinstrom'],
                    ['nummer' => '042000', 'bezeichnung' => 'Wasser'],
                ],
            );

            $io->success('✓ AI categorization working!');

            $io->definitionList(
                ['Suggested Kostenkonto' => $result['kostenkonto'] ?? 'N/A'],
                ['Confidence' => sprintf('%.0f%%', ($result['confidence'] ?? 0) * 100)],
                ['Reasoning' => $result['reasoning'] ?? 'N/A']
            );

            // Final summary
            $io->section('Summary');
            $io->success('All AI services are working correctly!');
            $io->info('You can now use AI-powered payment categorization in local development.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('AI categorization failed: ' . $e->getMessage());

            $io->note('Common issues:');
            $io->listing([
                'Model not downloaded: docker exec -it hausman-ollama ollama pull llama3.1:8b',
                'Ollama not running: docker compose -f docker-compose.yaml -f docker-compose.dev.yml up -d',
                'Wrong model name: Check OLLAMA_MODEL in .env',
            ]);

            return Command::FAILURE;
        }
    }
}
