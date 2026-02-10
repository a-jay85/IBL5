<?php

declare(strict_types=1);

namespace Tests\ContractList;

use ContractList\ContractListService;
use ContractList\Contracts\ContractListRepositoryInterface;
use ContractList\Contracts\ContractListServiceInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ContractList\ContractListService
 */
#[AllowMockObjectsWithoutExpectations]
class ContractListServiceTest extends TestCase
{
    /** @var ContractListRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
    private ContractListRepositoryInterface $mockRepository;
    private ContractListService $service;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(ContractListRepositoryInterface::class);
        $this->service = new ContractListService($this->mockRepository);
    }

    public function testImplementsServiceInterface(): void
    {
        $this->assertInstanceOf(ContractListServiceInterface::class, $this->service);
    }

    public function testGetContractsWithCalculationsReturnsExpectedStructure(): void
    {
        $this->mockRepository->method('getActivePlayerContracts')->willReturn([]);

        $result = $this->service->getContractsWithCalculations();

        $this->assertArrayHasKey('contracts', $result);
        $this->assertArrayHasKey('capTotals', $result);
        $this->assertArrayHasKey('avgCaps', $result);
    }

    public function testGetContractsReturnsEmptyWhenNoPlayers(): void
    {
        $this->mockRepository->method('getActivePlayerContracts')->willReturn([]);

        $result = $this->service->getContractsWithCalculations();

        $this->assertSame([], $result['contracts']);
    }

    public function testCapTotalsAreZeroWhenNoPlayers(): void
    {
        $this->mockRepository->method('getActivePlayerContracts')->willReturn([]);

        $result = $this->service->getContractsWithCalculations();

        $this->assertEquals(0, $result['capTotals']['cap1']);
        $this->assertEquals(0, $result['capTotals']['cap6']);
    }

    public function testCalculatesContractYearsForCyZero(): void
    {
        $this->mockRepository->method('getActivePlayerContracts')->willReturn([
            self::createPlayerContract(['cy' => 0, 'cy1' => 500, 'cy2' => 600, 'cy3' => 700]),
        ]);

        $result = $this->service->getContractsWithCalculations();

        $this->assertSame(500, $result['contracts'][0]['con1']);
        $this->assertSame(600, $result['contracts'][0]['con2']);
        $this->assertSame(700, $result['contracts'][0]['con3']);
    }

    public function testCalculatesContractYearsWithCyOffset(): void
    {
        $this->mockRepository->method('getActivePlayerContracts')->willReturn([
            self::createPlayerContract(['cy' => 2, 'cy1' => 100, 'cy2' => 200, 'cy3' => 300, 'cy4' => 400]),
        ]);

        $result = $this->service->getContractsWithCalculations();

        // cy=2 means: con1 maps to cy2, con2 maps to cy3, etc.
        $this->assertSame(200, $result['contracts'][0]['con1']);
        $this->assertSame(300, $result['contracts'][0]['con2']);
        $this->assertSame(400, $result['contracts'][0]['con3']);
    }

    public function testContractYearsBeyondSixAreZero(): void
    {
        $this->mockRepository->method('getActivePlayerContracts')->willReturn([
            self::createPlayerContract(['cy' => 5, 'cy5' => 500, 'cy6' => 600]),
        ]);

        $result = $this->service->getContractsWithCalculations();

        // cy=5 means year1=5, year2=6 (both < 7), year3=7 (>= 7 → 0)
        $this->assertSame(500, $result['contracts'][0]['con1']);
        $this->assertSame(600, $result['contracts'][0]['con2']);
        $this->assertSame(0, $result['contracts'][0]['con3']);
    }

    public function testCapTotalsAccumulateAcrossPlayers(): void
    {
        $this->mockRepository->method('getActivePlayerContracts')->willReturn([
            self::createPlayerContract(['cy' => 0, 'cy1' => 500]),
            self::createPlayerContract(['cy' => 0, 'cy1' => 300]),
        ]);

        $result = $this->service->getContractsWithCalculations();

        // (500 + 300) / 100 = 8
        $this->assertEquals(8, $result['capTotals']['cap1']);
    }

    public function testAvgCapsCalculatedPerTeam(): void
    {
        $this->mockRepository->method('getActivePlayerContracts')->willReturn([
            self::createPlayerContract(['cy' => 0, 'cy1' => 2800]),
        ]);

        $result = $this->service->getContractsWithCalculations();

        // 2800 / 28 teams / 100 = 1
        $this->assertEquals(1, $result['avgCaps']['acap1']);
    }

    public function testContractIncludesPlayerMetadata(): void
    {
        $this->mockRepository->method('getActivePlayerContracts')->willReturn([
            self::createPlayerContract(['name' => 'LeBron James', 'pos' => 'SF', 'bird' => 'Yes']),
        ]);

        $result = $this->service->getContractsWithCalculations();

        $this->assertSame('LeBron James', $result['contracts'][0]['name']);
        $this->assertSame('SF', $result['contracts'][0]['pos']);
        $this->assertSame('Yes', $result['contracts'][0]['bird']);
    }

    /**
     * @return array{pid: int, name: string, pos: string, teamname: string, tid: int, cy: int, cyt: int, cy1: int, cy2: int, cy3: int, cy4: int, cy5: int, cy6: int, bird: string, team_city: string|null, color1: string|null, color2: string|null}
     */
    private static function createPlayerContract(array $overrides = []): array
    {
        /** @var array{pid: int, name: string, pos: string, teamname: string, tid: int, cy: int, cyt: int, cy1: int, cy2: int, cy3: int, cy4: int, cy5: int, cy6: int, bird: string, team_city: string|null, color1: string|null, color2: string|null} */
        return array_merge([
            'pid' => 1,
            'name' => 'Test Player',
            'pos' => 'G',
            'teamname' => 'Hawks',
            'tid' => 1,
            'cy' => 1,
            'cyt' => 3,
            'cy1' => 500,
            'cy2' => 550,
            'cy3' => 600,
            'cy4' => 0,
            'cy5' => 0,
            'cy6' => 0,
            'bird' => 'Yes',
            'team_city' => 'Atlanta',
            'color1' => 'FF0000',
            'color2' => '000000',
        ], $overrides);
    }
}
