<?php

declare(strict_types=1);

namespace Tests\SeasonHighs;

use Tests\WideUnit\WideUnitTestCase;

/**
 * @covers \SeasonHighs\SeasonHighsRepository
 */
class SeasonHighsRepositoryTest extends WideUnitTestCase
{
    private function repo(): \SeasonHighs\SeasonHighsRepository
    {
        $db = $this->mockDb;
        self::assertNotNull($db);
        return new \SeasonHighs\SeasonHighsRepository($db);
    }

    public function testGetSeasonHighsBatchBucketsSortsAndSkipsUnknown(): void
    {
        $this->mockDb->onQuery('ibl_box_scores', [
            ['stat_category' => 'POINTS',  'stat_value' => '40', 'date' => '2025-01-15', 'name' => 'P1'],
            ['stat_category' => 'POINTS',  'stat_value' => '50', 'date' => '2025-01-16', 'name' => 'P2'],
            ['stat_category' => 'ASSISTS', 'stat_value' => '10', 'date' => '2025-01-10', 'name' => 'P3'],
            ['stat_category' => 'UNKNOWN', 'stat_value' => '99', 'date' => '2025-01-01', 'name' => 'P4'],
        ]);

        $result = $this->repo()->getSeasonHighsBatch(
            ['POINTS' => '`game_pts`', 'ASSISTS' => '`game_ast`'],
            '',
            '2025-01-01',
            '2025-01-31'
        );

        $this->assertSame(50, $result['POINTS'][0]['value']);
        $this->assertSame(40, $result['POINTS'][1]['value']);
        $this->assertSame(10, $result['ASSISTS'][0]['value']);
        $this->assertArrayNotHasKey('UNKNOWN', $result);
    }

    public function testGetSeasonHighsBatchTieBreaksByDateAscending(): void
    {
        $this->mockDb->onQuery('ibl_box_scores', [
            ['stat_category' => 'POINTS', 'stat_value' => '30', 'date' => '2025-02-02', 'name' => 'Later'],
            ['stat_category' => 'POINTS', 'stat_value' => '30', 'date' => '2025-01-01', 'name' => 'Earlier'],
        ]);

        $result = $this->repo()->getSeasonHighsBatch(
            ['POINTS' => '`game_pts`'],
            '',
            '2025-01-01',
            '2025-02-28'
        );

        $this->assertSame('Earlier', $result['POINTS'][0]['name']);
        $this->assertSame('Later', $result['POINTS'][1]['name']);
    }

    public function testGetSeasonHighsBatchEmptyStatsShortCircuits(): void
    {
        $result = $this->repo()->getSeasonHighsBatch([], '', '2025-01-01', '2025-01-31');

        $this->assertSame([], $result);
        $this->assertQueryNotExecuted('ibl_box_scores');
    }

    public function testGetSeasonHighsBatchInitializesEmptyEntryPerStat(): void
    {
        $this->mockDb->onQuery('ibl_box_scores', []);

        $result = $this->repo()->getSeasonHighsBatch(
            ['POINTS' => '`game_pts`', 'ASSISTS' => '`game_ast`'],
            '',
            '2025-01-01',
            '2025-01-31'
        );

        $this->assertArrayHasKey('POINTS', $result);
        $this->assertArrayHasKey('ASSISTS', $result);
        $this->assertSame([], $result['POINTS']);
        $this->assertSame([], $result['ASSISTS']);
    }

    /**
     * Payloads built only from allowlisted characters that the token pattern
     * must still reject.
     *
     * @return iterable<string, array{string}>
     */
    public static function tokenOutsideAllowlistProvider(): iterable
    {
        yield 'bare word subquery' => ['(SELECT `pid` FROM `ibl_plr`)'];
        yield 'bare column name' => ['game_ast'];
        yield 'unbalanced backtick' => ['`game_ast` + `1 '];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('tokenOutsideAllowlistProvider')]
    public function testGetSeasonHighsBatchRejectsBareWordsAndUnbalancedBackticks(string $expression): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repo()->getSeasonHighsBatch(['POINTS' => $expression], '', '2025-01-01', '2025-01-31');
    }

    public function testGetSeasonHighsBatchRejectsExpressionOutsideAllowlistViaProvider(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repo()->getSeasonHighsBatch(['POINTS' => '1; DROP TABLE ibl_plr; -- '], '', '2025-01-01', '2025-01-31');
    }

    public function testGetSeasonHighsBatchRejectsExpressionOutsideAllowlist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repo()->getSeasonHighsBatch(['POINTS' => '`game_ast`) UNION SELECT 1 -- '], '', '2025-01-01', '2025-01-31');
    }

    /**
     * Literals copied from SeasonHighsService::STATS. HOME_AWAY_STATS is a
     * subset using the same strings.
     *
     * @return iterable<string, array{string}>
     */
    public static function serviceStatExpressionProvider(): iterable
    {
        yield 'POINTS' => ['(`game_2gm`*2) + `game_ftm` + (`game_3gm`*3)'];
        yield 'REBOUNDS' => ['(`game_orb` + `game_drb`)'];
        yield 'ASSISTS' => ['`game_ast`'];
        yield 'STEALS' => ['`game_stl`'];
        yield 'BLOCKS' => ['`game_blk`'];
        yield 'TURNOVERS' => ['`game_tov`'];
        yield 'FGM' => ['(`game_2gm` + `game_3gm`)'];
        yield 'FTM' => ['`game_ftm`'];
        yield '3PM' => ['`game_3gm`'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('serviceStatExpressionProvider')]
    public function testEveryServiceStatExpressionPassesAllowlist(string $expression): void
    {
        $this->mockDb->onQuery('ibl_box_scores', []);
        $result = $this->repo()->getSeasonHighsBatch(['Field Goals Made' => $expression], '', '2025-01-01', '2025-01-31');
        $this->assertSame(['Field Goals Made' => []], $result);
        // MockDatabase strips backticks from recorded SQL, so compare without them.
        $this->assertStringContainsString('(' . str_replace('`', '', $expression) . ') AS stat_value', implode("\n", $this->mockDb->getExecutedQueries()));
    }
}
