<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use Boxscore\AllStarTeamRepository;

#[Group('database')]
class AllStarTeamRepositoryTest extends DatabaseTestCase
{
    private AllStarTeamRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new AllStarTeamRepository($this->db);
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
}
