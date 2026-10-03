<?php

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

require __DIR__ . '/../vendor/autoload.php';

$kernel = new App\Kernel('dev', true);
$kernel->boot();

// Use the command to generate report data via the service
// We need to access HgaService which is private, so use a custom approach
$container = $kernel->getContainer();

// Get EntityManager directly
$em = $container->get('doctrine.orm.entity_manager');
$wegRepo = $em->getRepository(App\Entity\Weg::class);
$weg = $wegRepo->find(3);

if (!$weg) {
    echo "WEG 3 not found\n";
    exit(1);
}

// Get HgaService via the controller trick - make a synthetic request
$hgaService = $container->get('App\Service\Hga\HgaService');

foreach ($weg->getEinheiten() as $unit) {
    $data = $hgaService->generateReportData($unit, 2025);
    $totals = $data['calculated_totals'];

    echo sprintf(
        "%s | %-30s | WEG: %10s | Anteil: %10s | /Monat: %8s | +5%%: %8s\n",
        $unit->getNummer(),
        $unit->getMiteigentuemer(),
        number_format($totals['weg']['gesamtkosten'], 2, ',', '.') . '€',
        number_format($totals['unit']['gesamtkosten'], 2, ',', '.') . '€',
        number_format($totals['unit']['gesamtkosten'] / 12, 2, ',', '.') . '€',
        number_format($totals['unit']['gesamtkosten'] * 1.05 / 12, 2, ',', '.') . '€'
    );
}
