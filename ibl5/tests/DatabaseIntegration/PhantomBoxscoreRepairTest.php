<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Boxscore\BoxscoreRepository;
use Boxscore\PhantomBoxscoreRepair;
use PHPUnit\Framework\Attributes\Group;

/**
 * Behavioural cover for the season-2008 phantom boxscore repair.
 *
 * The counts asserted here are the FIXTURE's, not production's. This suite builds its
 * database from `migrations/` + `Fixtures/db-seed.sql`, and that seed carries zero
 * `ibl_box_scores*` rows, so the production snapshot's 618/1236/3/6/14502/20 is
 * unreachable in this harness by construction. Every test therefore seeds a small
 * synthetic season 2008 that reproduces the same SHAPE — orphans, one duplicated
 * triple whose score-matched survivor is not the lowest `game_of_that_day`, exempt and
 * unscheduled triples that must be skipped, and scheduled games that must survive —
 * and passes the matching expectations through the constructor's test-only
 * `$expectedOverride` seam. The production figures are verified against the real
 * snapshot by `bin/check-boxscore-schedule` and the migration's dry-run report, not here.
 *
 * `season_year` is GENERATED on both boxscore tables (`month >= 10 ? year + 1 : year`),
 * so April 2008 dates land in season 2008 without being inserted directly. On
 * `ibl_schedule` and `ibl_sim_game_recaps` it is a plain column and is bound explicitly.
 */
#[Group('database')]
final class PhantomBoxscoreRepairTest extends DatabaseTestCase
{
    private const SEASON = 2008;

    /** The sim this fixture's recaps belong to; ibl_sim_game_recaps.sim is FK-constrained to ibl_sim_summaries. */
    private const SIM = 720;

    /**
     * Fixture-scaled counterpart of PhantomBoxscoreRepair::EXPECTED, measured off
     * seedBaseFixture(): 2 orphan games (4 team rows), 1 duplicated triple losing
     * 1 copy (2 team rows), 5 dependent player rows and 3 dependent recaps.
     */
    private const FIXTURE_EXPECTED = [
        'orphan_games' => 2,
        'orphan_team_rows' => 4,
        'duplicate_triple_games' => 1,
        'duplicate_team_rows' => 2,
        'player_rows' => 5,
        'recap_rows' => 3,
    ];

    private BoxscoreRepository $repository;

    protected function setUp(): void
    {
        // DatabaseTestCase::setUp() already opens the transaction that tearDown()
        // rolls back. Do not open another - mysqli has no nested transactions, and
        // an inner begin_transaction() would implicitly COMMIT the outer one and
        // leak this fixture into the shared test database.
        parent::setUp();

        $this->repository = new BoxscoreRepository($this->db);
    }

    /**
     * @param array{orphan_games: int, orphan_team_rows: int, duplicate_triple_games: int, duplicate_team_rows: int, player_rows: int, recap_rows: int}|null $expectedOverride
     */
    private function makeRepair(?array $expectedOverride = self::FIXTURE_EXPECTED): PhantomBoxscoreRepair
    {
        // manageTransaction: false - the surrounding test transaction owns the commit
        // boundary, so the repair must not open one of its own.
        return new PhantomBoxscoreRepair($this->db, $this->repository, false, $expectedOverride);
    }

    private function seedSchedule(string $date, int $visitor, int $home, int $visitorScore, int $homeScore): void
    {
        $this->insertRow('ibl_schedule', [
            'season_year' => self::SEASON,
            'box_id' => 0,
            'game_date' => $date,
            'visitor_teamid' => $visitor,
            'visitor_score' => $visitorScore,
            'home_teamid' => $home,
            'home_score' => $homeScore,
        ]);
    }

