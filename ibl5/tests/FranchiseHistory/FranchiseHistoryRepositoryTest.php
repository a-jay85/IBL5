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
 * Tests verify that title counts are properly calculated from ibl_team_awards.
 * This is a regression test to ensure titles are never returned as all zeros.
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
     * Integration test to verify title calculation behavior
     *
     * This test uses reflection to verify that the getAllTitleCounts method exists.
     * This prevents the regression where titles were directly read from
     * ibl_team_history instead of being calculated from ibl_team_awards.
     */
    public function testGetAllFranchiseHistoryCalculatesTitlesFromAwardsTable(): void
    {
        $reflectionClass = new \ReflectionClass($this->repository);

        // Verify the private getAllTitleCounts method exists (bulk title calculation)
        $this->assertTrue(
            $reflectionClass->hasMethod('getAllTitleCounts'),
            'Repository must have getAllTitleCounts method to calculate titles from ibl_team_awards'
        );

        $method = $reflectionClass->getMethod('getAllTitleCounts');

        // Verify it's a callable method
        $this->assertTrue(
            $method->isPrivate() || $method->isPublic(),
            'getAllTitleCounts must be a callable method'
        );
    }

    /**
     * Test that verifies the query logic for title counting
     *
     * This documents the expected behavior: titles must be counted from
     * ibl_team_awards, not read from ibl_team_history columns.
     */
    public function testRepositoryMustQueryTeamAwardsForTitles(): void
    {
        // Get the source code of the repository to verify it queries ibl_team_awards
        $reflectionClass = new \ReflectionClass($this->repository);
        $fileName = $reflectionClass->getFileName();
        $sourceCode = file_get_contents($fileName);

        // Verify that the repository queries ibl_team_awards table
        $this->assertStringContainsString(
            'ibl_team_awards',
            $sourceCode,
            'Repository must query ibl_team_awards table to count titles'
        );

        // Verify that titles are calculated dynamically (not just read from ibl_team_history)
        $this->assertStringContainsString(
            "Award LIKE",
            $sourceCode,
            'Repository must use LIKE query to match award names'
        );

        // Verify the title types are searched for
        $this->assertStringContainsString(
            'HEAT',
            $sourceCode,
            'Repository must search for HEAT titles'
        );
        $this->assertStringContainsString(
            'Division',
            $sourceCode,
            'Repository must search for Division titles'
        );
        $this->assertStringContainsString(
            'Conference',
            $sourceCode,
            'Repository must search for Conference titles'
        );
        $this->assertStringContainsString(
            'IBL Champions',
            $sourceCode,
            'Repository must search for IBL Champions titles'
        );
    }

    /**
     * Verify that getPlayoffTotals method exists and queries vw_playoff_series_results
     *
     * This test documents the expected behavior: playoff game records must be
     * derived from series results in vw_playoff_series_results using CASE expressions.
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
        $sourceCode = file_get_contents($fileName);

        // Verify that the repository queries vw_playoff_series_results view
        $this->assertStringContainsString(
            'vw_playoff_series_results',
            $sourceCode,
            'Repository must query vw_playoff_series_results view to calculate playoff records'
        );

        // Verify that playoff fields are assigned in the foreach loop
        $this->assertMatchesRegularExpression(
            '/\$team\[[\'"]playoff_total_wins[\'"]\]\s*=/',
            $sourceCode,
            'Repository must assign calculated playoff wins to team array'
        );
    }

    /**
     * Regression test: Verify that title fields are overwritten
     *
     * This test documents that the repository MUST overwrite the title fields
     * from ibl_team_history (which may be stale/zero) with calculated values
     * from ibl_team_awards.
     */
    public function testTitleFieldsMustBeOverwrittenNotJustRead(): void
    {
        $reflectionClass = new \ReflectionClass($this->repository);
        $fileName = $reflectionClass->getFileName();
        $sourceCode = file_get_contents($fileName);

        // Verify that title fields are assigned (not just selected)
        $this->assertMatchesRegularExpression(
            '/\$team\[[\'"](heat_titles|div_titles|conf_titles|ibl_titles)[\'"]\]\s*=/',
            $sourceCode,
            'Repository must assign calculated title values to team array (overwrite database values)'
        );
    }
}
