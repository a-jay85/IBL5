<?php

declare(strict_types=1);

namespace Tests\League;

use League\League;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Characterization test locking the retired-player filter on the four award /
 * All-Star candidate queries in {@see League}.
 *
 * Backlog 13.8 standardized these from `p.retired != 1` to `p.retired = 0` to
 * match the column's `DEFAULT 0` and the other repositories. The swap is
 * behavior-preserving only because `ibl_plr.retired` holds exactly {0, 1} (no
 * sentinels, no NULLs — verified against the live dev DB: 659 active / 903
 * retired, 0 other). Under that domain `retired != 1` and `retired = 0` select
 * the identical rows.
 *
 * These assertions lock the emitted SQL so a regression back to `!= 1` (or any
 * non-equivalent predicate) is caught.
 *
 * @covers \League\League
 */
class RetiredFilterCharacterizationTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    public function testAllStarCandidatesQueryFiltersRetiredWithEqualsZero(): void
    {
        (new League($this->mockDb))->getAllStarCandidatesResult('EC');
        $this->assertFilterUsesEqualsZero();
    }

    public function testMvpCandidatesQueryFiltersRetiredWithEqualsZero(): void
    {
        (new League($this->mockDb))->getMVPCandidatesResult();
        $this->assertFilterUsesEqualsZero();
    }

    public function testSixthPersonCandidatesQueryFiltersRetiredWithEqualsZero(): void
    {
        (new League($this->mockDb))->getSixthPersonOfTheYearCandidatesResult();
        $this->assertFilterUsesEqualsZero();
    }

    public function testRookieOfTheYearCandidatesQueryFiltersRetiredWithEqualsZero(): void
    {
        (new League($this->mockDb))->getRookieOfTheYearCandidatesResult();
        $this->assertFilterUsesEqualsZero();
    }

    private function assertFilterUsesEqualsZero(): void
    {
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries, 'Candidate method should emit exactly one query');
        $sql = $queries[0];

        $this->assertStringContainsString('p.retired = 0', $sql);
        $this->assertStringNotContainsString('p.retired != 1', $sql);
        $this->assertStringNotContainsString('retired != 1', $sql);
    }
}
