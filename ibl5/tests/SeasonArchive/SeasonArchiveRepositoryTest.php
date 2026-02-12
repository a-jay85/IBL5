<?php

declare(strict_types=1);

namespace Tests\SeasonArchive;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use SeasonArchive\SeasonArchiveRepository;
use SeasonArchive\Contracts\SeasonArchiveRepositoryInterface;

/**
 * SeasonArchiveRepositoryTest - Tests for SeasonArchiveRepository
 *
 * Verifies that the repository implements the interface contract and
 * queries the correct database tables.
 *
 * @covers \SeasonArchive\SeasonArchiveRepository
 */
#[AllowMockObjectsWithoutExpectations]
class SeasonArchiveRepositoryTest extends TestCase
{
    private SeasonArchiveRepository $repository;
    /** @var \mysqli&\PHPUnit\Framework\MockObject\MockObject */
    private \mysqli $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(\mysqli::class);
        $this->repository = new SeasonArchiveRepository($this->mockDb);
    }

    public function testImplementsSeasonArchiveRepositoryInterface(): void
    {
        $this->assertInstanceOf(SeasonArchiveRepositoryInterface::class, $this->repository);
    }

    public function testRepositoryQueriesAwardsTable(): void
    {
        $reflectionClass = new \ReflectionClass($this->repository);
        $fileName = $reflectionClass->getFileName();
        $this->assertIsString($fileName);
        $sourceCode = file_get_contents($fileName);
        $this->assertIsString($sourceCode);

        $this->assertStringContainsString(
            'ibl_awards',
            $sourceCode,
            'Repository must query ibl_awards table'
        );
    }

    public function testRepositoryQueriesPlayoffSeriesResultsView(): void
    {
        $reflectionClass = new \ReflectionClass($this->repository);
        $fileName = $reflectionClass->getFileName();
        $this->assertIsString($fileName);
        $sourceCode = file_get_contents($fileName);
        $this->assertIsString($sourceCode);

        $this->assertStringContainsString(
            'vw_playoff_series_results',
            $sourceCode,
            'Repository must query vw_playoff_series_results view'
        );
    }

    public function testRepositoryQueriesTeamAwardsTable(): void
    {
        $reflectionClass = new \ReflectionClass($this->repository);
        $fileName = $reflectionClass->getFileName();
        $this->assertIsString($fileName);
        $sourceCode = file_get_contents($fileName);
        $this->assertIsString($sourceCode);

        $this->assertStringContainsString(
            'ibl_team_awards',
            $sourceCode,
            'Repository must query ibl_team_awards table'
        );
    }

    public function testRepositoryQueriesGmAwardsTable(): void
    {
        $reflectionClass = new \ReflectionClass($this->repository);
        $fileName = $reflectionClass->getFileName();
        $this->assertIsString($fileName);
        $sourceCode = file_get_contents($fileName);
        $this->assertIsString($sourceCode);

        $this->assertStringContainsString(
            'ibl_gm_awards',
            $sourceCode,
            'Repository must query ibl_gm_awards table'
        );
    }

    public function testRepositoryQueriesGmTenuresTable(): void
    {
        $reflectionClass = new \ReflectionClass($this->repository);
        $fileName = $reflectionClass->getFileName();
        $this->assertIsString($fileName);
        $sourceCode = file_get_contents($fileName);
        $this->assertIsString($sourceCode);

        $this->assertStringContainsString(
            'ibl_gm_tenures',
            $sourceCode,
            'Repository must query ibl_gm_tenures table'
        );
    }

    public function testRepositoryQueriesHeatWinLossTable(): void
    {
        $reflectionClass = new \ReflectionClass($this->repository);
        $fileName = $reflectionClass->getFileName();
        $this->assertIsString($fileName);
        $sourceCode = file_get_contents($fileName);
        $this->assertIsString($sourceCode);

        $this->assertStringContainsString(
            'ibl_heat_win_loss',
            $sourceCode,
            'Repository must query ibl_heat_win_loss table'
        );
    }

    public function testRepositoryQueriesTeamInfoForColors(): void
    {
        $reflectionClass = new \ReflectionClass($this->repository);
        $fileName = $reflectionClass->getFileName();
        $this->assertIsString($fileName);
        $sourceCode = file_get_contents($fileName);
        $this->assertIsString($sourceCode);

        $this->assertStringContainsString(
            'ibl_team_info',
            $sourceCode,
            'Repository must query ibl_team_info for team colors'
        );
    }

    public function testPlayoffResultsUseViewNotLegacyTable(): void
    {
        $reflectionClass = new \ReflectionClass($this->repository);
        $fileName = $reflectionClass->getFileName();
        $this->assertIsString($fileName);
        $sourceCode = file_get_contents($fileName);
        $this->assertIsString($sourceCode);

        // Verify the query uses the view (which has no anomalous year=1 data)
        $this->assertStringNotContainsString(
            'ibl_playoff_results',
            $sourceCode,
            'Repository must not query legacy ibl_playoff_results table directly'
        );
    }

    public function testGetPlayerIdsByNamesQueriesPlayerTable(): void
    {
        $reflectionClass = new \ReflectionClass($this->repository);
        $fileName = $reflectionClass->getFileName();
        $this->assertIsString($fileName);
        $sourceCode = file_get_contents($fileName);
        $this->assertIsString($sourceCode);

        $this->assertStringContainsString(
            'ibl_plr',
            $sourceCode,
            'getPlayerIdsByNames must query ibl_plr table'
        );
    }

    public function testGetPlayerIdsByNamesReturnsEmptyForEmptyInput(): void
    {
        $result = $this->repository->getPlayerIdsByNames([]);
        $this->assertSame([], $result);
    }
}