    /**
     * Both sides of one boxscore game. Quarter points are split so the reconstructed
     * final score is exactly ($visitorScore, $homeScore) - that is the value
     * SCORE_MATCHED_COPIES compares against ibl_schedule.
     */
    private function seedTeamRows(
        string $date,
        int $visitor,
        int $home,
        int $gotd,
        int $visitorScore,
        int $homeScore
    ): void {
        foreach (['V' . $visitor, 'H' . $home] as $name) {
            $this->insertRow('ibl_box_scores_teams', [
                'game_date' => $date,
                'name' => $name,
                'game_of_that_day' => $gotd,
                'visitor_teamid' => $visitor,
                'home_teamid' => $home,
                'attendance' => 15000,
                'capacity' => 20000,
                'visitor_wins' => 1,
                'visitor_losses' => 1,
                'home_wins' => 1,
                'home_losses' => 1,
                'visitor_q1_points' => $visitorScore - 60,
                'visitor_q2_points' => 20,
                'visitor_q3_points' => 20,
                'visitor_q4_points' => 20,
                'visitor_ot_points' => 0,
                'home_q1_points' => $homeScore - 60,
                'home_q2_points' => 20,
                'home_q3_points' => 20,
                'home_q4_points' => 20,
                'home_ot_points' => 0,
                'game_min' => 240,
                'game_2gm' => 30,
                'game_2ga' => 60,
                'game_ftm' => 15,
                'game_fta' => 20,
                'game_3gm' => 8,
                'game_3ga' => 20,
                'game_orb' => 10,
                'game_drb' => 30,
                'game_ast' => 20,
                'game_stl' => 7,
                'game_tov' => 12,
                'game_blk' => 4,
                'game_pf' => 18,
            ]);
        }
    }

    private function seedPlayerRow(string $date, int $visitor, int $home, int $gotd, int $pid): void
    {
        // ibl_box_scores.pid is FK-constrained to ibl_plr.pid, so the player has to
        // exist before its boxscore line does.
        $this->insertTestPlayer($pid, 'P' . $pid, ['teamid' => $visitor]);

        $this->insertRow('ibl_box_scores', [
            'game_date' => $date,
            'name' => 'P' . $pid,
            'pos' => 'PG',
            'pid' => $pid,
            'teamid' => $visitor,
            'visitor_teamid' => $visitor,
            'home_teamid' => $home,
            'game_of_that_day' => $gotd,
            'game_min' => 30,
            'game_2gm' => 5,
            'game_2ga' => 10,
            'game_ftm' => 2,
            'game_fta' => 2,
            'game_3gm' => 1,
            'game_3ga' => 3,
            'game_orb' => 1,
            'game_drb' => 4,
            'game_ast' => 5,
            'game_stl' => 1,
            'game_tov' => 2,
            'game_blk' => 0,
            'game_pf' => 3,
        ]);
    }

    private function seedRecap(string $date, int $visitor, int $home, int $gotd, int $sortOrder): void
    {
        // db-seed.sql carries no ibl_sim_summaries rows, so the FK parent has to be
        // created before the first recap. INSERT IGNORE keeps repeat calls cheap.
        $this->db->query('INSERT IGNORE INTO `ibl_sim_summaries` (`sim`, `status`) VALUES (' . self::SIM . ", 'done')");

        $this->insertRow('ibl_sim_game_recaps', [
            'sim' => self::SIM,
            'season_year' => self::SEASON,
            'game_date' => $date,
            'visitor_teamid' => $visitor,
            'home_teamid' => $home,
            'game_of_that_day' => $gotd,
            'sort_order' => $sortOrder,
            'recap_text' => 'recap ' . $date . ' ' . $visitor . '@' . $home . ' g' . $gotd,
        ]);
    }

    /**
     * The shape the repair is built for, at fixture scale:
     *
     *  - set A: two orphan games (no ibl_schedule row), non-exempt teams, April.
     *  - set B: one scheduled triple with copies at gotd 1 and 3 where ONLY gotd 3
     *           reconstructs the scheduled 100-90 final, so the survivor is the
     *           higher gotd and the loser (gotd 1) is the phantom.
     *  - control: one ordinary scheduled game that must be untouched.
     */
    private function seedBaseFixture(): void
    {
        // Set A - orphans.
        $this->seedTeamRows('2008-04-01', 1, 2, 1, 100, 90);
        $this->seedPlayerRow('2008-04-01', 1, 2, 1, 900001);
        $this->seedPlayerRow('2008-04-01', 1, 2, 1, 900002);
        $this->seedRecap('2008-04-01', 1, 2, 1, 1);

        $this->seedTeamRows('2008-04-02', 3, 4, 1, 100, 90);
        $this->seedPlayerRow('2008-04-02', 3, 4, 1, 900003);
        $this->seedRecap('2008-04-02', 3, 4, 1, 2);

        // Set B - scheduled duplicate; gotd 3 matches the schedule, gotd 1 does not.
        $this->seedSchedule('2008-04-05', 5, 6, 100, 90);
        $this->seedTeamRows('2008-04-05', 5, 6, 1, 99, 90);
        $this->seedPlayerRow('2008-04-05', 5, 6, 1, 900004);
        $this->seedPlayerRow('2008-04-05', 5, 6, 1, 900005);
        $this->seedRecap('2008-04-05', 5, 6, 1, 3);

        $this->seedTeamRows('2008-04-05', 5, 6, 3, 100, 90);
        $this->seedPlayerRow('2008-04-05', 5, 6, 3, 900006);
        $this->seedRecap('2008-04-05', 5, 6, 3, 4);

        // Control - an ordinary scheduled game with a single copy.
        $this->seedSchedule('2008-04-10', 7, 8, 100, 90);
        $this->seedTeamRows('2008-04-10', 7, 8, 1, 100, 90);
        $this->seedPlayerRow('2008-04-10', 7, 8, 1, 900007);
        $this->seedRecap('2008-04-10', 7, 8, 1, 5);
    }

