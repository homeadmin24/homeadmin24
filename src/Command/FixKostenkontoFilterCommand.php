<?php

namespace App\Command;

use App\Repository\ZahlungskategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-kostenkonto-filter',
    description: 'Fix kostenkonto_filter: Replace 049000 with 051000 (Nebenkosten Geldverkehr)',
)]
class FixKostenkontoFilterCommand extends Command
{
    public function __construct(
        private readonly ZahlungskategorieRepository $zahlungskategorieRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Fix Kostenkonto-Filter: 049000 → 051000');

        $allKategorien = $this->zahlungskategorieRepository->findAll();
        $fixed = 0;
        $changes = [];

        foreach ($allKategorien as $kategorie) {
            $fieldConfig = $kategorie->getFieldConfig() ?? [];
            $kostenkontoFilter = $fieldConfig['kostenkonto_filter'] ?? [];

            // Check if filter contains 049000
            if (\in_array('049000', $kostenkontoFilter, true)) {
                // Replace 049000 with 051000
                $newFilter = [];
                foreach ($kostenkontoFilter as $nummer) {
                    if ('049000' === $nummer) {
                        $newFilter[] = '051000';
                    } else {
                        $newFilter[] = $nummer;
                    }
                }

                $fieldConfig['kostenkonto_filter'] = $newFilter;
                $kategorie->setFieldConfig($fieldConfig);
                ++$fixed;

                $changes[] = [
                    'name' => $kategorie->getName(),
                    'old' => implode(', ', $kostenkontoFilter),
                    'new' => implode(', ', $newFilter),
                ];
            }
        }

        if ($fixed > 0) {
            $io->section('Änderungen');
            $io->table(['Kategorie', 'Alt', 'Neu'], $changes);

            $this->entityManager->flush();
            $io->success(\sprintf('✅ %d Filter korrigiert: 049000 → 051000 (Nebenkosten Geldverkehr)', $fixed));
        } else {
            $io->success('✅ Keine Korrekturen nötig. Alle Filter sind korrekt.');
        }

        $io->section('Info');
        $io->text([
            'Problem: Kostenkonto 049000 existiert nicht in der Datenbank',
            'Lösung: Verwende 051000 (Nebenkosten Geldverkehr) stattdessen',
            '',
            'Kategorien die dieses Konto nutzen sollten:',
            '• Umbuchung',
            '• Sonderumlage',
            '• Bankgebühren',
            '• Zinserträge',
        ]);

        return Command::SUCCESS;
    }
}
