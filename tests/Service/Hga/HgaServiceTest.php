<?php

declare(strict_types=1);

namespace App\Tests\Service\Hga;

use App\Entity\Weg;
use App\Entity\WegEinheit;
use App\Service\Hga\Calculation\BalanceCalculationService;
use App\Service\Hga\Calculation\CostCalculationService;
use App\Service\Hga\Calculation\ExternalCostService;
use App\Service\Hga\Calculation\PaymentCalculationService;
use App\Service\Hga\Calculation\TaxCalculationService;
use App\Service\Hga\ConfigurationInterface;
use App\Service\Hga\HgaService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class HgaServiceTest extends TestCase
{
    private HgaService $service;
    private CostCalculationService|MockObject $mockCostCalculationService;
    private PaymentCalculationService|MockObject $mockPaymentCalculationService;
    private ExternalCostService|MockObject $mockExternalCostService;
    private TaxCalculationService|MockObject $mockTaxCalculationService;
    private BalanceCalculationService|MockObject $mockBalanceCalculationService;
    private ConfigurationInterface|MockObject $mockConfigurationService;
    private LoggerInterface|MockObject $mockLogger;

    protected function setUp(): void
    {
        $this->mockCostCalculationService = $this->createMock(CostCalculationService::class);
        $this->mockPaymentCalculationService = $this->createMock(PaymentCalculationService::class);
        $this->mockExternalCostService = $this->createMock(ExternalCostService::class);
        $this->mockTaxCalculationService = $this->createMock(TaxCalculationService::class);
        $this->mockBalanceCalculationService = $this->createMock(BalanceCalculationService::class);
        $this->mockConfigurationService = $this->createMock(ConfigurationInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);

        $this->service = new HgaService(
            $this->mockCostCalculationService,
            $this->mockPaymentCalculationService,
            $this->mockTaxCalculationService,
            $this->mockExternalCostService,
            $this->mockBalanceCalculationService,
            $this->mockConfigurationService,
            $this->mockLogger
        );
    }

    public function testValidateCalculationInputsWithValidInputs(): void
    {
        $weg = $this->createMock(Weg::class);
        $einheit = $this->createMock(WegEinheit::class);
        $einheit->expects($this->once())
            ->method('getMiteigentumsanteile')
            ->willReturn('290/1000');
        $einheit->expects($this->atLeastOnce())
            ->method('getWeg')
            ->willReturn($weg);

        // Mock external cost service validation
        $this->mockExternalCostService->expects($this->once())
            ->method('validateExternalCostData')
            ->with($weg, 2024)
            ->willReturn([]);

        $result = $this->service->validateCalculationInputs($einheit, 2024);

        $this->assertEmpty($result); // No validation errors expected
    }

    public function testValidateCalculationInputsWithInvalidYear(): void
    {
        $weg = $this->createMock(Weg::class);
        $einheit = $this->createMock(WegEinheit::class);
        $einheit->expects($this->once())
            ->method('getMiteigentumsanteile')
            ->willReturn('290/1000');
        $einheit->expects($this->atLeastOnce())
            ->method('getWeg')
            ->willReturn($weg);

        // Mock external cost service validation
        $this->mockExternalCostService->expects($this->once())
            ->method('validateExternalCostData')
            ->with($weg, 1999)
            ->willReturn([]);

        $result = $this->service->validateCalculationInputs($einheit, 1999);

        $this->assertContains('Invalid year: must be between 2000 and ' . (date('Y') + 1), $result);
    }

    public function testValidateCalculationInputsWithInvalidMea(): void
    {
        $weg = $this->createMock(Weg::class);
        $einheit = $this->createMock(WegEinheit::class);
        $einheit->expects($this->once())
            ->method('getMiteigentumsanteile')
            ->willReturn(null); // Invalid MEA - no value
        $einheit->expects($this->atLeastOnce())
            ->method('getWeg')
            ->willReturn($weg);

        // Mock external cost service validation
        $this->mockExternalCostService->expects($this->once())
            ->method('validateExternalCostData')
            ->with($weg, 2024)
            ->willReturn([]);

        $result = $this->service->validateCalculationInputs($einheit, 2024);

        $this->assertContains('Unit missing MEA value', $result);
    }

    public function testGenerateReportDataRequiresValidInputs(): void
    {
        $weg = $this->createMock(Weg::class);
        $einheit = $this->createMock(WegEinheit::class);
        $einheit->expects($this->any())
            ->method('getMiteigentumsanteile')
            ->willReturn(null); // Invalid MEA - no value
        $einheit->expects($this->any())
            ->method('getWeg')
            ->willReturn($weg);

        // Mock external cost service to return no additional errors
        $this->mockExternalCostService->expects($this->any())
            ->method('validateExternalCostData')
            ->willReturn([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid inputs');

        $this->service->generateReportData($einheit, 2024);
    }
}
