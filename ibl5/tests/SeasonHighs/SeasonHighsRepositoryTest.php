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

    public function testGetSeasonHighsNormalizesPlayerRow(): void
    {
        $this->mockDb->onQuery('ibl_box_scores', [[
            'name'            => 'High Scorer',
            'date'            => '2025-01-15',
            'POINTS'          => '38',
            'pid'             => '7',
            'teamid'          => '3',
            'team_city'       => 'City',
            'color1'          => '00FF00',
            'color2'          => '0000FF',
            'box_id'          => '99',
            'game_of_that_day' => '1',
        ]]);

        $result = $this->repo()->getSeasonHighs('(`game_2gm`*2)', 'POINTS', '', '2025-01-01', '2025-01-31');

        $this->assertCount(1, $result);
        $entry = $result[0];

        $this->assertSame(38, $entry['value']);
        $this->assertSame(7, $entry['pid']);
        $this->assertSame(3, $entry['teamid']);
        $this->assertSame(99, $entry['boxId']);
        $this->assertSame(1, $entry['gameOfThatDay']);
        $this->assertSame('00FF00', $entry['color1']);
    }

    public function testGetSeasonHighsAppliesColorAndKeyDefaults(): void
    {
        $this->mockDb->onQuery('ibl_box_scores', [[
            'name'      => 'No Colors',
            'date'      => '2025-01-10',
            'POINTS'    => '12',
            'teamid'    => '5',
            'team_city' => 'Somewhere',
        ]]);

        $result = $this->repo()->getSeasonHighs('(`game_2gm`*2)', 'POINTS', '', '2025-01-01', '2025-01-31');

        $this->assertCount(1, $result);
        $entry = $result[0];

        $this->assertSame('FFFFFF', $entry['color1']);
        $this->assertSame('000000', $entry['color2']);
        $this->assertArrayNotHasKey('pid', $entry);
        $this->assertArrayNotHasKey('boxId', $entry);
    }

    public function testGetSeasonHighsTeamSuffixShapeOmitsPid(): void
    {
        $this->mockDb->onQuery('ibl_box_scores', [[
            'name'      => 'Hawks',
            'date'      => '2025-01-20',
            'POINTS'    => '120',
            'teamid'    => '2',
            'team_city' => 'Atlanta',
            'color1'    => 'FF0000',
            'color2'    => '000000',
        ]]);

        $result = $this->repo()->getSeasonHighs('(`game_2gm`*2)', 'POINTS', '_teams', '2025-01-01', '2025-01-31');

        $this->assertCount(1, $result);
        $entry = $result[0];

        $this->assertSame(2, $entry['teamid']);
        $this->assertArrayNotHasKey('pid', $entry);
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
            ['POINTS' => 'gamePTS', 'ASSISTS' => 'gameAST'],
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
            ['POINTS' => 'gamePTS'],
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
            ['POINTS' => 'gamePTS', 'ASSISTS' => 'gameAST'],
            '',
            '2025-01-01',
            '2025-01-31'
        );

        $this->assertArrayHasKey('POINTS', $result);
        $this->assertArrayHasKey('ASSISTS', $result);
        $this->assertSame([], $result['POINTS']);
        $this->assertSame([], $result['ASSISTS']);
    }

    public function testGetSeasonHighsRejectsStatNameWithNoAlphanumerics(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repo()->getSeasonHighs('`game_ast`', '`;  ', '', '2025-01-01', '2025-01-31');
    }

    public function testGetSeasonHighsStripsAliasAndNeverEmbedsPayload(): void
    {
        $this->mockDb->onQuery('ibl_box_scores', []);
        $this->repo()->getSeasonHighs('`game_ast`', 'AST`; DROP TABLE ibl_plr; --', '', '2025-01-01', '2025-01-31');
        $sql = implode("\n", $this->mockDb->getExecutedQueries());
        $this->assertStringContainsString('AS ASTDROPTABLEibl_plr', $sql);
        $this->assertStringNotContainsString('DROP TABLE ibl_plr;', $sql);
    }

    public function testGetSeasonHighsRejectsExpressionOutsideAllowlist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repo()->getSeasonHighs('1; DROP TABLE ibl_plr; -- ', 'POINTS', '', '2025-01-01', '2025-01-31');
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
        $result = $this->repo()->getSeasonHighs($expression, 'Field Goals Made', '', '2025-01-01', '2025-01-31');
        $this->assertSame([], $result);
        $this->assertStringContainsString('AS FieldGoalsMade', implode("\n", $this->mockDb->getExecutedQueries()));
    }
}