    private function scalar(string $sql): int
    {
        $result = $this->db->query($sql);
        self::assertNotFalse($result, 'Query failed: ' . $sql . ' - ' . $this->db->error);
        /** @var array<string, float|int|string|null> $row */
        $row = $result->fetch_assoc();

        return (int) $row['c'];
    }

    /** @return array{teams: int, players: int, recaps: int} */
    private function liveCounts(): array
    {
        return [
            'teams' => $this->scalar(
                'SELECT COUNT(*) AS c FROM `ibl_box_scores_teams` WHERE `season_year` = ' . self::SEASON
            ),
            'players' => $this->scalar(
                'SELECT COUNT(*) AS c FROM `ibl_box_scores` WHERE `season_year` = ' . self::SEASON
            ),
            'recaps' => $this->scalar(
                'SELECT COUNT(*) AS c FROM `ibl_sim_game_recaps` WHERE `season_year` = ' . self::SEASON
            ),
        ];
    }

    /** @return array{teams: int, players: int, recaps: int} */
    private function backupCounts(): array
    {
        return [
            'teams' => $this->scalar('SELECT COUNT(*) AS c FROM `ibl_box_scores_teams_phantom_backup`'),
            'players' => $this->scalar('SELECT COUNT(*) AS c FROM `ibl_box_scores_phantom_backup`'),
            'recaps' => $this->scalar('SELECT COUNT(*) AS c FROM `ibl_sim_game_recaps_phantom_backup`'),
        ];
    }

    public function testDryRunChangesNothing(): void
    {
        $this->seedBaseFixture();
        $before = $this->liveCounts();

        $repair = $this->makeRepair();
        $keys = $repair->findPhantomTeamRows(self::SEASON);
        $counts = $repair->countAffectedRows(self::SEASON);

        self::assertCount(3, $keys, 'Two orphan games plus one duplicate loser.');
        self::assertSame(self::FIXTURE_EXPECTED, $counts);
        self::assertSame($before, $this->liveCounts(), 'The read path must not delete anything.');
        self::assertSame(
            ['teams' => 0, 'players' => 0, 'recaps' => 0],
            $this->backupCounts(),
            'The read path must not fill the backup tables.'
        );
    }

    public function testPreconditionProceedOnExpectedCounts(): void
    {
        $this->seedBaseFixture();

        self::assertSame('proceed', $this->makeRepair()->assertPreconditions(self::SEASON));
    }

    public function testPreconditionNoopOnAlreadyRepaired(): void
    {
        $this->seedBaseFixture();
        $repair = $this->makeRepair();
        $repair->deletePhantomRows(self::SEASON);

        self::assertSame(
            'noop',
            $repair->assertPreconditions(self::SEASON),
            'Once every count is zero the gate must report noop, never throw.'
        );
    }

    public function testPreconditionThrowsOnCountMismatch(): void
    {
        $this->seedBaseFixture();

        $wrong = self::FIXTURE_EXPECTED;
        $wrong['player_rows'] = self::FIXTURE_EXPECTED['player_rows'] + 1;

        try {
            $this->makeRepair($wrong)->assertPreconditions(self::SEASON);
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('precondition failed', $e->getMessage());
        }
    }

