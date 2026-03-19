<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Team\SplitStatsRepository;

class SplitStatsRepositoryTest extends DatabaseTestCase
{
    private SplitStatsRepository $repo;
    private const TEST_TID = 1;
    private const TEST_PID = 200090201;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SplitStatsRepository($this->db);
    }

    /**
     * Insert the player + box_scores + schedule data needed for split stats queries.
     * Creates a home game on 2098-01-15 (team 1 at home vs team 2).
     */
    private function insertSplitStatsGameData(): void
    {
        $this->insertTestPlayer(self::TEST_PID, 'SplitStats Test');

        // Player boxscore for a home game (homeTID=1, teamID=1)
        // Date in January 2098 → season_year=2098, game_type=1
        $this->insertPlayerBoxscoreRow(
            '2098-01-15', self::TEST_PID, 'SplitStats Test', 'PG', 2, self::TEST_TID, self::TEST_TID,
            minutes: 32, points2m: 6, points2a: 12, ftm: 4, fta: 5, points3m: 3, points3a: 7,
            orb: 2, drb: 5, ast: 6, stl: 2, tov: 3, blk: 1, pf: 2,
        );

        // Schedule row with scores (needed for wins/losses split)
        $this->insertRow('ibl_schedule', [
            'Year' => 2098,
            'BoxID' => 90001,
            'Date' => '2098-01-15',
            'Visitor' => 2,
            'VScore' => 85,
            'Home' => self::TEST_TID,
            'HScore' => 104,
            'uuid' => 'split-sch-0000-0000-000000090001',
        ]);
    }

    public function testGetSplitStatsHomeReturnsHomeGamesOnly(): void
    {
        $this->insertSplitStatsGameData();

        $result = $this->repo->getSplitStats(self::TEST_TID, 2098, 'home');

        $found = false;
        foreach ($result as $row) {
            if ($row['pid'] === self::TEST_PID) {
                $found = true;
                self::assertSame('SplitStats Test', $row['name']);
                self::assertSame(1, $row['games']);
                break;
            }
        }
        self::assertTrue($found, 'Test player should appear in home split');
    }

    public function testGetSplitStatsRoadReturnsEmptyForHomeOnlyData(): void
    {
        $this->insertSplitStatsGameData();

        // Player's teamID=1 = homeTID, so 'road' split should not include this game
        $result = $this->repo->getSplitStats(self::TEST_TID, 2098, 'road');

        $found = false;
        foreach ($result as $row) {
            if ($row['pid'] === self::TEST_PID) {
                $found = true;
                break;
            }
        }
        self::assertFalse($found, 'Test player should NOT appear in road split');
    }

    public function testGetSplitStatsWinsReturnsWonGamesOnly(): void
    {
        $this->insertSplitStatsGameData();

        $result = $this->repo->getSplitStats(self::TEST_TID, 2098, 'wins');

        $found = false;
        foreach ($result as $row) {
            if ($row['pid'] === self::TEST_PID) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Test player should appear in wins split (HScore=104 > VScore=85)');
    }

    public function testGetSplitStatsLossesReturnsEmptyWhenTeamWon(): void
    {
        $this->insertSplitStatsGameData();

        $result = $this->repo->getSplitStats(self::TEST_TID, 2098, 'losses');

        $found = false;
        foreach ($result as $row) {
            if ($row['pid'] === self::TEST_PID) {
                $found = true;
                break;
            }
        }
        self::assertFalse($found, 'Test player should NOT appear in losses split');
    }

    public function testGetSplitStatsMonthFiltersCorrectly(): void
    {
        $this->insertSplitStatsGameData();

        // January = month 1 — should find our data
        $result = $this->repo->getSplitStats(self::TEST_TID, 2098, 'month_1');
        $found = false;
        foreach ($result as $row) {
            if ($row['pid'] === self::TEST_PID) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Test player should appear in January split');

        // March = month 3 — no data
        $result = $this->repo->getSplitStats(self::TEST_TID, 2098, 'month_3');
        $found = false;
        foreach ($result as $row) {
            if ($row['pid'] === self::TEST_PID) {
                $found = true;
                break;
            }
        }
        self::assertFalse($found, 'Test player should NOT appear in March split');
    }

    public function testGetSplitStatsVsOpponentFiltersCorrectly(): void
    {
        $this->insertSplitStatsGameData();

        // vs Sharks (tid=2) — game was against team 2
        $result = $this->repo->getSplitStats(self::TEST_TID, 2098, 'vs_2');
        $found = false;
        foreach ($result as $row) {
            if ($row['pid'] === self::TEST_PID) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Test player should appear vs tid=2');
    }

    public function testGetValidSplitKeysReturnsExpectedKeys(): void
    {
        $keys = $this->repo->getValidSplitKeys();

        self::assertContains('home', $keys);
        self::assertContains('road', $keys);
        self::assertContains('wins', $keys);
        self::assertContains('losses', $keys);
        self::assertContains('month_1', $keys);
        self::assertContains('div_atlantic', $keys);
        self::assertContains('conf_eastern', $keys);
        self::assertContains('vs_1', $keys);
        self::assertContains('vs_28', $keys);
    }

    public function testGetSplitLabelReturnsCorrectLabels(): void
    {
        self::assertSame('Home', $this->repo->getSplitLabel('home'));
        self::assertSame('Road', $this->repo->getSplitLabel('road'));
        self::assertSame('Wins', $this->repo->getSplitLabel('wins'));
        self::assertSame('January', $this->repo->getSplitLabel('month_1'));
        self::assertSame('vs. Atlantic', $this->repo->getSplitLabel('div_atlantic'));
        self::assertSame('vs. Eastern', $this->repo->getSplitLabel('conf_eastern'));
    }

    public function testGetSplitLabelVsTeamHitsDatabase(): void
    {
        // vs_1 should look up team name for tid=1
        $label = $this->repo->getSplitLabel('vs_1');

        self::assertStringStartsWith('vs. ', $label);
        // Should NOT be the fallback 'vs. Team #1'
        self::assertStringNotContainsString('Team #', $label);
    }
}
