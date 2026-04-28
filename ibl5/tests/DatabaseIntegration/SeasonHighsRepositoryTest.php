<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use SeasonHighs\SeasonHighsRepository;

class SeasonHighsRepositoryTest extends DatabaseTestCase
{
    private SeasonHighsRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SeasonHighsRepository($this->db);
    }

    public function testGetSeasonHighsPlayerReturnsRows(): void
    {
        $this->insertTestPlayer(200090301, 'SeasonHighs Test');

        $this->insertPlayerBoxscoreRow(
            '2098-01-15', 200090301, 'SeasonHighs Test', 'PG', 2, 1, 1,
            minutes: 35, points2m: 10, points2a: 18, ftm: 6, fta: 7, points3m: 4, points3a: 8,
        );

        $this->insertTeamBoxscoreRow('2098-01-15', 'Metros', 1, 2, 1);

        $result = $this->repo->getSeasonHighs(
            'bs.calc_points',
            'Points',
            '',
            '2098-01-01',
            '2098-01-31',
        );

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('name', $first);
        self::assertArrayHasKey('date', $first);
        self::assertArrayHasKey('value', $first);
        self::assertArrayHasKey('pid', $first);
        self::assertArrayHasKey('gameOfThatDay', $first);
        self::assertSame('SeasonHighs Test', $first['name']);
        // calc_points = 10*2 + 6 + 4*3 = 38
        self::assertSame(38, $first['value']);
        self::assertSame(1, $first['gameOfThatDay']);
    }

    public function testGetSeasonHighsTeamReturnsRows(): void
    {
        $this->insertTeamBoxscoreRow('2098-01-15', 'Metros', 1, 2, 1);

        $result = $this->repo->getSeasonHighs(
            'bs.game_ast',
            'Assists',
            '_teams',
            '2098-01-01',
            '2098-01-31',
        );

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('name', $first);
        self::assertArrayHasKey('value', $first);
        self::assertArrayHasKey('teamid', $first);
    }

    public function testGetSeasonHighsWithLocationFilterHome(): void
    {
        $this->insertTestPlayer(200090302, 'Home Filter Test', ['pos' => 'SG', 'ordinal' => 2]);

        // Player at home: teamid=1 = home_teamid=1
        $this->insertPlayerBoxscoreRow(
            '2098-01-15', 200090302, 'Home Filter Test', 'SG', 2, 1, 1,
            minutes: 30, points2m: 8, points2a: 15,
        );

        $result = $this->repo->getSeasonHighs(
            'bs.calc_points',
            'Points',
            '',
            '2098-01-01',
            '2098-01-31',
            15,
            'home',
        );

        $found = false;
        foreach ($result as $row) {
            if ($row['pid'] === 200090302) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Home player should appear in home filter');
    }

    public function testGetSeasonHighsWithLocationFilterAwayReturnsEmpty(): void
    {
        $this->insertTestPlayer(200090303, 'Away Filter Test', ['pos' => 'SF', 'ordinal' => 3]);

        // Player at home (teamid=1 = home_teamid=1), so 'away' should exclude
        $this->insertPlayerBoxscoreRow(
            '2098-01-15', 200090303, 'Away Filter Test', 'SF', 2, 1, 1,
        );

        $result = $this->repo->getSeasonHighs(
            'bs.calc_points',
            'Points',
            '',
            '2098-01-01',
            '2098-01-31',
            15,
            'away',
        );

        $found = false;
        foreach ($result as $row) {
            if (isset($row['pid']) && $row['pid'] === 200090303) {
                $found = true;
                break;
            }
        }
        self::assertFalse($found, 'Home player should NOT appear in away filter');
    }

    public function testGetSeasonHighsReturnsEmptyForNoData(): void
    {
        $result = $this->repo->getSeasonHighs(
            'bs.calc_points',
            'Points',
            '',
            '2099-01-01',
            '2099-01-31',
        );

        self::assertSame([], $result);
    }

    public function testGetSeasonHighsBatchReturnsMultipleStats(): void
    {
        $this->insertTestPlayer(200090401, 'Batch Test One');
        $this->insertTestPlayer(200090402, 'Batch Test Two');

        // Player one: high points (40), low assists
        $this->insertPlayerBoxscoreRow(
            '2098-01-15', 200090401, 'Batch Test One', 'PG', 2, 1, 1,
            minutes: 35, points2m: 12, points2a: 20, ftm: 8, fta: 10, points3m: 4, points3a: 9,
            ast: 2,
        );

        // Player two: lower points, high assists (15)
        $this->insertPlayerBoxscoreRow(
            '2098-01-16', 200090402, 'Batch Test Two', 'PG', 2, 1, 1,
            minutes: 30, points2m: 4, points2a: 10, ftm: 2, fta: 3, points3m: 1, points3a: 4,
            ast: 15,
        );

        $result = $this->repo->getSeasonHighsBatch(
            [
                'POINTS' => '(`game_2gm`*2) + `game_ftm` + (`game_3gm`*3)',
                'ASSISTS' => '`game_ast`',
            ],
            '',
            '2098-01-01',
            '2098-01-31',
        );

        self::assertArrayHasKey('POINTS', $result);
        self::assertArrayHasKey('ASSISTS', $result);

        // POINTS leader is player one (12*2 + 8 + 4*3 = 44)
        self::assertNotEmpty($result['POINTS']);
        self::assertSame('Batch Test One', $result['POINTS'][0]['name']);
        self::assertSame(44, $result['POINTS'][0]['value']);
        self::assertArrayHasKey('pid', $result['POINTS'][0]);
        self::assertArrayHasKey('teamid', $result['POINTS'][0]);
        self::assertArrayHasKey('gameOfThatDay', $result['POINTS'][0]);

        // ASSISTS leader is player two
        self::assertNotEmpty($result['ASSISTS']);
        self::assertSame('Batch Test Two', $result['ASSISTS'][0]['name']);
        self::assertSame(15, $result['ASSISTS'][0]['value']);
    }

    public function testGetSeasonHighsBatchReturnsEmptyEntriesForUnmatchedStats(): void
    {
        $result = $this->repo->getSeasonHighsBatch(
            [
                'POINTS' => '(`game_2gm`*2) + `game_ftm` + (`game_3gm`*3)',
                'BLOCKS' => '`game_blk`',
            ],
            '',
            '2099-01-01',
            '2099-01-31',
        );

        // Both keys present even when no rows match — service callers iterate over expected keys.
        self::assertArrayHasKey('POINTS', $result);
        self::assertArrayHasKey('BLOCKS', $result);
        self::assertSame([], $result['POINTS']);
        self::assertSame([], $result['BLOCKS']);
    }

    public function testGetRcbSeasonHighsReturnsRows(): void
    {
        // Insert test data within transaction to avoid seed dependency issues
        $this->insertRow('ibl_rcb_season_records', [
            'season_year' => 2098,
            'scope' => 'league',
            'teamid' => 0,
            'context' => 'home',
            'stat_category' => 'pts',
            'ranking' => 1,
            'player_name' => 'RCB Test Player',
            'player_position' => 'PG',
            'stat_value' => 55,
            'record_season_year' => 2098,
        ]);

        $result = $this->repo->getRcbSeasonHighs(2098, 'home');

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertSame('pts', $first['stat_category']);
        self::assertSame(1, $first['ranking']);
        self::assertSame('RCB Test Player', $first['player_name']);
        self::assertSame(55, $first['stat_value']);
    }

    public function testGetRcbSeasonHighsReturnsEmptyForUnknownYear(): void
    {
        $result = $this->repo->getRcbSeasonHighs(9999, 'home');

        self::assertSame([], $result);
    }
}
