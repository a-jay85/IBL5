<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\SimRecap;

use JsbParser\TrnFileParser;
use PHPUnit\Framework\Attributes\Group;
use Season\SeasonQueryRepository;
use SimRecap\RecapDocument;
use SimRecap\SimRecapContextRepository;
use SimRecap\SimSummaryRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Updater\Steps\QueueSimSummaryStep;

/**
 * Runs the sim-recap pipeline against HEAT-phase (October) data.
 *
 * Stages covered: the producer gate (QueueSimSummaryStep), the agent's context
 * read (SimRecapContextRepository::buildContext), the store step (resolveBoxId
 * then markDone, mirroring scripts/storeSimRecap.php), and the read side
 * (findDisplayableGameRecaps, findOrphanedGameRecaps, RecapDocument::assemble).
 *
 * HEAT is October 2025 here, so ibl_box_scores_teams.game_type and .season_year
 * (GENERATED columns, migrations/000_baseline_schema.sql:309-310) resolve to 3
 * and 2026: the date year and the season year differ. The fixture-honesty tests
 * pin that, so a later date edit cannot turn this into a Regular Season test.
 *
 * Isolation: transaction rollback (inherited from DatabaseTestCase). Sim 9101
 * and team ids 999101..999104 sit outside the seed band and outside the 9001 /
 * 999001+ ids used by sibling SimRecap tests.
 */
#[Group('database')]
final class HeatPhaseRecapPipelineDbTest extends DatabaseTestCase
{
    private const HEAT_SIM = 9101;
    private const HEAT_START = '2025-10-15';
    private const HEAT_END = '2025-10-21';
    private const HEAT_SEASON_YEAR = 2026;   // generated season_year for an Oct-2025 date
    private const GAME_TYPE_HEAT = 3;        // generated game_type for month 10
    private const GAME_TYPE_REGULAR = 1;     // generated game_type for Nov..May
    private const TEAM_A = 999101;
    private const TEAM_B = 999102;
    private const TEAM_C = 999103;
    private const TEAM_D = 999104;

    /** @var array<int, string> */
    private const TEAM_NAMES = [
        self::TEAM_A => 'Heat Alphas',
        self::TEAM_B => 'Heat Bravos',
        self::TEAM_C => 'Heat Charlies',
        self::TEAM_D => 'Heat Deltas',
    ];

    private SimSummaryRepository $repo;
    private SeasonQueryRepository $seasonQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new SimSummaryRepository($this->db);
        $this->seasonQuery = new SeasonQueryRepository($this->db);

        // Pin the phase explicitly rather than inheriting ambient fixture state.
        $this->setSeasonPhase('HEAT');

        $this->insertRow('ibl_sim_dates', [
            'sim'        => self::HEAT_SIM,
            'start_date' => self::HEAT_START,
            'end_date'   => self::HEAT_END,
        ]);

        foreach (self::TEAM_NAMES as $tid => $name) {
            $this->seedTeam($tid, 'Heat City', $name);
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function setSeasonPhase(string $phase): void
    {
        $stmt = $this->db->prepare(
            'UPDATE `ibl_settings` SET `value` = ? WHERE `setting_key` = \'Current Season Phase\''
        );
        self::assertNotFalse($stmt, 'Prepare must succeed: ' . $this->db->error);
        $stmt->bind_param('s', $phase);
        $stmt->execute();
        $stmt->close();
    }

    private function seedTeam(int $teamid, string $city, string $name): void
    {
        $this->insertRow('ibl_team_info', [
            'teamid'    => $teamid,
            'team_city' => $city,
            'team_name' => $name,
        ]);
    }

    /**
     * Inserts the two team-side rows production writes for one game.
     */
    private function seedBoxScoreGame(string $date, int $gameOfDay, int $visitorTid, int $homeTid): void
    {
        $this->insertTeamBoxscoreRow($date, self::TEAM_NAMES[$visitorTid], $gameOfDay, $visitorTid, $homeTid);
        $this->insertTeamBoxscoreRow($date, self::TEAM_NAMES[$homeTid], $gameOfDay, $visitorTid, $homeTid);
    }

    /**
     * @return array{game_type: int, season_year: int}
     */
    private function fetchGeneratedColumns(string $date, int $visitorTid, int $homeTid): array
    {
        $stmt = $this->db->prepare(
            'SELECT game_type, season_year FROM ibl_box_scores_teams
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ? LIMIT 1'
        );
        self::assertNotFalse($stmt, 'Prepare must succeed: ' . $this->db->error);
        $stmt->bind_param('sii', $date, $visitorTid, $homeTid);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row, 'Box-score row must exist for the generated-column read');

        return [
            'game_type'   => (int) $row['game_type'],
            'season_year' => (int) $row['season_year'],
        ];
    }

