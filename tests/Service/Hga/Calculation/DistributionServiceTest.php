<?php

declare(strict_types=1);

namespace App\Tests\Service\Hga\Calculation;

use App\Entity\Weg;
use App\Entity\WegEinheit;
use App\Repository\WegEinheitRepository;
use App\Service\Hga\Calculation\DistributionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DistributionServiceTest extends TestCase
{
    private DistributionService $service;
    private WegEinheitRepository|MockObject $mockWegEinheitRepository;

    protected function setUp(): void
    {
        $this->mockWegEinheitRepository = $this->createMock(WegEinheitRepository::class);
        $this->service = new DistributionService($this->mockWegEinheitRepository);
    }

    /**
     * @dataProvider distributionKeyProvider
     */
    public function testCalculateOwnerShare(
        float $totalAmount,
        string $distributionKey,
        float $mea,
        float $expectedShare,
    ): void {
        $einheit = $this->createMock(WegEinheit::class);
        $weg = $this->createMock(Weg::class);

        // Mock unit count for equal distribution
        if ('03*' === $distributionKey) {
            $this->mockWegEinheitRepository->expects($this->once())
                ->method('count')
                ->with(['weg' => $weg])
                ->willReturn(4);
        }

        $result = $this->service->calculateOwnerShare($totalAmount, $distributionKey, $mea, $einheit, $weg);

        $this->assertEquals($expectedShare, $result, "Failed for distribution key {$distributionKey}");
    }

    /**
     * @return array<string, array<string|float>>
     */
    public function distributionKeyProvider(): array
    {
        return [
            // [totalAmount, distributionKey, mea, expectedShare]
            'MEA distribution 05*' => [1000.0, '05*', 0.29, 290.0],
            'Equal distribution 03*' => [1000.0, '03*', 0.29, 250.0], // 1000/4 units
            'Hebeanlage distribution 06*' => [1000.0, '06*', 0.29, 0.0], // Currently returns 0.0
            'Fixed distribution 04*' => [1000.0, '04*', 0.29, 1000.0], // Fixed amount
            'External heating 01*' => [1000.0, '01*', 0.29, 0.0], // Currently returns 0.0
            'External water 02*' => [1000.0, '02*', 0.29, 0.0], // Currently returns 0.0
        ];
    }

    public function testValidateInputsWithValidData(): void
    {
        $einheit = $this->createMock(WegEinheit::class);
        $weg = $this->createMock(Weg::class);

        // This should not throw an exception for valid inputs
        $result = $this->service->calculateOwnerShare(1000.0, '05*', 0.29, $einheit, $weg);

        $this->assertEquals(290.0, $result);
    }

    public function testCalculateOwnerShareWithUnsupportedKey(): void
    {
        $einheit = $this->createMock(WegEinheit::class);
        $weg = $this->createMock(Weg::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Distribution key must be in format 0X* where X is 1-6');

        $this->service->calculateOwnerShare(1000.0, '99*', 0.29, $einheit, $weg);
    }
}
