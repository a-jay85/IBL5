<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use LastSimRecap\LastSimRecapRepository;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class LastSimRecapRepositoryTest extends DatabaseTestCase
{
    private LastSimRecapRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new LastSimRecapRepository($this->db);
    }

    public function testGetLastSimWindowReturnsHighestSimRow(): void
    {
        $this->insertRow('ibl_sim_dates', [
            'sim' => 9001,
            'start_date' => '2030-01-01',
            'end_date' => '2030-01-05',
        ]);
        $this->insertRow('ibl_sim_dates', [
            'sim' => 9002,
            'start_date' => '2030-01-06',
            'end_date' => '2030-01-10',
        ]);

        $window = $this->repo->getLastSimWindow();

        self::assertNotNull($window);
        self::assertSame(9002, $window['sim']);
        self::assertSame('2030-01-06', $window['startDate']);
        self::assertSame('2030-01-10', $window['endDate']);
    }

    public function testGetGamesForTeamInWindowReturnsVisitorAndHomeGamesAsc(): void
    {
        // Two games for team 1 in the window — one home, one visitor.
        $this->insertScheduleRow(2030, '2030-03-01', visitorTid: 1, visitorScore: 90, homeTid: 2, homeScore: 100);
        $this->insertScheduleRow(2030, '2030-03-03', visitorTid: 2, visitorScore: 95, homeTid: 1, homeScore: 110);
        // One game outside the window — should be excluded.
        $this->insertScheduleRow(2030, '2030-02-28', visitorTid: 1, visitorScore: 80, homeTid: 3, homeScore: 70);
        // One game inside the window but for a different team.
        $this->insertScheduleRow(2030, '2030-03-02', visitorTid: 3, visitorScore: 80, homeTid: 2, homeScore: 90);

        $games = $this->repo->getGamesForTeamInWindow(1, '2030-03-01', '2030-03-05');

        self::assertCount(2, $games);
        // Ordered asc by date.
        self::assertSame('2030-03-01', $games[0]['date']);
        self::assertSame('2030-03-03', $games[1]['date']);
        self::assertSame(1, $games[0]['visitor']);
        self::assertSame(1, $games[1]['home']);
    }

    public function testGetTeamBoxscoreLinesReturnsQuartersAndPreGameRecords(): void
    {
        $this->insertTeamBoxscoreRow('2030-04-10', 'Visitors', 1, visitorTid: 1, homeTid: 2);

        $lines = $this->repo->getTeamBoxscoreLines(1, 2, '2030-04-10');

        self::assertNotNull($lines);
        self::assertCount(4, $lines['visQ']);
        self::assertCount(4, $lines['homeQ']);
        self::assertSame(20, $lines['visQ'][0]);
        self::assertSame(28, $lines['homeQ'][0]);
        self::assertSame(0, $lines['visOT']);
        self::assertSame(20, $lines['visitorPreWins']);
        self::assertSame(25, $lines['homePreWins']);
    }

    public function testGetTeamBoxscoreLinesReturnsNullWhenAbsent(): void
    {
        $lines = $this->repo->getTeamBoxscoreLines(999, 998, '2030-04-10');
        self::assertNull($lines);
    }

    public function testGetActiveInjuriesForPlayersReturnsCurrentlyActiveOnly(): void
    {
        // Active injury: occurred on game date.
        $this->insertTestPlayer(800001, 'Hurt Player', ['teamid' => 1]);
        $this->insertRow('ibl_jsb_transactions', [
            'season_year' => 2030,
            'transaction_month' => 4,
            'transaction_day' => 10,
            'transaction_type' => 1,
            'pid' => 800001,
            'player_name' => 'Hurt Player',
            'from_teamid' => 1,
            'to_teamid' => 0,
            'injury_games_missed' => 7,
            'injury_description' => 'Sprained ankle',
            'is_draft_pick' => 0,
        ]);

        // Expired injury: more than `injury_games_missed` days ago.
        $this->insertTestPlayer(800002, 'Healed Player', ['teamid' => 1]);
        $this->insertRow('ibl_jsb_transactions', [
            'season_year' => 2030,
            'transaction_month' => 3,
            'transaction_day' => 1,
            'transaction_type' => 1,
            'pid' => 800002,
            'player_name' => 'Healed Player',
            'from_teamid' => 1,
            'to_teamid' => 0,
            'injury_games_missed' => 3,
            'injury_description' => 'Bruised foot',
            'is_draft_pick' => 0,
        ]);

        $injuries = $this->repo->getActiveInjuriesForPlayers([800001, 800002], '2030-04-10');

        self::assertCount(1, $injuries);
        self::assertSame(800001, $injuries[0]['pid']);
        self::assertTrue($injuries[0]['isNew']);
        self::assertSame('Sprained ankle', $injuries[0]['injuryDescription']);
    }

    public function testGetActiveInjuriesIsNewOnlyOnSameDay(): void
    {
        $this->insertTestPlayer(800010, 'P10', ['teamid' => 1]);
        $this->insertRow('ibl_jsb_transactions', [
            'season_year' => 2030,
            'transaction_month' => 4,
            'transaction_day' => 5,
            'transaction_type' => 1,
            'pid' => 800010,
            'player_name' => 'P10',
            'from_teamid' => 1,
            'to_teamid' => 0,
            'injury_games_missed' => 14,
            'injury_description' => 'Strain',
            'is_draft_pick' => 0,
        ]);

        $injuries = $this->repo->getActiveInjuriesForPlayers([800010], '2030-04-10');

        self::assertCount(1, $injuries);
        self::assertFalse($injuries[0]['isNew']);
    }

    public function testGetStarterPidsFromBoxScoresPicksTopMinutes(): void
    {
        $schedId = $this->insertScheduleRow(2030, '2030-05-01', visitorTid: 1, visitorScore: 100, homeTid: 2, homeScore: 90);

        $this->insertTestPlayer(900001, 'Starter PG', ['teamid' => 1, 'pos' => 'PG']);
        $this->insertTestPlayer(900002, 'Bench PG',   ['teamid' => 1, 'pos' => 'PG']);
        $this->insertTestPlayer(900003, 'Starter SG', ['teamid' => 1, 'pos' => 'SG']);

        $this->insertPlayerBoxscoreRow('2030-05-01', 900001, 'Starter PG', 'PG', visitorTid: 1, homeTid: 2, teamId: 1, minutes: 36);
        $this->insertPlayerBoxscoreRow('2030-05-01', 900002, 'Bench PG',   'PG', visitorTid: 1, homeTid: 2, teamId: 1, minutes: 12);
        $this->insertPlayerBoxscoreRow('2030-05-01', 900003, 'Starter SG', 'SG', visitorTid: 1, homeTid: 2, teamId: 1, minutes: 30);
        // DNP — should be excluded.
        $this->insertTestPlayer(900004, 'DNP SF', ['teamid' => 1, 'pos' => 'SF']);
        $this->insertPlayerBoxscoreRow('2030-05-01', 900004, 'DNP SF', 'SF', visitorTid: 1, homeTid: 2, teamId: 1, minutes: 0);

        $starters = $this->repo->getStarterPidsFromBoxScores($schedId, 1);

        self::assertSame(900001, $starters['PG']);
        self::assertSame(900003, $starters['SG']);
        self::assertSame(0, $starters['SF']);
    }

    public function testGetTeamRecordAsOfCountsWinsAndLosses(): void
    {
        // Use teamid=5 which has no games in db-seed.sql.
        $this->insertScheduleRow(2030, '2030-03-01', visitorTid: 5, visitorScore: 100, homeTid: 6, homeScore: 90); // T5 win (visitor)
        $this->insertScheduleRow(2030, '2030-03-02', visitorTid: 6, visitorScore: 110, homeTid: 5, homeScore: 95); // T5 loss (home)
        $this->insertScheduleRow(2030, '2030-03-03', visitorTid: 7, visitorScore: 80,  homeTid: 5, homeScore: 100); // T5 win (home)
        // Future game — should not count.
        $this->insertScheduleRow(2030, '2030-03-10', visitorTid: 5, visitorScore: 0, homeTid: 8, homeScore: 0);

        $record = $this->repo->getTeamRecordAsOf(5, '2030-03-03');

        self::assertSame(2, $record['wins']);
        self::assertSame(1, $record['losses']);
    }
}
