<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use GameBoxscore\GameBoxscoreRepository;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class GameBoxscoreRepositoryTest extends DatabaseTestCase
{
    private GameBoxscoreRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new GameBoxscoreRepository($this->db);
    }

    public function testGetGameInfoComputesScoresAndTeamColors(): void
    {
        $this->insertTeamBoxscoreRow('2099-03-01', 'Metros', 1, 1, 2);

        $result = $this->repo->getGameInfo('2099-03-01', 1);

        self::assertNotNull($result);
        // Away quarters: 20+22+18+25 = 85
        self::assertSame(85, $result['awayScore']);
        // Home quarters: 28+24+22+30 = 104
        self::assertSame(104, $result['homeScore']);
        self::assertSame(1, $result['awayTeamId']);
        self::assertSame(2, $result['homeTeamId']);
        self::assertArrayHasKey('awayColor1', $result);
        self::assertArrayHasKey('homeColor1', $result);
    }

    public function testGetGameInfoReturnsNullForUnknownGame(): void
    {
        $this->insertTeamBoxscoreRow('2099-03-01', 'Metros', 1, 1, 2);

        self::assertNull($this->repo->getGameInfo('2099-03-01', 7));
        self::assertNull($this->repo->getGameInfo('1901-01-01', 1));
    }

    public function testGetGameInfoFallsBackToEachSideCalcPointsWhenQuartersNull(): void
    {
        $this->seedNullQuarterGame('2099-04-01', 3, 1, 2, 45, 59);

        $result = $this->repo->getGameInfo('2099-04-01', 3);

        self::assertNotNull($result);
        self::assertSame(108, $result['awayScore']);
        self::assertSame(136, $result['homeScore']);
        self::assertSame(1, $result['awayTeamId']);
        self::assertSame(2, $result['homeTeamId']);
    }

    public function testGetGameInfoPrefersQuarterSumOverCalcPoints(): void
    {
        // calc_points are 138 (visitor) and 78 (home); the quarters say 85 and 104
        $this->seedRealQuarterGameWithMisleadingCalcPoints('2099-04-02', 1, 1, 2);

        $result = $this->repo->getGameInfo('2099-04-02', 1);

        self::assertNotNull($result);
        self::assertSame(85, $result['awayScore']);
        self::assertSame(104, $result['homeScore']);
    }

    public function testGetGameInfoFallbackStaysInsideTheGameWhenTwoGamesShareGameOfDay(): void
    {
        $this->seedNullQuarterGame('2099-04-03', 2, 1, 2, 45, 59);

        // A second game under the same date and game number, with higher row ids and calc_points 158
        $quarters = [
            'visitor_q1_points' => 20,
            'visitor_q2_points' => 20,
            'visitor_q3_points' => 20,
            'visitor_q4_points' => 20,
            'visitor_ot_points' => 20,
            'home_q1_points' => 20,
            'home_q2_points' => 20,
            'home_q3_points' => 20,
            'home_q4_points' => 20,
            'home_ot_points' => 20,
        ];
        $common = $this->commonColumns('2099-04-03', 2, 3, 1) + $quarters;
        $this->insertRow('ibl_box_scores_teams', $common + ['name' => 'NQ-V2', 'game_2gm' => 70]);
        $this->insertRow('ibl_box_scores_teams', $common + ['name' => 'NQ-H2', 'game_2gm' => 70]);

        $result = $this->repo->getGameInfo('2099-04-03', 2);

        self::assertNotNull($result);
        self::assertSame(1, $result['awayTeamId']);
        self::assertSame(108, $result['awayScore']);
        self::assertSame(136, $result['homeScore']);
    }

    public function testGetGameInfoTreatsDateAsBoundValue(): void
    {
        $this->seedNullQuarterGame('2099-04-04', 3, 1, 2, 45, 59);

        self::assertNull($this->repo->getGameInfo("x' OR '1'='1", 3));
    }

    public function testGetPlayerRowsSplitsTeamsAndFlagsAwaySide(): void
    {
        $this->insertTestPlayer(200090601, 'GB Away One');
        $this->insertTestPlayer(200090602, 'GB Home One', ['ordinal' => 2]);

        $this->insertPlayerBoxscoreRow('2099-03-01', 200090601, 'GB Away One', 'PG', 1, 2, 1);
        $this->insertPlayerBoxscoreRow('2099-03-01', 200090602, 'GB Home One', 'SG', 1, 2, 2);

        $result = $this->repo->getPlayerRows('2099-03-01', 1, 1, 2);

        self::assertCount(2, $result);

        $byTeam = [];
        foreach ($result as $row) {
            $byTeam[(int) $row['teamid']] = $row;
        }

        self::assertSame(1, $byTeam[1]['isAwayPlayer']);
        self::assertSame(0, $byTeam[2]['isAwayPlayer']);

        // Defaults from insertPlayerBoxscoreRow: pts 5*2+4+2*3=20, reb 2+6=8, fgm 5+2=7
        self::assertSame(20, $byTeam[1]['pts']);
        self::assertSame(8, $byTeam[1]['reb']);
        self::assertSame(7, $byTeam[1]['fgm']);
        self::assertSame(20, $byTeam[2]['pts']);
        self::assertSame(8, $byTeam[2]['reb']);
        self::assertSame(7, $byTeam[2]['fgm']);
    }

    public function testGetPlayerRowsExcludesOtherTeamsAndEmptyGames(): void
    {
        $this->insertTestPlayer(200090603, 'GB Away Two');
        $this->insertTestPlayer(200090604, 'GB Home Two', ['ordinal' => 2]);
        $this->insertTestPlayer(200090605, 'GB Other One', ['ordinal' => 3]);

        $this->insertPlayerBoxscoreRow('2099-03-01', 200090603, 'GB Away Two', 'PG', 1, 2, 1);
        $this->insertPlayerBoxscoreRow('2099-03-01', 200090604, 'GB Home Two', 'SG', 1, 2, 2);
        $this->insertPlayerBoxscoreRow('2099-03-01', 200090605, 'GB Other One', 'SF', 1, 2, 3);

        $result = $this->repo->getPlayerRows('2099-03-01', 1, 1, 2);

        $pids = array_map(static fn (array $row): int => (int) $row['pid'], $result);
        self::assertNotContains(200090605, $pids);
        self::assertContains(200090603, $pids);
        self::assertContains(200090604, $pids);

        self::assertSame([], $this->repo->getPlayerRows('2099-03-01', 9, 1, 2));
    }

    /**
     * Two rows for one game with every quarter and OT column NULL. calc_points is
     * game_2gm * 2 + 12 + 2 * 3, so 45 gives 108 and 59 gives 136.
     *
     * @return array{0: int, 1: int} visitor row id, home row id
     */
    private function seedNullQuarterGame(
        string $date,
        int $gameOfDay,
        int $visitorTid,
        int $homeTid,
        int $visitor2gm,
        int $home2gm,
    ): array {
        // Quarter keys are omitted on purpose: the columns default to NULL, and
        // insertRow() binds a PHP null as '', which strict mode rejects.
        $common = $this->commonColumns($date, $gameOfDay, $visitorTid, $homeTid);
        $visitorId = $this->insertRow('ibl_box_scores_teams', $common + ['name' => 'NQ-V', 'game_2gm' => $visitor2gm]);
        $homeId = $this->insertRow('ibl_box_scores_teams', $common + ['name' => 'NQ-H', 'game_2gm' => $home2gm]);
        self::assertLessThan($homeId, $visitorId, 'visitor row must carry the lower id');

        return [$visitorId, $homeId];
    }

    /**
     * Quarters say home 104 beats visitor 85. calc_points says visitor 138 beats home 78.
     */
    private function seedRealQuarterGameWithMisleadingCalcPoints(
        string $date,
        int $gameOfDay,
        int $visitorTid,
        int $homeTid,
    ): void {
        $quarters = [
            'visitor_q1_points' => 20,
            'visitor_q2_points' => 22,
            'visitor_q3_points' => 18,
            'visitor_q4_points' => 25,
            'visitor_ot_points' => 0,
            'home_q1_points' => 28,
            'home_q2_points' => 24,
            'home_q3_points' => 22,
            'home_q4_points' => 30,
            'home_ot_points' => 0,
        ];
        $common = $this->commonColumns($date, $gameOfDay, $visitorTid, $homeTid) + $quarters;
        $visitorId = $this->insertRow('ibl_box_scores_teams', $common + ['name' => 'NQ-V', 'game_2gm' => 60]);
        $homeId = $this->insertRow('ibl_box_scores_teams', $common + ['name' => 'NQ-H', 'game_2gm' => 30]);
        self::assertLessThan($homeId, $visitorId, 'visitor row must carry the lower id');
    }

    /**
     * @return array<string, int|string>
     */
    private function commonColumns(string $date, int $gameOfDay, int $visitorTid, int $homeTid): array
    {
        return [
            'game_date' => $date,
            'game_of_that_day' => $gameOfDay,
            'visitor_teamid' => $visitorTid,
            'home_teamid' => $homeTid,
            'attendance' => 10000,
            'capacity' => 15000,
            'visitor_wins' => 0,
            'visitor_losses' => 0,
            'home_wins' => 0,
            'home_losses' => 0,
            'game_ftm' => 12,
            'game_fta' => 16,
            'game_3gm' => 2,
            'game_3ga' => 8,
            'game_2ga' => 80,
            'game_orb' => 10,
            'game_drb' => 30,
            'game_ast' => 20,
            'game_stl' => 8,
            'game_tov' => 12,
            'game_blk' => 5,
            'game_pf' => 18,
        ];
    }
}