    /**
     * Schedule rows: one inside the HEAT window and one a day past it.
     */
    private function seedHeatWindowSchedule(): void
    {
        $this->insertScheduleRow(self::HEAT_SEASON_YEAR, '2025-10-18', self::TEAM_A, 101, self::TEAM_B, 97);
        $this->insertScheduleRow(self::HEAT_SEASON_YEAR, '2025-10-22', self::TEAM_C, 99, self::TEAM_D, 95);
    }

    private function insertTrade(int $pid, int $seasonYear, int $month, int $day, int $fromTid, int $toTid): void
    {
        $this->insertRow('ibl_jsb_transactions', [
            'season_year'       => $seasonYear,
            'transaction_month' => $month,
            'transaction_day'   => $day,
            'transaction_type'  => TrnFileParser::TYPE_TRADE,
            'pid'               => $pid,
            'from_teamid'       => $fromTid,
            'to_teamid'         => $toTid,
            'is_draft_pick'     => 0,
        ]);
    }

    /**
     * G1-G3 get two team-side box rows each. G4 gets none (the orphan).
     */
    private function seedHeatGames(): void
    {
        $this->seedBoxScoreGame('2025-10-18', 1, self::TEAM_A, self::TEAM_B);
        // League-wide per-date ordinal 2: the shape that broke sim 725 when every row was stored as 1.
        $this->seedBoxScoreGame('2025-10-18', 2, self::TEAM_C, self::TEAM_D);
        $this->seedBoxScoreGame('2025-10-19', 1, self::TEAM_B, self::TEAM_C);
    }

    /**
     * Mirrors the box_id overwrite in scripts/storeSimRecap.php.
     *
     * @return array{season_year: int, game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, box_id: ?int, sort_order: int, recap_text: string}
     */
    private function heatGame(string $date, int $visitorTid, int $homeTid, int $gameOfDay, int $sortOrder, string $text): array
    {
        return [
            'season_year'      => self::HEAT_SEASON_YEAR,
            'game_date'        => $date,
            'visitor_teamid'   => $visitorTid,
            'home_teamid'      => $homeTid,
            'game_of_that_day' => $gameOfDay,
            'box_id'           => $this->repo->resolveBoxId($date, $visitorTid, $homeTid, $gameOfDay),
            'sort_order'       => $sortOrder,
            'recap_text'       => $text,
        ];
    }

    private function storeHeatRecap(): void
    {
        $this->seedHeatGames();
        $this->repo->queuePendingIfAbsent(self::HEAT_SIM);
        $this->repo->markDone(
            self::HEAT_SIM,
            'HEAT intro.',
            'HEAT outro.',
            'HEAT full recap.',
            [
                $this->heatGame('2025-10-18', self::TEAM_A, self::TEAM_B, 1, 0, 'HEAT recap G1.'),
                $this->heatGame('2025-10-18', self::TEAM_C, self::TEAM_D, 2, 1, 'HEAT recap G2.'),
                $this->heatGame('2025-10-19', self::TEAM_B, self::TEAM_C, 1, 2, 'HEAT recap G3.'),
                $this->heatGame('2025-10-20', self::TEAM_A, self::TEAM_D, 1, 3, 'HEAT recap G4 orphan.'),
            ],
            null
        );
    }

    // ── Phase 1: fixture honesty ───────────────────────────────────────────────

    public function testHeatBoxScoreRowsCarryHeatGameTypeAndNextSeasonYear(): void
    {
        $this->seedBoxScoreGame('2025-10-18', 1, self::TEAM_A, self::TEAM_B);

        $cols = $this->fetchGeneratedColumns('2025-10-18', self::TEAM_A, self::TEAM_B);

        self::assertSame(self::GAME_TYPE_HEAT, $cols['game_type']);
        self::assertSame(self::HEAT_SEASON_YEAR, $cols['season_year']);
        self::assertNotSame(
            self::HEAT_SEASON_YEAR,
            (int) substr('2025-10-18', 0, 4),
            'HEAT date year must differ from the season year'
        );
    }

    /**
     * Boundary: the generated column discriminates on month, so the HEAT
     * assertion above is not a constant.
     */
    public function testNovemberBoxScoreRowIsRegularSeasonGameType(): void
    {
        $this->seedBoxScoreGame('2025-11-01', 1, self::TEAM_C, self::TEAM_D);

        $cols = $this->fetchGeneratedColumns('2025-11-01', self::TEAM_C, self::TEAM_D);

        self::assertSame(self::GAME_TYPE_REGULAR, $cols['game_type']);
        self::assertSame(2026, $cols['season_year']);
    }

    // ── Phase 1: queue gating ──────────────────────────────────────────────────

