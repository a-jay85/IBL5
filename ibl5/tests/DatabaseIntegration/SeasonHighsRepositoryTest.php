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
        self::assertSame('SeasonHighs Test', $first['name']);
        // calc_points = 10*2 + 6 + 4*3 = 38
        self::assertSame(38, $first['value']);
    }

    public function testGetSeasonHighsTeamReturnsRows(): void
    {
        $this->insertTeamBoxscoreRow('2098-01-15', 'Metros', 1, 2, 1);

        $result = $this->repo->getSeasonHighs(
            'bs.gameAST',
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
