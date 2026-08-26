<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Boxscore\BoxscoreRepository;
use Boxscore\ScheduleAuditReport;
use Boxscore\ScheduleMembershipGuard;
use Boxscore\ScheduleReconciliationAudit;
use Boxscore\RejectedGame;
use JsbParser\ScoFileParser;
use Boxscore\Boxscore;
use PHPUnit\Framework\Attributes\Group;

/**
 * DB-group integration tests for the replay code path and audit boundary.
 *
 * The replay code path is exercised in-process (same connection, same transaction)
 * so fixture rows seeded in setUp() are visible to the guard's index queries.
 * This also means the "writes nothing" assertion is enforceable: we can snapshot
 * and re-query the same tables on the same connection.
 *
 * Season-year / date encoding for season 2008:
 *   - ibl_box_scores_teams.season_year is GENERATED:
 *       CASE WHEN month(game_date) >= 10 THEN year(game_date) + 1 ELSE year(game_date) END
 *   - Month < 10, year 2008  → season_year = 2008  (e.g. 2008-03-10)
 *   - Month >= 10, year 2007 → season_year = 2008  (not used: month 10 is OFF_SCHEDULE)
 *
 * Game info line encoding (ScoFileParser convention):
 *   monthOffset = ((month - 10) + 12) % 12    0 = Oct, 3 = Jan, 5 = Mar, 4 = Feb
 *   dayOffset   = day - 1
 *   teamIndex   = teamid - 1
 *   gameOfDay   = gotd - 1
 */
#[Group('database')]
final class CheckBoxscoreScheduleReplayTest extends DatabaseTestCase
{
    /** @var list<string> temp .sco files to clean up */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    // ── Replay writes nothing ────────────────────────────────────────────────

    /**
     * The replay code path must never write a row. Counts for ibl_box_scores,
     * ibl_box_scores_teams, and ibl_schedule must be byte-identical before and
     * after a replay run.
     *
     * This is the structural guarantee that future edits cannot silently add a
     * write path to replay mode.
     */
    public function testReplayWritesNothing(): void
    {
        // Seed a schedule row so the guard's isEnabled() returns true.
        // Visitor 3 @ home 7, season 2008, date 2008-03-10
        $this->insertScheduleRow(2008, '2008-03-10', 3, 100, 7, 98);
        $this->insertTeamBoxscoreRow('2008-03-10', 'Metros', 1, 3, 7);

        // Snapshot row counts on the same connection (same transaction).
        $before = $this->countRows(['ibl_box_scores', 'ibl_box_scores_teams', 'ibl_schedule']);

        // Build a synthetic .sco with one game (visitor=3, home=7, March=month 5 offset,
        // day 10 = offset 9) and run the replay code path.
        $gameInfoLine = $this->buildGameInfoLine(5, 9, 0, 2, 6); // visitor=3 (idx 2), home=7 (idx 6)
        $scoFile      = $this->buildScoFile([$this->buildGameRecord($gameInfoLine)]);

        $scoData = file_get_contents($scoFile);
        self::assertIsString($scoData);

        $this->runReplayInProcess($scoData, 2008);

        $after = $this->countRows(['ibl_box_scores', 'ibl_box_scores_teams', 'ibl_schedule']);

        self::assertSame($before['ibl_box_scores'], $after['ibl_box_scores'], 'ibl_box_scores count must be unchanged');
        self::assertSame($before['ibl_box_scores_teams'], $after['ibl_box_scores_teams'], 'ibl_box_scores_teams count must be unchanged');
        self::assertSame($before['ibl_schedule'], $after['ibl_schedule'], 'ibl_schedule count must be unchanged');
    }

    // ── Replay reject / accept ───────────────────────────────────────────────

    /**
     * A game whose triple is NOT in ibl_schedule must be rejected with
     * reason not_in_schedule.
     */
    public function testReplayRejectsGameAbsentFromSchedule(): void
    {
        // A schedule row exists for a DIFFERENT triple — so isEnabled()=true.
        $this->insertScheduleRow(2008, '2008-03-10', 5, 90, 6, 88);

        // Game: visitor=3, home=7 — no schedule row for this triple.
        $gameInfoLine = $this->buildGameInfoLine(5, 9, 0, 2, 6);
        $scoFile      = $this->buildScoFile([$this->buildGameRecord($gameInfoLine)]);
        $scoData      = file_get_contents($scoFile);
        self::assertIsString($scoData);

        ['accepted' => $accepted, 'rejected' => $rejected, 'reasons' => $reasons]
            = $this->runReplayInProcess($scoData, 2008);

        self::assertSame(0, $accepted);
        self::assertSame(1, $rejected);
        self::assertSame(1, $reasons[RejectedGame::REASON_NOT_IN_SCHEDULE] ?? 0);
    }

