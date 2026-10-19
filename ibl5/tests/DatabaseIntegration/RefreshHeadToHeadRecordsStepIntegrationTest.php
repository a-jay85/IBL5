<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use Rivalries\RivalryScorer;
use Updater\Steps\RefreshHeadToHeadRecordsStep;

/**
 * Runs the real Updater step against the DB: the Rivalries stats key must land.
 */
#[Group('database')]
final class RefreshHeadToHeadRecordsStepIntegrationTest extends DatabaseTestCase
{
    public function testStepPopulatesRivalriesStatsCache(): void
    {
        $endingYear = (new \Season\Season($this->db))->endingYear;
        $this->insertScoredTeamBoxscoreRow(($endingYear - 1) . '-11-05', 1, 1, 3, 90, 80);

        $result = (new RefreshHeadToHeadRecordsStep($this->db))->execute();
        self::assertTrue($result->success);

        $stats = (new \Cache\DatabaseCache($this->db))->get('rivalries:franchises:' . RivalryScorer::recentWindowStart($endingYear));
        self::assertNotNull($stats);
        $pairs = array_filter(
            $stats,
            static fn (mixed $s): bool => is_array($s) && ($s['a'] ?? null) === '1' && ($s['b'] ?? null) === '3',
        );
        self::assertCount(1, $pairs);
    }
}
