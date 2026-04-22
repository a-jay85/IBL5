<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Api\Repository\ApiPlayerStatsRepository;

/**
 * Tests ApiPlayerStatsRepository against real MariaDB —
 * career stats via vw_player_career_stats view and season history from ibl_hist.
 */
class ApiPlayerStatsRepositoryTest extends DatabaseTestCase
{
    private ApiPlayerStatsRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ApiPlayerStatsRepository($this->db);
    }

    // ── getCareerStats ──────────────────────────────────────────

    public function testGetCareerStatsReturnsPlayerData(): void
    {
        $this->insertTestPlayer(200000085, 'DB Test Career Stats', [
            'uuid' => 'batch7-career-stats-00000085',
            'car_gm' => 100,
            'car_min' => 3200,
            'car_fgm' => 400,
            'car_fga' => 800,
            'car_ftm' => 150,
            'car_fta' => 180,
            'car_tgm' => 60,
            'car_3ga' => 160,
            'car_orb' => 50,
            'car_drb' => 250,
            'car_ast' => 300,
            'car_stl' => 80,
            'car_blk' => 40,
        ]);

        $stats = $this->repo->getCareerStats('batch7-career-stats-00000085');

        self::assertNotNull($stats);
        self::assertSame('batch7-career-stats-00000085', $stats['player_uuid']);
        self::assertSame('DB Test Career Stats', $stats['name']);
        self::assertSame(100, $stats['career_games']);
        self::assertSame(3200, $stats['career_minutes']);
    }

    public function testGetCareerStatsReturnsNullForUnknownUuid(): void
    {
        $result = $this->repo->getCareerStats('nonexistent-uuid-career');

        self::assertNull($result);
    }

    public function testGetCareerStatsIncludesCalculatedFields(): void
    {
        $this->insertTestPlayer(200000086, 'DB Test Career Calcs', [
            'uuid' => 'batch7-career-calcs-00000086',
            'car_gm' => 50,
            'car_min' => 1600,
            'car_fgm' => 200,
            'car_fga' => 400,
            'car_ftm' => 80,
            'car_fta' => 100,
            'car_tgm' => 30,
            'car_3ga' => 80,
            'car_orb' => 25,
            'car_drb' => 125,
            'car_ast' => 150,
            'car_stl' => 40,
            'car_blk' => 20,
        ]);

        $stats = $this->repo->getCareerStats('batch7-career-calcs-00000086');

        self::assertNotNull($stats);
        self::assertArrayHasKey('ppg_career', $stats);
        self::assertArrayHasKey('rpg_career', $stats);
        self::assertArrayHasKey('apg_career', $stats);
        self::assertArrayHasKey('fg_pct_career', $stats);
        self::assertArrayHasKey('ft_pct_career', $stats);
        self::assertArrayHasKey('three_pt_pct_career', $stats);

        // career_points = round(car_fgm * 2 + car_tgm + car_ftm) = round(200*2 + 30 + 80) = 510
        self::assertSame(510, (int) $stats['career_points']);
        // career_rebounds = car_orb + car_drb = 25 + 125 = 150
        self::assertSame(150, $stats['career_rebounds']);
    }

    // ── getSeasonHistory ────────────────────────────────────────

    public function testGetSeasonHistoryReturnsYearlyData(): void
    {
        $this->insertTestPlayer(200000087, 'DB Test Season History', [
            'uuid' => 'batch7-season-hist-00000087',
        ]);

        $this->insertHistRow(200000087, 'DB Test Season History', 2025, [
            'teamid' => 1,
            'games' => 50,
            'pts' => 750,
        ]);
        $this->insertHistRow(200000087, 'DB Test Season History', 2024, [
            'teamid' => 1,
            'games' => 48,
            'pts' => 700,
        ]);

        $history = $this->repo->getSeasonHistory('batch7-season-hist-00000087');

        self::assertCount(2, $history);

        // Should be ordered by year DESC
        self::assertSame(2025, $history[0]['year']);
        self::assertSame(2024, $history[1]['year']);
    }

    public function testGetSeasonHistoryIncludesTeamData(): void
    {
        $this->insertTestPlayer(200000088, 'DB Test History Team', [
            'uuid' => 'batch7-hist-team-00000088',
        ]);

        $this->insertHistRow(200000088, 'DB Test History Team', 2025, [
            'teamid' => 1,
        ]);

        $history = $this->repo->getSeasonHistory('batch7-hist-team-00000088');

        self::assertNotEmpty($history);
        $row = $history[0];

        self::assertArrayHasKey('player_uuid', $row);
        self::assertArrayHasKey('team_uuid', $row);
        self::assertArrayHasKey('team_city', $row);
        self::assertArrayHasKey('team_name', $row);
    }

    public function testGetSeasonHistoryReturnsEmptyForUnknownUuid(): void
    {
        $result = $this->repo->getSeasonHistory('nonexistent-uuid-history');

        self::assertSame([], $result);
    }
}
