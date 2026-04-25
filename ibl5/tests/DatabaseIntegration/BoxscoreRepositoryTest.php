<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Boxscore\BoxscoreRepository;

class BoxscoreRepositoryTest extends DatabaseTestCase
{
    private BoxscoreRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new BoxscoreRepository($this->db);
    }

    public function testFindTeamBoxscoreReturnsRow(): void
    {
        $this->insertTeamBoxscoreRow('2025-01-15', 'Metros', 1, 2, 1);

        $row = $this->repo->findTeamBoxscore('2025-01-15', 2, 1, 1);

        self::assertNotNull($row);
        self::assertArrayHasKey('visitor_q1_points', $row);
        self::assertArrayHasKey('home_q1_points', $row);
    }

    public function testFindTeamBoxscoreReturnsNullForMissing(): void
    {
        $row = $this->repo->findTeamBoxscore('2099-01-01', 2, 1, 1);

        self::assertNull($row);
    }

    public function testInsertTeamBoxscoreCreatesRow(): void
    {
        $this->repo->insertTeamBoxscore(
            '2025-02-01', 'Metros', 1, 2, 1,
            10000, 15000, 20, 10, 25, 5,
            28, 24, 22, 30, 0,
            20, 22, 18, 25, 0,
            30, 60, 15, 20, 8, 22, 10, 30, 20, 8, 12, 5, 18
        );

        $row = $this->repo->findTeamBoxscore('2025-02-01', 2, 1, 1);
        self::assertNotNull($row);
        self::assertSame(28, $row['visitor_q1_points']);
        self::assertSame(20, $row['home_q1_points']);
    }

    public function testInsertPlayerBoxscoreCreatesRow(): void
    {
        $this->repo->insertPlayerBoxscore(
            '2025-02-01', 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'Test Player One', 'PG', 1, 2, 1, 1,
            10000, 15000, 20, 10, 25, 5,
            1, 32, 8, 15, 4, 5, 2, 6, 2, 5, 6, 1, 3, 1, 2
        );

        $stmt = $this->db->prepare(
            "SELECT name, pid, game_min FROM ibl_box_scores WHERE game_date = '2025-02-01' AND pid = 1"
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame('Test Player One', $row['name']);
        self::assertSame(1, $row['pid']);
        self::assertSame(32, $row['game_min']);
    }

    public function testDeleteTeamBoxscoresByGameRemovesRows(): void
    {
        $this->insertTeamBoxscoreRow('2025-03-01', 'Metros', 1, 2, 1);

        $deleted = $this->repo->deleteTeamBoxscoresByGame('2025-03-01', 2, 1, 1);

        self::assertGreaterThan(0, $deleted);

        $row = $this->repo->findTeamBoxscore('2025-03-01', 2, 1, 1);
        self::assertNull($row);
    }

    public function testDeletePlayerBoxscoresByGameRemovesRows(): void
    {
        $this->repo->insertPlayerBoxscore(
            '2025-03-01', 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'Test Player One', 'PG', 1, 2, 1, 1,
            10000, 15000, 20, 10, 25, 5,
            1, 30, 7, 14, 3, 4, 1, 5, 1, 4, 5, 1, 2, 1, 3
        );

        $deleted = $this->repo->deletePlayerBoxscoresByGame('2025-03-01', 2, 1);

        self::assertGreaterThan(0, $deleted);
    }

    public function testDeletePreseasonBoxScoresRemovesOnlyPreseason(): void
    {
        // Preseason uses year 9998, month 11
        $this->insertTeamBoxscoreRow('9998-11-15', 'Metros', 1, 2, 1);
        // Regular season boxscore should not be deleted
        $this->insertTeamBoxscoreRow('2025-01-15', 'Metros', 1, 2, 1);

        $this->repo->deletePreseasonBoxScores();

        $preseason = $this->repo->findTeamBoxscore('9998-11-15', 2, 1, 1);
        self::assertNull($preseason);

        $regular = $this->repo->findTeamBoxscore('2025-01-15', 2, 1, 1);
        self::assertNotNull($regular);
    }

    public function testFindAllStarTeamNamesReturnsNames(): void
    {
        // Insert two team boxscore rows for All-Star teams (visitor=50, home=51)
        $this->insertRow('ibl_box_scores_teams', [
            'game_date' => '2025-02-15',
            'name' => 'Team West',
            'game_of_that_day' => 1,
            'visitor_teamid' => 50,
            'home_teamid' => 51,
            'attendance' => 20000,
            'capacity' => 20000,
            'visitor_wins' => 0,
            'visitor_losses' => 0,
            'home_wins' => 0,
            'home_losses' => 0,
            'visitor_q1_points' => 30,
            'visitor_q2_points' => 28,
            'visitor_q3_points' => 32,
            'visitor_q4_points' => 35,
            'visitor_ot_points' => 0,
            'home_q1_points' => 25,
            'home_q2_points' => 30,
            'home_q3_points' => 28,
            'home_q4_points' => 32,
            'home_ot_points' => 0,
            'game_2gm' => 40,
            'game_2ga' => 80,
            'game_ftm' => 20,
            'game_fta' => 25,
            'game_3gm' => 12,
            'game_3ga' => 30,
            'game_orb' => 15,
            'game_drb' => 35,
            'game_ast' => 25,
            'game_stl' => 10,
            'game_tov' => 15,
            'game_blk' => 6,
            'game_pf' => 20,
        ]);
        $this->insertRow('ibl_box_scores_teams', [
            'game_date' => '2025-02-15',
            'name' => 'Team East',
            'game_of_that_day' => 1,
            'visitor_teamid' => 50,
            'home_teamid' => 51,
            'attendance' => 20000,
            'capacity' => 20000,
            'visitor_wins' => 0,
            'visitor_losses' => 0,
            'home_wins' => 0,
            'home_losses' => 0,
            'visitor_q1_points' => 25,
            'visitor_q2_points' => 30,
            'visitor_q3_points' => 28,
            'visitor_q4_points' => 32,
            'visitor_ot_points' => 0,
            'home_q1_points' => 30,
            'home_q2_points' => 28,
            'home_q3_points' => 32,
            'home_q4_points' => 35,
            'home_ot_points' => 0,
            'game_2gm' => 38,
            'game_2ga' => 78,
            'game_ftm' => 18,
            'game_fta' => 23,
            'game_3gm' => 10,
            'game_3ga' => 28,
            'game_orb' => 12,
            'game_drb' => 33,
            'game_ast' => 22,
            'game_stl' => 8,
            'game_tov' => 13,
            'game_blk' => 5,
            'game_pf' => 18,
        ]);

        $names = $this->repo->findAllStarTeamNames('2025-02-15');

        self::assertNotNull($names);
        self::assertSame('Team West', $names['awayName']);
        self::assertSame('Team East', $names['homeName']);
    }

    public function testFindAllStarTeamNamesReturnsNullWhenMissing(): void
    {
        $names = $this->repo->findAllStarTeamNames('2099-02-15');

        self::assertNull($names);
    }

    public function testRenameAllStarTeamUpdatesName(): void
    {
        $id = $this->insertRow('ibl_box_scores_teams', [
            'game_date' => '2025-02-16',
            'name' => 'Team Away',
            'game_of_that_day' => 1,
            'visitor_teamid' => 50,
            'home_teamid' => 51,
            'attendance' => 20000,
            'capacity' => 20000,
            'visitor_wins' => 0,
            'visitor_losses' => 0,
            'home_wins' => 0,
            'home_losses' => 0,
            'visitor_q1_points' => 30,
            'visitor_q2_points' => 28,
            'visitor_q3_points' => 32,
            'visitor_q4_points' => 35,
            'visitor_ot_points' => 0,
            'home_q1_points' => 25,
            'home_q2_points' => 30,
            'home_q3_points' => 28,
            'home_q4_points' => 32,
            'home_ot_points' => 0,
            'game_2gm' => 40,
            'game_2ga' => 80,
            'game_ftm' => 20,
            'game_fta' => 25,
            'game_3gm' => 12,
            'game_3ga' => 30,
            'game_orb' => 15,
            'game_drb' => 35,
            'game_ast' => 25,
            'game_stl' => 10,
            'game_tov' => 15,
            'game_blk' => 6,
            'game_pf' => 20,
        ]);

        $affected = $this->repo->renameAllStarTeam($id, 'Team LeBron');

        self::assertSame(1, $affected);

        $stmt = $this->db->prepare("SELECT name FROM ibl_box_scores_teams WHERE id = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame('Team LeBron', $row['name']);
    }

    // ── hasNullTeamIdPlayerBoxscores ────────────────────────────

    public function testHasNullTeamIdReturnsFalseWhenAllHaveTeamId(): void
    {
        $this->insertTestPlayer(200010030, 'BS NullTid', ['teamid' => 1]);
        $this->insertPlayerBoxscoreRow(
            '2025-04-01', 200010030, 'BS NullTid', 'PG', 2, 1, 1
        );

        $result = $this->repo->hasNullTeamIdPlayerBoxscores('2025-04-01', 2, 1);

        self::assertFalse($result);
    }

    public function testHasNullTeamIdReturnsTrueWhenNullExists(): void
    {
        // Create the player first (FK constraint requires it)
        $this->insertTestPlayer(200010031, 'BS NullTidPlr', ['teamid' => 1]);

        // Insert a player boxscore with NULL teamid manually
        $this->insertRow('ibl_box_scores', [
            'game_date' => '2025-04-02',
            'name' => 'BS NullTidPlr',
            'pos' => 'PG',
            'pid' => 200010031,
            'visitor_teamid' => 2,
            'home_teamid' => 1,
            // teamid intentionally omitted — will be NULL
            'game_min' => 30,
            'game_2gm' => 5,
            'game_2ga' => 10,
            'game_ftm' => 4,
            'game_fta' => 5,
            'game_3gm' => 2,
            'game_3ga' => 6,
            'game_orb' => 2,
            'game_drb' => 6,
            'game_ast' => 5,
            'game_stl' => 2,
            'game_tov' => 2,
            'game_blk' => 1,
            'game_pf' => 3,
            'game_of_that_day' => 1,
            'attendance' => 10000,
            'capacity' => 15000,
            'visitor_wins' => 20,
            'visitor_losses' => 10,
            'home_wins' => 25,
            'home_losses' => 5,
            'uuid' => 'bs-nulltid-' . bin2hex(random_bytes(6)),
        ]);

        $result = $this->repo->hasNullTeamIdPlayerBoxscores('2025-04-02', 2, 1);

        self::assertTrue($result);
    }

    public function testHasNullTeamIdReturnsFalseForNoMatchingGames(): void
    {
        $result = $this->repo->hasNullTeamIdPlayerBoxscores('2099-01-01', 999, 998);

        self::assertFalse($result);
    }

    // ── findAllStarGamesWithDefaultNames ────────────────────────

    public function testFindAllStarGamesWithDefaultNamesReturnsMatchingRows(): void
    {
        $this->insertRow('ibl_box_scores_teams', [
            'game_date' => '2025-02-20',
            'name' => 'Team Away',
            'game_of_that_day' => 1,
            'visitor_teamid' => 50,
            'home_teamid' => 51,
            'attendance' => 20000, 'capacity' => 20000,
            'visitor_wins' => 0, 'visitor_losses' => 0, 'home_wins' => 0, 'home_losses' => 0,
            'visitor_q1_points' => 25, 'visitor_q2_points' => 25, 'visitor_q3_points' => 25, 'visitor_q4_points' => 25, 'visitor_ot_points' => 0,
            'home_q1_points' => 25, 'home_q2_points' => 25, 'home_q3_points' => 25, 'home_q4_points' => 25, 'home_ot_points' => 0,
            'game_2gm' => 30, 'game_2ga' => 60, 'game_ftm' => 15, 'game_fta' => 20,
            'game_3gm' => 8, 'game_3ga' => 22, 'game_orb' => 10, 'game_drb' => 30,
            'game_ast' => 20, 'game_stl' => 8, 'game_tov' => 12, 'game_blk' => 5, 'game_pf' => 18,
        ]);

        $rows = $this->repo->findAllStarGamesWithDefaultNames();

        $matching = array_filter(
            $rows,
            static fn (array $r): bool => $r['game_date'] === '2025-02-20',
        );

        self::assertNotEmpty($matching);
        $first = array_values($matching)[0];
        self::assertSame('Team Away', $first['name']);
        self::assertSame(50, $first['visitor_teamid']);
    }

    public function testFindAllStarGamesWithDefaultNamesExcludesRenamedTeams(): void
    {
        $this->insertRow('ibl_box_scores_teams', [
            'game_date' => '2025-02-21',
            'name' => 'Team LeBron',
            'game_of_that_day' => 1,
            'visitor_teamid' => 50,
            'home_teamid' => 51,
            'attendance' => 20000, 'capacity' => 20000,
            'visitor_wins' => 0, 'visitor_losses' => 0, 'home_wins' => 0, 'home_losses' => 0,
            'visitor_q1_points' => 25, 'visitor_q2_points' => 25, 'visitor_q3_points' => 25, 'visitor_q4_points' => 25, 'visitor_ot_points' => 0,
            'home_q1_points' => 25, 'home_q2_points' => 25, 'home_q3_points' => 25, 'home_q4_points' => 25, 'home_ot_points' => 0,
            'game_2gm' => 30, 'game_2ga' => 60, 'game_ftm' => 15, 'game_fta' => 20,
            'game_3gm' => 8, 'game_3ga' => 22, 'game_orb' => 10, 'game_drb' => 30,
            'game_ast' => 20, 'game_stl' => 8, 'game_tov' => 12, 'game_blk' => 5, 'game_pf' => 18,
        ]);

        $rows = $this->repo->findAllStarGamesWithDefaultNames();

        $matching = array_filter(
            $rows,
            static fn (array $r): bool => $r['game_date'] === '2025-02-21',
        );

        self::assertEmpty($matching);
    }

    // ── getPlayersForAllStarTeam ────────────────────────────────

    public function testGetPlayersForAllStarTeamReturnsPlayerNames(): void
    {
        $this->insertTestPlayer(200010032, 'BS AllStar PG', ['teamid' => 50]);

        $this->insertRow('ibl_box_scores', [
            'game_date' => '2025-02-22',
            'name' => 'BS AllStar PG',
            'pos' => 'PG',
            'pid' => 200010032,
            'visitor_teamid' => 50,
            'home_teamid' => 51,
            'teamid' => 50,
            'game_min' => 25, 'game_2gm' => 4, 'game_2ga' => 8, 'game_ftm' => 2, 'game_fta' => 3,
            'game_3gm' => 1, 'game_3ga' => 3, 'game_orb' => 1, 'game_drb' => 3,
            'game_ast' => 5, 'game_stl' => 1, 'game_tov' => 1, 'game_blk' => 0, 'game_pf' => 2,
            'game_of_that_day' => 1, 'attendance' => 20000, 'capacity' => 20000,
            'visitor_wins' => 0, 'visitor_losses' => 0, 'home_wins' => 0, 'home_losses' => 0,
            'uuid' => 'bs-allstar-' . bin2hex(random_bytes(6)),
        ]);

        $names = $this->repo->getPlayersForAllStarTeam('2025-02-22', 50);

        self::assertContains('BS AllStar PG', $names);
    }

    public function testGetPlayersForAllStarTeamReturnsEmptyForNoMatch(): void
    {
        $names = $this->repo->getPlayersForAllStarTeam('2099-02-22', 50);

        self::assertSame([], $names);
    }

    // ── deleteRegularSeasonAndPlayoffsBoxScores ────────────────

    public function testDeleteRegularSeasonAndPlayoffsBoxScoresRemovesJanJuneGames(): void
    {
        // Season starting 2098: range is 2098-11-01 to 2099-06-30
        $this->insertTeamBoxscoreRow('2099-01-15', 'Metros', 1, 2, 1);
        $this->insertTeamBoxscoreRow('2099-06-10', 'Metros', 1, 3, 1);
        // HEAT October game (before range start) — should survive
        $this->insertTeamBoxscoreRow('2098-10-05', 'Metros', 1, 4, 1);

        $result = $this->repo->deleteRegularSeasonAndPlayoffsBoxScores(2098);

        self::assertTrue($result);
        self::assertNull($this->repo->findTeamBoxscore('2099-01-15', 2, 1, 1));
        self::assertNull($this->repo->findTeamBoxscore('2099-06-10', 3, 1, 1));
        self::assertNotNull($this->repo->findTeamBoxscore('2098-10-05', 4, 1, 1));
    }

    // ── deleteHeatBoxScores ─────────────────────────────────────

    public function testDeleteHeatBoxScoresRemovesOctoberGames(): void
    {
        // HEAT uses October dates (game_type=3), seasonStartingYear=2025 → Oct 2025
        $this->insertTeamBoxscoreRow('2025-10-15', 'Metros', 1, 2, 1);
        // Regular season (Jan) should not be deleted
        $this->insertTeamBoxscoreRow('2025-01-20', 'Metros', 1, 3, 1);

        $result = $this->repo->deleteHeatBoxScores(2025);

        self::assertTrue($result);

        $heat = $this->repo->findTeamBoxscore('2025-10-15', 2, 1, 1);
        self::assertNull($heat);

        $regular = $this->repo->findTeamBoxscore('2025-01-20', 3, 1, 1);
        self::assertNotNull($regular);
    }
}
