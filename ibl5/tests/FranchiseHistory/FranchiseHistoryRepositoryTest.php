<?php

declare(strict_types=1);

namespace Tests\FranchiseHistory;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use FranchiseHistory\FranchiseHistoryRepository;

/**
 * FranchiseHistoryRepositoryTest - Tests for FranchiseHistoryRepository
 *
 * The repository is now a thin raw-fetch layer: all cross-source merging and
 * derived-field computation lives in FranchiseHistoryService (tested DB-free in
 * FranchiseHistoryServiceTest). These tests assert only the repository's
 * legitimate concern — that each raw-fetch method queries the expected
 * view/table. Behavioral assembly assertions belong to the Service test;
 * the live query chain is covered by the DB-integration suite.
 *
 * @covers \FranchiseHistory\FranchiseHistoryRepository
 */
#[AllowMockObjectsWithoutExpectations]
class FranchiseHistoryRepositoryTest extends TestCase
{
    private FranchiseHistoryRepository $repository;
    /** @var \mysqli&\PHPUnit\Framework\MockObject\MockObject */
    private \mysqli $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(\mysqli::class);
        $this->repository = new FranchiseHistoryRepository($this->mockDb);
    }

    public function testSummaryFetchQueriesFranchiseSummaryView(): void
    {
        $sourceCode = $this->repositorySource();

        // Summary rows come from vw_franchise_summary (which internally derives
        // title counts from vw_team_awards), avoiding redundant materialization.
        $this->assertStringContainsString(
            'vw_franchise_summary',
            $sourceCode,
            'Repository must query vw_franchise_summary for the all-time summary rows'
        );

        // Title columns are DB-supplied on the raw summary row.
        foreach (['heat_titles', 'div_titles', 'conf_titles', 'ibl_titles'] as $titleColumn) {
            $this->assertStringContainsString(
                $titleColumn,
                $sourceCode,
                "Repository summary query must select $titleColumn from the franchise summary"
            );
        }
    }

    public function testFiveSeasonWindowFetchQueriesTeamWinLoss(): void
    {
        $this->assertStringContainsString(
            'ibl_team_win_loss',
            $this->repositorySource(),
            'Repository must query ibl_team_win_loss for the rolling 5-season window'
        );
    }

    public function testPlayoffFetchQueriesPlayoffSeriesResultsView(): void
    {
        $this->assertStringContainsString(
            'vw_playoff_series_results',
            $this->repositorySource(),
            'Repository must query vw_playoff_series_results for raw playoff totals'
        );
    }

    public function testHeatFetchQueriesHeatWinLossTable(): void
    {
        $this->assertStringContainsString(
            'ibl_heat_win_loss',
            $this->repositorySource(),
            'Repository must query ibl_heat_win_loss for raw HEAT totals'
        );
    }

    public function testExposesRawFetchMethods(): void
    {
        // The repository sheds assembly: it exposes only raw-row fetches.
        foreach ([
            'getFranchiseSummaryRows',
            'getFiveSeasonWindowRows',
            'getRawPlayoffTotals',
            'getRawHeatTotals',
        ] as $method) {
            $this->assertTrue(
                method_exists($this->repository, $method),
                "Repository must expose raw-fetch method $method"
            );
        }
    }

    private function repositorySource(): string
    {
        $reflectionClass = new \ReflectionClass($this->repository);
        $fileName = $reflectionClass->getFileName();
        $this->assertIsString($fileName);
        $sourceCode = file_get_contents($fileName);
        $this->assertIsString($sourceCode);

        return $sourceCode;
    }
}