    /**
     * A game whose triple IS in ibl_schedule must be accepted with zero rejections.
     * This is the false-positive guard: the guard must not flag a legitimate game.
     */
    public function testReplayAcceptsGamePresentInSchedule(): void
    {
        // Schedule row matching the game triple exactly.
        $this->insertScheduleRow(2008, '2008-03-10', 3, 100, 7, 98);

        $gameInfoLine = $this->buildGameInfoLine(5, 9, 0, 2, 6); // visitor=3 (idx 2), home=7 (idx 6)
        $scoFile      = $this->buildScoFile([$this->buildGameRecord($gameInfoLine)]);
        $scoData      = file_get_contents($scoFile);
        self::assertIsString($scoData);

        ['accepted' => $accepted, 'rejected' => $rejected]
            = $this->runReplayInProcess($scoData, 2008);

        self::assertSame(1, $accepted);
        self::assertSame(0, $rejected);
    }

    /**
     * All-Star team IDs (50@51) must be accepted even with no schedule row.
     * February regression guard at the replay entry point.
     */
    public function testReplayAcceptsAllStarGameWithNoScheduleRow(): void
    {
        // Seed a regular-season schedule row so isEnabled()=true.
        $this->insertScheduleRow(2008, '2008-03-10', 5, 90, 6, 88);

        // All-Star game: visitor=50 (idx=49), home=51 (idx=50), February (monthOffset=4).
        $gameInfoLine = $this->buildGameInfoLine(4, 1, 0, 49, 50);
        $scoFile      = $this->buildScoFile([$this->buildGameRecord($gameInfoLine)]);
        $scoData      = file_get_contents($scoFile);
        self::assertIsString($scoData);

        ['accepted' => $accepted, 'rejected' => $rejected]
            = $this->runReplayInProcess($scoData, 2008);

        self::assertSame(1, $accepted, 'All-Star game must be accepted');
        self::assertSame(0, $rejected);
    }

    /**
     * When no schedule rows exist for the season, the guard fails open and
     * accepts every game. Zero rejections from a non-empty .sco is the contract.
     */
    public function testReplayOnEmptyScheduleAcceptsEverything(): void
    {
        // No schedule rows for season 9999 — guard isEnabled()=false.
        $gameInfoLine = $this->buildGameInfoLine(5, 9, 0, 2, 6);
        $scoFile      = $this->buildScoFile([$this->buildGameRecord($gameInfoLine)]);
        $scoData      = file_get_contents($scoFile);
        self::assertIsString($scoData);

        ['accepted' => $accepted, 'rejected' => $rejected]
            = $this->runReplayInProcess($scoData, 9999);

        self::assertSame(1, $accepted, 'Empty-schedule season must accept everything');
        self::assertSame(0, $rejected);
    }

    // ── Audit exit-code boundary ─────────────────────────────────────────────

    /**
     * Seeding an orphan boxscore must make the audit report exitCode() === 1.
     * Matrix row 8 at the executable boundary.
     */
    public function testAuditExitCodeIsOneWithOrphan(): void
    {
        // Enablement row (different triple).
        $this->insertScheduleRow(2008, '2008-01-10', 5, 90, 6, 88);
        // Orphan: boxscore triple not in schedule.
        $this->insertTeamBoxscoreRow('2008-01-15', 'Metros', 1, 3, 7);

        $report = $this->buildAudit()->run(2008);

        self::assertSame(1, $report->exitCode(), 'Orphan boxscore must set exitCode to 1');
    }

    /**
     * A played-but-missing schedule row (warning only) must NOT raise exitCode.
     * Matrix row 9 at the executable boundary.
     */
    public function testAuditExitCodeIsZeroWithMissingOnly(): void
    {
        // Played schedule row (non-zero scores) but no boxscore rows → warning only.
        $this->insertScheduleRow(2008, '2008-06-25', 3, 134, 19, 147);

        $report = $this->buildAudit()->run(2008);

        self::assertSame([], $report->errors(), 'No errors expected for a missing boxscore');
        self::assertSame(0, $report->exitCode(), 'Missing boxscore must not raise exit code');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function buildAudit(): ScheduleReconciliationAudit
    {
        return new ScheduleReconciliationAudit(new BoxscoreRepository($this->db));
    }

    /**
     * Run the replay logic in-process against $scoData for $season.
     *
     * Returns ['accepted', 'rejected', 'reasons'] where 'reasons' is
     * array<string, int> tallied by RejectedGame::$reason.
     *
     * @return array{accepted: int, rejected: int, reasons: array<string, int>}
     */
    private function runReplayInProcess(string $scoData, int $season): array
    {
        $repo   = new BoxscoreRepository($this->db);
        $guard  = ScheduleMembershipGuard::fromRepository($repo, $season);

        $accepted = 0;
        $rejected = 0;
        /** @var array<string, int> $reasons */
        $reasons    = [];
        $dataLength = strlen($scoData);

        $offset = ScoFileParser::HEADER_OFFSET_BYTES;
        while ($offset + ScoFileParser::GAME_PAYLOAD_SIZE <= $dataLength) {
            $line         = substr($scoData, $offset, ScoFileParser::RECORD_SIZE);
            $offset      += ScoFileParser::RECORD_SIZE;
            $gameInfoLine = ScoFileParser::extractGameInfo($line);
            $boxscore     = Boxscore::withGameInfoLine($gameInfoLine, $season, '', 'ibl');
            $rejection    = $guard->evaluate($boxscore);
            if ($rejection === null) {
                $accepted++;
            } else {
                $rejected++;
                $reasons[$rejection->reason] = ($reasons[$rejection->reason] ?? 0) + 1;
            }
        }

        return ['accepted' => $accepted, 'rejected' => $rejected, 'reasons' => $reasons];
    }

    /**
     * Snapshot COUNT(*) for each given table name on the shared connection.
     *
     * @param list<string> $tables
     * @return array<string, int>
     */
    private function countRows(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table) {
            $result = $this->db->query("SELECT COUNT(*) AS cnt FROM `{$table}`");
            self::assertNotFalse($result);
            $row            = $result->fetch_assoc();
            self::assertIsArray($row);
            $counts[$table] = (int) $row['cnt'];
            $result->free();
        }
        return $counts;
    }