    public function testQueueStepQueuesPendingRowDuringHeatPhase(): void
    {
        self::assertSame('HEAT', $this->seasonQuery->getSeasonPhase());

        $result = (new QueueSimSummaryStep($this->repo, $this->seasonQuery))->execute();

        self::assertTrue($result->success);
        self::assertStringNotContainsStringIgnoringCase('disabled', $result->detail);

        $row = $this->repo->find(self::HEAT_SIM);
        self::assertNotNull($row, 'HEAT phase must queue a row for the current sim');
        self::assertSame('pending', $row['status']);
        self::assertSame(0, $row['attempts']);
    }

    public function testQueueStepSkipsDuringPreseasonPhase(): void
    {
        $this->setSeasonPhase('Preseason');

        $result = (new QueueSimSummaryStep($this->repo, $this->seasonQuery))->execute();

        self::assertTrue($result->success);
        self::assertStringContainsString('Preseason', $result->detail);
        self::assertNull($this->repo->find(self::HEAT_SIM));
    }

    // ── Phase 2: context over a HEAT window ────────────────────────────────────

    public function testBuildContextOverHeatWindowReturnsScheduledTeamsRosters(): void
    {
        $ctxRepo = new SimRecapContextRepository($this->db);
        $this->seedHeatWindowSchedule();
        $this->insertTestPlayer(999110, 'Heat Guard', ['teamid' => self::TEAM_A, 'pos' => 'PG']);
        $this->insertTestPlayer(999111, 'Heat Wing', ['teamid' => self::TEAM_B, 'pos' => 'SF']);
        $this->insertTestPlayer(999112, 'Late Center', ['teamid' => self::TEAM_C, 'pos' => 'C']);

        $ctx = $ctxRepo->buildContext(self::HEAT_SIM);

        self::assertSame(self::HEAT_START, $ctx['start_date']);
        self::assertSame(self::HEAT_END, $ctx['end_date']);

        $teamIds = array_keys($ctx['roster']);
        sort($teamIds);
        self::assertSame([self::TEAM_A, self::TEAM_B], $teamIds, 'Teams scheduled one day past the window must be absent');

        self::assertSame([999110], array_column($ctx['roster'][self::TEAM_A], 'pid'));
        self::assertSame(self::TEAM_A, $ctx['roster'][self::TEAM_A][0]['current_teamid']);

        $allPids = [];
        foreach ($ctx['roster'] as $players) {
            foreach ($players as $player) {
                $allPids[] = $player['pid'];
            }
        }
        self::assertNotContains(999112, $allPids);
    }

    public function testHeatWindowTradeDerivesPriorCalendarYearTradeDate(): void
    {
        $ctxRepo = new SimRecapContextRepository($this->db);
        $this->seedHeatWindowSchedule();
        $this->insertTestPlayer(999120, 'Trade One', ['teamid' => self::TEAM_B, 'pos' => 'SG']);
        $this->insertTestPlayer(999121, 'Trade Two', ['teamid' => self::TEAM_A, 'pos' => 'PF']);
        $this->insertTrade(999120, 2026, 10, 18, self::TEAM_A, self::TEAM_B);
        // Window-start boundary: BETWEEN is inclusive.
        $this->insertTrade(999121, 2026, 10, 15, self::TEAM_B, self::TEAM_A);

        $ctx = $ctxRepo->buildContext(self::HEAT_SIM);

        $byPid = [];
        foreach ($ctx['sim_trades'] as $trade) {
            $byPid[$trade['pid']] = $trade;
        }

        self::assertArrayHasKey(999120, $byPid);
        self::assertSame('2025-10-18', $byPid[999120]['trade_date']);
        self::assertArrayHasKey(999121, $byPid);
        self::assertSame('2025-10-15', $byPid[999121]['trade_date']);
        self::assertSame([999121, 999120], array_column($ctx['sim_trades'], 'pid'), 'Trades sort by trade_date ascending');
    }

    /**
     * Negative and boundary: last season's October trade and a trade one day
     * past the window are both excluded.
     */
    public function testPriorSeasonOctoberTradeExcludedFromHeatWindow(): void
    {
        $ctxRepo = new SimRecapContextRepository($this->db);
        $this->seedHeatWindowSchedule();
        $this->insertTestPlayer(999130, 'Old Heat', ['teamid' => self::TEAM_A, 'pos' => 'C']);
        $this->insertTestPlayer(999131, 'Late Heat', ['teamid' => self::TEAM_A, 'pos' => 'C']);
        // Previous HEAT: season_year 2025, calendar 2024-10-18.
        $this->insertTrade(999130, 2025, 10, 18, self::TEAM_A, self::TEAM_B);
        // Calendar 2025-10-22: one day past HEAT_END.
        $this->insertTrade(999131, 2026, 10, 22, self::TEAM_A, self::TEAM_B);

        $ctx = $ctxRepo->buildContext(self::HEAT_SIM);

        $pids = array_column($ctx['sim_trades'], 'pid');
        self::assertNotContains(999130, $pids);
        self::assertNotContains(999131, $pids);
        self::assertSame([], $ctx['sim_trades']);
    }