    public function testDuplicateSurvivorAbortOnNoScoreMatch(): void
    {
        // A scheduled triple with two copies, neither of which reconstructs the
        // scheduled final. The survivor is undecidable, so the whole repair aborts.
        $this->seedSchedule('2008-04-06', 9, 10, 100, 90);
        $this->seedTeamRows('2008-04-06', 9, 10, 1, 98, 90);
        $this->seedTeamRows('2008-04-06', 9, 10, 2, 97, 90);

        try {
            $this->makeRepair()->findPhantomTeamRows(self::SEASON);
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('expected exactly 1', $e->getMessage());
        }
    }

    public function testDuplicateSurvivorKeepsScoreMatchedCopyNotLowestGotd(): void
    {
        $this->seedBaseFixture();

        $repair = $this->makeRepair();
        $keys = $repair->findPhantomTeamRows(self::SEASON);

        $duplicates = array_values(array_filter(
            $keys,
            static fn (array $key): bool => $key['source'] === 'duplicate'
        ));

        self::assertCount(1, $duplicates);
        self::assertSame('2008-04-05', $duplicates[0]['game_date']);
        self::assertSame(1, $duplicates[0]['game_of_that_day'], 'The loser is gotd 1, not the survivor.');

        $resolutions = $repair->describeDuplicateResolutions();
        self::assertCount(1, $resolutions);
        self::assertSame(3, $resolutions[0]['keeper_gotd']);
        self::assertSame([1, 3], $resolutions[0]['candidate_gotds']);
        self::assertNotSame(
            min($resolutions[0]['candidate_gotds']),
            $resolutions[0]['keeper_gotd'],
            'The survivor is chosen by score match, never by lowest game_of_that_day.'
        );
    }

    public function testDuplicateSurvivorSkipsExemptTriple(): void
    {
        // teamid 40 is in ScheduleMembershipGuard::EXEMPT_TEAMIDS, so this duplicated
        // triple is skipped by set B and excluded from set A - nothing is deleted.
        $this->seedSchedule('2008-04-12', 40, 9, 100, 90);
        $this->seedTeamRows('2008-04-12', 40, 9, 1, 99, 90);
        $this->seedTeamRows('2008-04-12', 40, 9, 2, 100, 90);

        $keys = $this->makeRepair()->findPhantomTeamRows(self::SEASON);

        self::assertSame([], $keys, 'An exempt triple is never a phantom, in either set.');
    }

    public function testDuplicateSurvivorSkipsUnscheduledTripleLeftToSetA(): void
    {
        // Two copies with no ibl_schedule row at all. Set B skips them (nothing to
        // score-match against); set A claims both, because each is an orphan game.
        $this->seedTeamRows('2008-04-20', 11, 12, 1, 100, 90);
        $this->seedTeamRows('2008-04-20', 11, 12, 2, 100, 90);

        $keys = $this->makeRepair()->findPhantomTeamRows(self::SEASON);

        self::assertCount(2, $keys);
        foreach ($keys as $key) {
            self::assertSame('orphan', $key['source'], 'An unscheduled triple belongs to set A only.');
            self::assertSame('2008-04-20', $key['game_date']);
        }
        self::assertSame([1, 2], array_map(
            static fn (array $key): int => $key['game_of_that_day'],
            $keys
        ));
    }

    public function testPhantomSetsAreDisjoint(): void
    {
        $this->seedBaseFixture();
        // Extra shapes that have historically been the overlap risk: an unscheduled
        // duplicated triple (set A only) and an exempt duplicated triple (neither set).
        $this->seedTeamRows('2008-04-20', 11, 12, 1, 100, 90);
        $this->seedTeamRows('2008-04-20', 11, 12, 2, 100, 90);
        $this->seedSchedule('2008-04-12', 40, 9, 100, 90);
        $this->seedTeamRows('2008-04-12', 40, 9, 1, 99, 90);
        $this->seedTeamRows('2008-04-12', 40, 9, 2, 100, 90);

        // findPhantomTeamRows() throws from assertDisjoint() on any overlap; the
        // explicit uniqueness assertion below documents the property it guards.
        $keys = $this->makeRepair(null)->findPhantomTeamRows(self::SEASON);

        $seen = array_map(
            static fn (array $key): string => $key['game_date'] . '|' . $key['visitor_teamid']
                . '|' . $key['home_teamid'] . '|' . $key['game_of_that_day'],
            $keys
        );

        self::assertCount(
            count($seen),
            array_unique($seen),
            'No identity key may appear in both set A and set B.'
        );
    }