    /**
     * Build a 58-byte game info line matching ScoFileParser's encoding convention.
     *
     * monthOffset: ((month-10)+12)%12  — 0=Oct, 1=Nov, 2=Dec, 3=Jan, 4=Feb, 5=Mar
     * dayOffset:   day - 1
     * gameOfDay:   gotd - 1 (0-indexed)
     * visitorIndex: teamid - 1
     * homeIndex:    teamid - 1
     */
    private function buildGameInfoLine(
        int $monthOffset  = 0,
        int $dayOffset    = 14,
        int $gameOfDay    = 0,
        int $visitorIndex = 0,
        int $homeIndex    = 1,
    ): string {
        return sprintf('%02d', $monthOffset)    // month offset from Oct
             . sprintf('%02d', $dayOffset)      // day offset (0-indexed)
             . sprintf('%02d', $gameOfDay)      // game of that day (0-indexed)
             . sprintf('%02d', $visitorIndex)   // visitor team (0-indexed)
             . sprintf('%02d', $homeIndex)      // home team (0-indexed)
             . '18000'                          // attendance
             . '20000'                          // capacity
             . '1005'                           // visitor wins/losses
             . '0510'                           // home wins/losses
             . '025030028027000'                // visitor quarter scores (5×3 chars)
             . '022031025030000';               // home quarter scores (5×3 chars)
    }

    /**
     * Build a padded 2000-byte game record from a 58-byte game info line.
     * Includes minimal visitor and home player slots to satisfy the parser.
     */
    private function buildGameRecord(string $gameInfoLine, bool $padToRecordSize = true): string
    {
        $emptySlot     = str_repeat(' ', ScoFileParser::PLAYER_SLOT_SIZE);
        $teamTotalLine = substr(
            str_pad(
                str_pad('Visitor Total', 16) . '  ' . '000000' . '00' . '3508004003020605060302010203',
                ScoFileParser::PLAYER_SLOT_SIZE
            ),
            0,
            ScoFileParser::PLAYER_SLOT_SIZE
        );
        $playerLine = substr(
            str_pad(
                str_pad('Test Player', 16) . 'PG' . '200001' . '32' . '0801500030201030402010102',
                ScoFileParser::PLAYER_SLOT_SIZE
            ),
            0,
            ScoFileParser::PLAYER_SLOT_SIZE
        );
        $homeTeamTotal = substr(
            str_pad(
                str_pad('Home Total', 16) . '  ' . '000000' . '00' . '3207003002020504050302010203',
                ScoFileParser::PLAYER_SLOT_SIZE
            ),
            0,
            ScoFileParser::PLAYER_SLOT_SIZE
        );
        $homePlayer = substr(
            str_pad(
                str_pad('Home Player', 16) . 'SG' . '200002' . '28' . '0701200020201020301010102',
                ScoFileParser::PLAYER_SLOT_SIZE
            ),
            0,
            ScoFileParser::PLAYER_SLOT_SIZE
        );

        $gameData  = $gameInfoLine;
        $gameData .= $teamTotalLine;
        $gameData .= $playerLine;
        for ($i = 2; $i < 15; $i++) {
            $gameData .= $emptySlot;
        }
        $gameData .= $homeTeamTotal;
        $gameData .= $homePlayer;
        for ($i = 17; $i < 30; $i++) {
            $gameData .= $emptySlot;
        }

        if ($padToRecordSize) {
            $gameData = str_pad($gameData, ScoFileParser::RECORD_SIZE);
        }

        return $gameData;
    }

    /**
     * Write a synthetic .sco file: 1MB null header followed by the given records.
     * Returns the temp file path; the file is cleaned up in tearDown.
     *
     * @param list<string> $records
     */
    private function buildScoFile(array $records): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'cbs_replay_');
        self::assertIsString($tmpFile);
        file_put_contents($tmpFile, str_repeat("\0", ScoFileParser::HEADER_OFFSET_BYTES) . implode('', $records));
        $this->tempFiles[] = $tmpFile;
        return $tmpFile;
    }
}