    // ── Phase 3: store and render ──────────────────────────────────────────────

    public function testResolveBoxIdMatchesHeatGamesByNaturalKey(): void
    {
        $this->seedHeatGames();

        $stmt = $this->db->prepare(
            'SELECT id FROM ibl_box_scores_teams
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ? ORDER BY id'
        );
        self::assertNotFalse($stmt, 'Prepare must succeed: ' . $this->db->error);
        $date = '2025-10-18';
        $visitor = self::TEAM_A;
        $home = self::TEAM_B;
        $stmt->bind_param('sii', $date, $visitor, $home);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $ids = [];
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int) $row['id'];
        }
        $stmt->close();
        self::assertCount(2, $ids, 'Each game has two team-side rows');

        $g1 = $this->repo->resolveBoxId('2025-10-18', self::TEAM_A, self::TEAM_B, 1);
        self::assertSame($ids[0], $g1, 'resolveBoxId returns MIN(id) of the two team-side rows');
        self::assertSame(
            self::GAME_TYPE_HEAT,
            $this->fetchGeneratedColumns('2025-10-18', self::TEAM_A, self::TEAM_B)['game_type']
        );

        $g2 = $this->repo->resolveBoxId('2025-10-18', self::TEAM_C, self::TEAM_D, 2);
        $g3 = $this->repo->resolveBoxId('2025-10-19', self::TEAM_B, self::TEAM_C, 1);
        self::assertNotNull($g2);
        self::assertNotNull($g3);
        self::assertNotSame($g1, $g2);
        self::assertNotSame($g1, $g3);
        self::assertNotSame($g2, $g3);

        self::assertNull($this->repo->resolveBoxId('2025-10-18', self::TEAM_A, self::TEAM_B, 2), 'Wrong ordinal');
        self::assertNull($this->repo->resolveBoxId('2025-10-18', self::TEAM_B, self::TEAM_A, 1), 'Swapped sides');
        self::assertNull($this->repo->resolveBoxId('2025-10-20', self::TEAM_A, self::TEAM_D, 1), 'Unboxed G4');
    }

    public function testHeatGameRecapsAreDisplayableOnceEachAndUnmatchedHeatGameIsOrphaned(): void
    {
        $this->storeHeatRecap();

        $envelope = $this->repo->find(self::HEAT_SIM);
        self::assertNotNull($envelope);
        self::assertSame('done', $envelope['status']);

        $stored = $this->repo->findGameRecaps(self::HEAT_SIM);
        self::assertCount(4, $stored);
        foreach ($stored as $row) {
            self::assertSame(self::HEAT_SEASON_YEAR, $row['season_year']);
            self::assertStringStartsWith('2025-10-', $row['game_date']);
        }

        $displayable = $this->repo->findDisplayableGameRecaps(self::HEAT_SIM);
        self::assertCount(3, $displayable, 'Two team-side rows per game must not duplicate a recap');
        self::assertSame(
            ['HEAT recap G1.', 'HEAT recap G2.', 'HEAT recap G3.'],
            array_column($displayable, 'recap_text')
        );
        self::assertSame([1, 2, 1], array_map('intval', array_column($displayable, 'game_of_that_day')));

        $orphaned = $this->repo->findOrphanedGameRecaps(self::HEAT_SIM);
        self::assertCount(1, $orphaned);
        self::assertSame('2025-10-20', $orphaned[0]['game_date']);
        self::assertSame(self::TEAM_A, (int) $orphaned[0]['visitor_teamid']);
        self::assertSame(self::TEAM_D, (int) $orphaned[0]['home_teamid']);
        self::assertNull($orphaned[0]['box_id']);
    }

    public function testRecapDocumentAssemblesHeatGamesInSortOrder(): void
    {
        $this->storeHeatRecap();

        $doc = RecapDocument::assemble(
            'HEAT intro.',
            $this->repo->findDisplayableGameRecaps(self::HEAT_SIM),
            'HEAT outro.'
        );

        $offsets = [];
        foreach (['HEAT intro.', 'HEAT recap G1.', 'HEAT recap G2.', 'HEAT recap G3.', 'HEAT outro.'] as $needle) {
            $pos = strpos($doc, $needle);
            self::assertNotFalse($pos, "Document must contain '{$needle}'");
            $offsets[] = $pos;
        }
        $sorted = $offsets;
        sort($sorted);
        self::assertSame($sorted, $offsets, 'intro < G1 < G2 < G3 < outro');
        self::assertCount(5, array_unique($offsets));

        self::assertStringNotContainsString('HEAT recap G4 orphan.', $doc);
    }
}
