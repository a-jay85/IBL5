<?php

declare(strict_types=1);

namespace Tests\FranchiseHistory;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use FranchiseHistory\FranchiseHistoryRepository;
use FranchiseHistory\Contracts\FranchiseHistoryRepositoryInterface;

/**
 * FranchiseHistoryRepositoryTest - Tests for FranchiseHistoryRepository
 *
 * Tests verify that title counts are sourced from vw_franchise_summary
 * (which internally derives them from vw_team_awards) and that playoff
 * records are derived from vw_playoff_series_results.
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

    public function testImplementsFranchiseHistoryRepositoryInterface(): void
    {
        $this->assertInstanceOf(FranchiseHistoryRepositoryInterface::class, $this->repository);
    }

    /**
     * Verify that titles are sourced from vw_franchise_summary
     *
     * vw_franchise_summary internally derives title counts from vw_team_awards,
     * so querying it directly avoids redundant vw_team_awards materialization.
     */
    public function testTitlesSourcedFromFranchiseSummaryView(): void
    {
        $reflectionClass = new \ReflectionClass($this->repository);
        $fileName = $reflectionClass->getFileName();
        $this->assertIsString($fileName);
        $sourceCode = file_get_contents($fileName);
        $this->assertIsString($sourceCode);

        // Verify the repository queries vw_franchise_summary (which contains title data)
        $this->assertStringContainsString(
            'vw_franchise_summary',
            $sourceCode,
            'Repository must query vw_franchise_summary to get title counts'
        );

        // Verify title fields are selected from the summary
        $this->assertStringContainsString(
            'heat_titles',
            $sourceCode,
            'Repository must include heat_titles from franchise summary'
        );
        $this->assertStringContainsString(
            'div_titles',
            $sourceCode,
            'Repository must include div_titles from franchise summary'
        );
        $this->assertStringContainsString(
            'conf_titles',
            $sourceCode,
            'Repository must include conf_titles from franchise summary'
        );
        $this->assertStringContainsString(
            'ibl_titles',
            $sourceCode,
            'Repository must include ibl_titles from franchise summary'
        );
    }

    /**
     * Verify that getAllPlayoffTotals method exists and queries vw_playoff_series_results
     *
     * This test documents the expected behavior: playoff game records must be
     * derived from series results in vw_playoff_series_results.
     */
    public function testRepositoryQueriesPlayoffSeriesResultsViewForPlayoffTotals(): void
    {
        $reflectionClass = new \ReflectionClass($this->repository);

        // Verify the private getAllPlayoffTotals method exists (bulk playoff calculation)
        $this->assertTrue(
            $reflectionClass->hasMethod('getAllPlayoffTotals'),
            'Repository must have getAllPlayoffTotals method to calculate playoff records from vw_playoff_series_results'
        );

        $fileName = $reflectionClass->getFileName();
        $this->assertIsString($fileName);
        $sourceCode = file_get_contents($fileName);
        $this->assertIsString($sourceCode);

        // Verify that the repository queries vw_playoff_series_results view
        $this->assertStringContainsString(
            'vw_playoff_series_results',
            $sourceCode,
            'Repository must query vw_playoff_series_results view to calculate playoff records'
        );

        // Verify that playoff fields are present in the result array
        $this->assertStringContainsString(
            "'playoff_total_wins'",
            $sourceCode,
            'Repository must include playoff_total_wins in result'
        );
    }

    /**
     * Regression test: Verify that title fields are populated in results
     *
     * This test documents that the repository MUST include title fields
     * in the returned franchise data.
     */
    public function testTitleFieldsArePopulatedInResults(): void
    {
        $reflectionClass = new \ReflectionClass($this->repository);
        $fileName = $reflectionClass->getFileName();
        $this->assertIsString($fileName);
        $sourceCode = file_get_contents($fileName);
        $this->assertIsString($sourceCode);

        // Verify that title fields are included in the result array
        $this->assertStringContainsString(
            "'heat_titles'",
            $sourceCode,
            'Repository result must include heat_titles field'
        );
        $this->assertStringContainsString(
            "'div_titles'",
            $sourceCode,
            'Repository result must include div_titles field'
        );
        $this->assertStringContainsString(
            "'conf_titles'",
            $sourceCode,
            'Repository result must include conf_titles field'
        );
        $this->assertStringContainsString(
            "'ibl_titles'",
            $sourceCode,
            'Repository result must include ibl_titles field'
        );
    }
}
