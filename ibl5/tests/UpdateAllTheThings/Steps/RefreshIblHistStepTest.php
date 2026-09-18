<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use Updater\Steps\RefreshIblHistStep;

class RefreshIblHistStepTest extends TestCase
{
    public function testGetLabelReturnsExpectedLabel(): void
    {
        $stub = self::createStub(\mysqli::class);
        $this->assertSame('ibl_hist refreshed', (new RefreshIblHistStep($stub))->getLabel());
    }

    public function testExecuteReturnsSuccess(): void
    {
        $mockDb = new MockDatabase();
        $result = (new RefreshIblHistStep($mockDb))->execute();

        $this->assertTrue($result->success);
        $this->assertSame('ibl_hist refreshed', $result->label);
    }

    public function testExecuteWrapsInTransaction(): void
    {
        $mockDb = new MockDatabase();
        (new RefreshIblHistStep($mockDb))->execute();

        $log = $mockDb->getOperationLog();
        $beginIdx = array_search('BEGIN', $log, true);
        $commitIdx = array_search('COMMIT', $log, true);

        $this->assertNotFalse($beginIdx, 'Expected BEGIN in operation log');
        $this->assertNotFalse($commitIdx, 'Expected COMMIT in operation log');
        $this->assertLessThan($commitIdx, $beginIdx, 'BEGIN must precede COMMIT');
    }

    /**
     * Pins the phase-rank CASE in the ORDER BY. Mutation: delete the CASE expression
     * and only `id DESC` remains — this assertion then fails.
     */
    public function testPhaseRankCaseExpressionPresentInRefreshQuery(): void
    {
        $mockDb = new MockDatabase();
        (new RefreshIblHistStep($mockDb))->execute();

        $queries = implode("\n", $mockDb->getExecutedQueries());

        $this->assertStringContainsString(
            "WHEN 'end-of-season'",
            $queries,
            'Phase-rank CASE must be present in the dedup ORDER BY',
        );
        $this->assertStringContainsString(
            'ELSE 99',
            $queries,
            'Phase-rank fallback (ELSE 99) must be present',
        );
    }

    /**
     * Pins stats_gm DESC as the first ORDER BY key. Mutation: move or remove
     * stats_gm DESC and this positional assertion fails.
     */
    public function testStatsGmDescPrecedesPhaseRankInRefreshQuery(): void
    {
        $mockDb = new MockDatabase();
        (new RefreshIblHistStep($mockDb))->execute();

        $queries = implode("\n", $mockDb->getExecutedQueries());

        $statsGmPos  = strpos($queries, 'stats_gm DESC');
        $phaseRankPos = strpos($queries, "WHEN 'end-of-season'");

        $this->assertNotFalse($statsGmPos, 'stats_gm DESC must be in the refresh query');
        $this->assertNotFalse($phaseRankPos, 'Phase-rank CASE must be in the refresh query');
        $this->assertLessThan(
            $phaseRankPos,
            $statsGmPos,
            'stats_gm DESC must appear before the phase-rank CASE in the ORDER BY',
        );
    }
}