    public function testDeletePhantomRowsFullRun(): void
    {
        $this->seedBaseFixture();
        $before = $this->liveCounts();

        $result = $this->makeRepair()->deletePhantomRows(self::SEASON);

        self::assertSame('repaired', $result['state']);
        self::assertSame(
            self::FIXTURE_EXPECTED['orphan_team_rows'] + self::FIXTURE_EXPECTED['duplicate_team_rows'],
            $result['team_rows']
        );
        self::assertSame(self::FIXTURE_EXPECTED['player_rows'], $result['player_rows']);
        self::assertSame(self::FIXTURE_EXPECTED['recap_rows'], $result['recap_rows']);

        $after = $this->liveCounts();
        self::assertSame($before['teams'] - $result['team_rows'], $after['teams']);
        self::assertSame($before['players'] - $result['player_rows'], $after['players']);
        self::assertSame($before['recaps'] - $result['recap_rows'], $after['recaps']);
    }

    public function testDeleteIdempotent(): void
    {
        $this->seedBaseFixture();
        $repair = $this->makeRepair();

        $first = $repair->deletePhantomRows(self::SEASON);
        self::assertSame('repaired', $first['state']);

        $after = $this->liveCounts();
        $second = $repair->deletePhantomRows(self::SEASON);

        self::assertSame(
            ['state' => 'noop', 'team_rows' => 0, 'player_rows' => 0, 'recap_rows' => 0],
            $second
        );
        self::assertSame($after, $this->liveCounts(), 'A second run must delete nothing.');
    }

    public function testBackupTablesPopulated(): void
    {
        $this->seedBaseFixture();

        $result = $this->makeRepair()->deletePhantomRows(self::SEASON);

        self::assertSame(
            [
                'teams' => $result['team_rows'],
                'players' => $result['player_rows'],
                'recaps' => $result['recap_rows'],
            ],
            $this->backupCounts(),
            'Every deleted row must be captured in its backup table first.'
        );

        self::assertSame(
            2,
            $this->scalar(
                'SELECT COUNT(*) AS c FROM `ibl_box_scores_teams_phantom_backup` '
                . "WHERE `game_date` = '2008-04-05' AND `game_of_that_day` = 1"
            ),
            'The duplicate loser is backed up.'
        );
        self::assertSame(
            0,
            $this->scalar(
                'SELECT COUNT(*) AS c FROM `ibl_box_scores_teams_phantom_backup` '
                . "WHERE `game_date` = '2008-04-05' AND `game_of_that_day` = 3"
            ),
            'The score-matched survivor is never backed up, because it is never deleted.'
        );
    }

    public function testScheduledGamesUntouched(): void
    {
        $this->seedBaseFixture();

        $this->makeRepair()->deletePhantomRows(self::SEASON);

        self::assertSame(
            2,
            $this->scalar(
                'SELECT COUNT(*) AS c FROM `ibl_box_scores_teams` '
                . "WHERE `game_date` = '2008-04-10' AND `visitor_teamid` = 7 AND `home_teamid` = 8"
            ),
            'The ordinary scheduled game keeps both team rows.'
        );
        self::assertSame(
            1,
            $this->scalar(
                'SELECT COUNT(*) AS c FROM `ibl_box_scores` '
                . "WHERE `game_date` = '2008-04-10' AND `visitor_teamid` = 7 AND `home_teamid` = 8"
            ),
            'Its player rows survive.'
        );
        self::assertSame(
            1,
            $this->scalar(
                'SELECT COUNT(*) AS c FROM `ibl_sim_game_recaps` '
                . "WHERE `game_date` = '2008-04-10' AND `visitor_teamid` = 7 AND `home_teamid` = 8"
            ),
            'Its recap survives.'
        );
        self::assertSame(
            2,
            $this->scalar(
                'SELECT COUNT(*) AS c FROM `ibl_box_scores_teams` '
                . "WHERE `game_date` = '2008-04-05' AND `game_of_that_day` = 3"
            ),
            'The surviving copy of the duplicated triple is left in place.'
        );
    }
}
