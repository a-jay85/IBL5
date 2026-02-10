<?php

declare(strict_types=1);

namespace Tests\SeasonHighs;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use SeasonHighs\Contracts\SeasonHighsRepositoryInterface;
use SeasonHighs\Contracts\SeasonHighsServiceInterface;
use SeasonHighs\SeasonHighsService;

/**
 * @covers \SeasonHighs\SeasonHighsService
 */
#[AllowMockObjectsWithoutExpectations]
class SeasonHighsServiceTest extends TestCase
{
    /** @var SeasonHighsRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
    private SeasonHighsRepositoryInterface $mockRepository;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(SeasonHighsRepositoryInterface::class);
    }

    public function testImplementsServiceInterface(): void
    {
        $season = $this->createMockSeason(2024, 2025);
        $service = new SeasonHighsService($this->mockRepository, $season);

        $this->assertInstanceOf(SeasonHighsServiceInterface::class, $service);
    }

    public function testGetSeasonHighsDataReturnsExpectedStructure(): void
    {
        $this->mockRepository->method('getSeasonHighs')->willReturn([]);
        $season = $this->createMockSeason(2024, 2025);
        $service = new SeasonHighsService($this->mockRepository, $season);

        $result = $service->getSeasonHighsData('Regular Season');

        $this->assertArrayHasKey('playerHighs', $result);
        $this->assertArrayHasKey('teamHighs', $result);
    }

    public function testGetSeasonHighsDataReturnsNineStatCategories(): void
    {
        $this->mockRepository->method('getSeasonHighs')->willReturn([]);
        $season = $this->createMockSeason(2024, 2025);
        $service = new SeasonHighsService($this->mockRepository, $season);

        $result = $service->getSeasonHighsData('Regular Season');

        $this->assertCount(9, $result['playerHighs']);
        $this->assertCount(9, $result['teamHighs']);
    }

    public function testGetSeasonHighsDataIncludesPointsStat(): void
    {
        $this->mockRepository->method('getSeasonHighs')->willReturn([]);
        $season = $this->createMockSeason(2024, 2025);
        $service = new SeasonHighsService($this->mockRepository, $season);

        $result = $service->getSeasonHighsData('Regular Season');

        $this->assertArrayHasKey('POINTS', $result['playerHighs']);
        $this->assertArrayHasKey('REBOUNDS', $result['playerHighs']);
        $this->assertArrayHasKey('ASSISTS', $result['playerHighs']);
    }

    public function testCallsRepositoryForPlayerAndTeamHighs(): void
    {
        // Each of 9 stats calls getSeasonHighs twice (player + team) = 18 calls
        $this->mockRepository->expects($this->exactly(18))
            ->method('getSeasonHighs')
            ->willReturn([]);
        $season = $this->createMockSeason(2024, 2025);
        $service = new SeasonHighsService($this->mockRepository, $season);

        $service->getSeasonHighsData('Regular Season');
    }

    public function testRegularSeasonUsesCorrectDateRange(): void
    {
        $this->mockRepository->method('getSeasonHighs')
            ->willReturnCallback(function (string $expr, string $name, string $suffix, string $start, string $end): array {
                // Regular season: Nov 2024 to May 2025
                $this->assertStringStartsWith('2024-11', $start);
                $this->assertStringStartsWith('2025-05', $end);
                return [];
            });
        $season = $this->createMockSeason(2024, 2025);
        $service = new SeasonHighsService($this->mockRepository, $season);

        $service->getSeasonHighsData('Regular Season');
    }

    public function testPlayoffsUsesCorrectDateRange(): void
    {
        $this->mockRepository->method('getSeasonHighs')
            ->willReturnCallback(function (string $expr, string $name, string $suffix, string $start, string $end): array {
                // Playoffs: June 2025
                $this->assertStringStartsWith('2025-06', $start);
                $this->assertStringStartsWith('2025-06', $end);
                return [];
            });
        $season = $this->createMockSeason(2024, 2025);
        $service = new SeasonHighsService($this->mockRepository, $season);

        $service->getSeasonHighsData('Playoffs');
    }

    /**
     * @return \Season&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createMockSeason(int $beginningYear, int $endingYear): \Season
    {
        $season = $this->createMock(\Season::class);
        $season->beginningYear = $beginningYear;
        $season->endingYear = $endingYear;

        return $season;
    }
}
