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
        self::assertArrayHasKey('visitorQ1points', $row);
        self::assertArrayHasKey('homeQ1points', $row);
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
        self::assertSame(28, $row['visitorQ1points']);
        self::assertSame(20, $row['homeQ1points']);
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
            "SELECT name, pid, gameMIN FROM ibl_box_scores WHERE Date = '2025-02-01' AND pid = 1"
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame('Test Player One', $row['name']);
        self::assertSame(1, $row['pid']);
        self::assertSame(32, $row['gameMIN']);
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
            'Date' => '2025-02-15',
            'name' => 'Team West',
            'gameOfThatDay' => 1,
            'visitorTeamID' => 50,
            'homeTeamID' => 51,
            'attendance' => 20000,
            'capacity' => 20000,
            'visitorWins' => 0,
            'visitorLosses' => 0,
            'homeWins' => 0,
            'homeLosses' => 0,
            'visitorQ1points' => 30,
            'visitorQ2points' => 28,
            'visitorQ3points' => 32,
            'visitorQ4points' => 35,
            'visitorOTpoints' => 0,
            'homeQ1points' => 25,
            'homeQ2points' => 30,
            'homeQ3points' => 28,
            'homeQ4points' => 32,
            'homeOTpoints' => 0,
            'game2GM' => 40,
            'game2GA' => 80,
            'gameFTM' => 20,
            'gameFTA' => 25,
            'game3GM' => 12,
            'game3GA' => 30,
            'gameORB' => 15,
            'gameDRB' => 35,
            'gameAST' => 25,
            'gameSTL' => 10,
            'gameTOV' => 15,
            'gameBLK' => 6,
            'gamePF' => 20,
        ]);
        $this->insertRow('ibl_box_scores_teams', [
            'Date' => '2025-02-15',
            'name' => 'Team East',
            'gameOfThatDay' => 1,
            'visitorTeamID' => 50,
            'homeTeamID' => 51,
            'attendance' => 20000,
            'capacity' => 20000,
            'visitorWins' => 0,
            'visitorLosses' => 0,
            'homeWins' => 0,
            'homeLosses' => 0,
            'visitorQ1points' => 25,
            'visitorQ2points' => 30,
            'visitorQ3points' => 28,
            'visitorQ4points' => 32,
            'visitorOTpoints' => 0,
            'homeQ1points' => 30,
            'homeQ2points' => 28,
            'homeQ3points' => 32,
            'homeQ4points' => 35,
            'homeOTpoints' => 0,
            'game2GM' => 38,
            'game2GA' => 78,
            'gameFTM' => 18,
            'gameFTA' => 23,
            'game3GM' => 10,
            'game3GA' => 28,
            'gameORB' => 12,
            'gameDRB' => 33,
            'gameAST' => 22,
            'gameSTL' => 8,
            'gameTOV' => 13,
            'gameBLK' => 5,
            'gamePF' => 18,
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
            'Date' => '2025-02-16',
            'name' => 'Team Away',
            'gameOfThatDay' => 1,
            'visitorTeamID' => 50,
            'homeTeamID' => 51,
            'attendance' => 20000,
            'capacity' => 20000,
            'visitorWins' => 0,
            'visitorLosses' => 0,
            'homeWins' => 0,
            'homeLosses' => 0,
            'visitorQ1points' => 30,
            'visitorQ2points' => 28,
            'visitorQ3points' => 32,
            'visitorQ4points' => 35,
            'visitorOTpoints' => 0,
            'homeQ1points' => 25,
            'homeQ2points' => 30,
            'homeQ3points' => 28,
            'homeQ4points' => 32,
            'homeOTpoints' => 0,
            'game2GM' => 40,
            'game2GA' => 80,
            'gameFTM' => 20,
            'gameFTA' => 25,
            'game3GM' => 12,
            'game3GA' => 30,
            'gameORB' => 15,
            'gameDRB' => 35,
            'gameAST' => 25,
            'gameSTL' => 10,
            'gameTOV' => 15,
            'gameBLK' => 6,
            'gamePF' => 20,
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
        $this->insertTestPlayer(200010030, 'BS NullTid', ['tid' => 1]);
        $this->insertPlayerBoxscoreRow(
            '2025-04-01', 200010030, 'BS NullTid', 'PG', 2, 1, 1
        );

        $result = $this->repo->hasNullTeamIdPlayerBoxscores('2025-04-01', 2, 1);

        self::assertFalse($result);
    }

    public function testHasNullTeamIdReturnsTrueWhenNullExists(): void
    {
        // Create the player first (FK constraint requires it)
        $this->insertTestPlayer(200010031, 'BS NullTidPlr', ['tid' => 1]);

        // Insert a player boxscore with NULL teamID manually
        $this->insertRow('ibl_box_scores', [
            'Date' => '2025-04-02',
            'name' => 'BS NullTidPlr',
            'pos' => 'PG',
            'pid' => 200010031,
            'visitorTID' => 2,
            'homeTID' => 1,
            // teamID intentionally omitted — will be NULL
            'gameMIN' => 30,
            'game2GM' => 5,
            'game2GA' => 10,
            'gameFTM' => 4,
            'gameFTA' => 5,
            'game3GM' => 2,
            'game3GA' => 6,
            'gameORB' => 2,
            'gameDRB' => 6,
            'gameAST' => 5,
            'gameSTL' => 2,
            'gameTOV' => 2,
            'gameBLK' => 1,
            'gamePF' => 3,
            'gameOfThatDay' => 1,
            'attendance' => 10000,
            'capacity' => 15000,
            'visitorWins' => 20,
            'visitorLosses' => 10,
            'homeWins' => 25,
            'homeLosses' => 5,
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
            'Date' => '2025-02-20',
            'name' => 'Team Away',
            'gameOfThatDay' => 1,
            'visitorTeamID' => 50,
            'homeTeamID' => 51,
            'attendance' => 20000, 'capacity' => 20000,
            'visitorWins' => 0, 'visitorLosses' => 0, 'homeWins' => 0, 'homeLosses' => 0,
            'visitorQ1points' => 25, 'visitorQ2points' => 25, 'visitorQ3points' => 25, 'visitorQ4points' => 25, 'visitorOTpoints' => 0,
            'homeQ1points' => 25, 'homeQ2points' => 25, 'homeQ3points' => 25, 'homeQ4points' => 25, 'homeOTpoints' => 0,
            'game2GM' => 30, 'game2GA' => 60, 'gameFTM' => 15, 'gameFTA' => 20,
            'game3GM' => 8, 'game3GA' => 22, 'gameORB' => 10, 'gameDRB' => 30,
            'gameAST' => 20, 'gameSTL' => 8, 'gameTOV' => 12, 'gameBLK' => 5, 'gamePF' => 18,
        ]);

        $rows = $this->repo->findAllStarGamesWithDefaultNames();

        $matching = array_filter(
            $rows,
            static fn (array $r): bool => $r['Date'] === '2025-02-20',
        );

        self::assertNotEmpty($matching);
        $first = array_values($matching)[0];
        self::assertSame('Team Away', $first['name']);
        self::assertSame(50, $first['visitorTeamID']);
    }

    public function testFindAllStarGamesWithDefaultNamesExcludesRenamedTeams(): void
    {
        $this->insertRow('ibl_box_scores_teams', [
            'Date' => '2025-02-21',
            'name' => 'Team LeBron',
            'gameOfThatDay' => 1,
            'visitorTeamID' => 50,
            'homeTeamID' => 51,
            'attendance' => 20000, 'capacity' => 20000,
            'visitorWins' => 0, 'visitorLosses' => 0, 'homeWins' => 0, 'homeLosses' => 0,
            'visitorQ1points' => 25, 'visitorQ2points' => 25, 'visitorQ3points' => 25, 'visitorQ4points' => 25, 'visitorOTpoints' => 0,
            'homeQ1points' => 25, 'homeQ2points' => 25, 'homeQ3points' => 25, 'homeQ4points' => 25, 'homeOTpoints' => 0,
            'game2GM' => 30, 'game2GA' => 60, 'gameFTM' => 15, 'gameFTA' => 20,
            'game3GM' => 8, 'game3GA' => 22, 'gameORB' => 10, 'gameDRB' => 30,
            'gameAST' => 20, 'gameSTL' => 8, 'gameTOV' => 12, 'gameBLK' => 5, 'gamePF' => 18,
        ]);

        $rows = $this->repo->findAllStarGamesWithDefaultNames();

        $matching = array_filter(
            $rows,
            static fn (array $r): bool => $r['Date'] === '2025-02-21',
        );

        self::assertEmpty($matching);
    }

    // ── getPlayersForAllStarTeam ────────────────────────────────

    public function testGetPlayersForAllStarTeamReturnsPlayerNames(): void
    {
        $this->insertTestPlayer(200010032, 'BS AllStar PG', ['tid' => 50]);

        $this->insertRow('ibl_box_scores', [
            'Date' => '2025-02-22',
            'name' => 'BS AllStar PG',
            'pos' => 'PG',
            'pid' => 200010032,
            'visitorTID' => 50,
            'homeTID' => 51,
            'teamID' => 50,
            'gameMIN' => 25, 'game2GM' => 4, 'game2GA' => 8, 'gameFTM' => 2, 'gameFTA' => 3,
            'game3GM' => 1, 'game3GA' => 3, 'gameORB' => 1, 'gameDRB' => 3,
            'gameAST' => 5, 'gameSTL' => 1, 'gameTOV' => 1, 'gameBLK' => 0, 'gamePF' => 2,
            'gameOfThatDay' => 1, 'attendance' => 20000, 'capacity' => 20000,
            'visitorWins' => 0, 'visitorLosses' => 0, 'homeWins' => 0, 'homeLosses' => 0,
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
