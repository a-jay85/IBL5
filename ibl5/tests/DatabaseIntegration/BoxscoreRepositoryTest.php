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

}
