<?php

declare(strict_types=1);

namespace Tests\Team;

use Database\SqlPlaceholders;
use PHPUnit\Framework\TestCase;
use Team\SplitStatsRepository;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Pins SplitStatsRepository::getPeriodStats(): the prepared SQL shape, bind order, and row passthrough.
 */
final class SplitStatsRepositoryGetPeriodStatsTest extends TestCase
{
    private function mainPreparedQuery(MockDatabase $db): string
    {
        $matches = array_values(array_filter(
            $db->getPreparedQueries(),
            static fn (string $q): bool => str_contains($q, 'gameMINavg')
        ));
        $this->assertCount(1, $matches);

        return $matches[0];
    }

    private function normalizedQuery(MockDatabase $db): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $this->mainPreparedQuery($db)));
    }

    /**
     * @return array<string, string|int|null>
     */
    private function playerRow(): array
    {
        return [
            'name' => 'Test Player',
            'pos' => 'PG',
            'pid' => 11,
            'games' => 12,
            'gameMINavg' => '31.5',
            'gameFGMavg' => '6.20',
            'gameFGAavg' => '13.10',
            'gameFGPavg' => '0.473',
            'gameFTMavg' => '3.40',
            'gameFTAavg' => '4.10',
            'gameFTPavg' => '0.829',
            'game3GMavg' => '1.70',
            'game3GAavg' => '4.30',
            'game3GPavg' => '0.395',
            'gameORBavg' => '1.1',
            'gameREBavg' => '5.2',
            'gameASTavg' => '7.3',
            'gameSTLavg' => '1.4',
            'gameTOVavg' => '2.5',
            'gameBLKavg' => '0.6',
            'gamePFavg' => '2.7',
            'gamePTSavg' => '17.8',
        ];
    }

    public function testGetPeriodStatsPreparedSqlMatchesPeriodAveragesQuery(): void
    {
        $db = new MockDatabase();
        $db->onQuery('gameMINavg', []);

        (new SplitStatsRepository($db))->getPeriodStats(5, '2025-01-01', '2025-01-31');

        $sql = $this->normalizedQuery($db);
        $this->assertStringStartsWith('SELECT p.name, bs.pos, bs.pid, COUNT(DISTINCT bs.game_date) as games,', $sql);
        $this->assertStringContainsString('FROM `ibl_box_scores` bs JOIN `ibl_plr` p ON bs.pid = p.pid', $sql);
        $this->assertStringContainsString('AND p.retired = 0 GROUP BY p.name, bs.pos, bs.pid ORDER BY p.name ASC', $sql);
        $this->assertStringNotContainsString('NULLIF', $sql);
        $this->assertStringNotContainsString('season_year', $sql);
        $this->assertStringNotContainsString('game_type', $sql);
    }

    public function testGetPeriodStatsBindsDatesThenTeamIdThreeTimes(): void
    {
        $db = new MockDatabase();
        $db->onQuery('gameMINavg', []);

        (new SplitStatsRepository($db))->getPeriodStats(5, '2025-01-01', '2025-01-31');

        $this->assertSame(['2025-01-01', '2025-01-31', 5, 5, 5], $db->getLastBoundParams());
    }

    public function testGetPeriodStatsPidFilterAppendsInClauseAndPadsPids(): void
    {
        $db = new MockDatabase();
        $db->onQuery('gameMINavg', []);

        (new SplitStatsRepository($db))->getPeriodStats(5, '2025-01-01', '2025-01-31', [11, 12]);

        $this->assertStringContainsString(' AND bs.pid IN (', $this->mainPreparedQuery($db));
        $expected = ['2025-01-01', '2025-01-31', 5, 5, 5, ...SqlPlaceholders::padPidList([11, 12])];
        $bound = $db->getLastBoundParams();
        $this->assertCount(5 + strlen(SqlPlaceholders::PID_LIST_TYPES), $bound);
        $this->assertSame($expected, $bound);
    }

    public function testGetPeriodStatsEmptyPidFilterAddsNoInClause(): void
    {
        $db = new MockDatabase();
        $db->onQuery('gameMINavg', []);

        (new SplitStatsRepository($db))->getPeriodStats(5, '2025-01-01', '2025-01-31');

        $this->assertStringNotContainsString('bs.pid IN (', $this->mainPreparedQuery($db));
        $this->assertCount(5, $db->getLastBoundParams());
    }

    public function testGetPeriodStatsReturnsEmptyListWhenNoRows(): void
    {
        $db = new MockDatabase();
        $db->onQuery('gameMINavg', []);

        $result = (new SplitStatsRepository($db))->getPeriodStats(5, '2025-01-01', '2025-01-31');

        $this->assertSame([], $result);
    }

    public function testGetPeriodStatsReturnsRowsVerbatimIncludingNullPct(): void
    {
        $row = array_merge($this->playerRow(), ['games' => 0, 'gameFGPavg' => null]);
        $db = new MockDatabase();
        $db->onQuery('gameMINavg', [$row]);

        $result = (new SplitStatsRepository($db))->getPeriodStats(5, '2025-01-01', '2025-01-31');

        $this->assertSame([$row], $result);
    }
}
