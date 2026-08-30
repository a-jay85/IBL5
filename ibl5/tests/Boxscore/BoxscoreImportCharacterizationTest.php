<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\BoxscoreProcessor;
use Boxscore\BoxscoreRepository;
use PHPUnit\Framework\TestCase;
use Season\Season;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Characterization (golden-master) pin suite for BoxscoreProcessor's import paths.
 *
 * Every expected literal below was captured by running this test once against the
 * unmodified `BoxscoreProcessor` and copying the actual value out of the failure diff.
 * Do not hand-edit these — if behavior is intentionally changed, regenerate via the
 * same capture procedure.
 *
 * Pin-file immutability gate: this file must exercise only the four public entry
 * points of BoxscoreProcessorInterface. It must never use TestableBoxscoreProcessor
 * or any protected/private member.
 *
 * onQuery traps — three rules that must be honored by every pin in this file:
 *  - MockDatabase::onQuery() keys the pattern into an array: re-registering the same
 *    pattern overwrites it. Patterns are tested in registration order and the first hit
 *    wins — register the most specific first.
 *  - The pattern used by the existing suite, (?s)SELECT.*ibl_box_scores_teams.*WHERE,
 *    matches both findTeamBoxscore and findAllStarTeamNames. All-Star pins must use
 *    disjoint patterns instead: SELECT\s+name\s+FROM (names),
 *    SELECT visitor_q1_points (findTeamBoxscore), COUNT\(\*\) AS cnt (null-teamid count).
 *  - MockDatabase::sql_query() short-circuits INSERT/UPDATE/DELETE before pattern
 *    routing, so only SELECTs can be routed.
 *
 * @covers \Boxscore\BoxscoreProcessor
 */
class BoxscoreImportCharacterizationTest extends TestCase
{
    /** @var list<string> Temp files to clean up */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    // ── Harness helpers ────────────────────────────────────────────────────

    /**
     * Digest of every write (and SELECT) the processor issued against $db.
     *
     * For each entry of $db->getExecutedQueries() (backticks already stripped):
     *  1. Collapse whitespace: preg_replace('/\s+/', ' ', trim($sql))
     *  2. Scrub non-deterministic UUIDs to <uuid>.
     *  3. Derive the verb (strtoupper(strtok($sql, ' '))) and the table
     *     (INSERT INTO (\w+), DELETE FROM (\w+) / FROM (\w+), UPDATE (\w+)).
     *  4. Emit "INSERT <table> VALUES(<text after the final VALUES>)" for INSERT,
     *     or "<VERB> <table> WHERE <text after the first WHERE>" otherwise.
     *
     * This pins the argument values byte-for-byte while deliberately not
     * freezing column lists or bind_param type strings.
     * Interpolation is already done: MockPreparedStatement::replacePlaceholders()
     * substitutes bound params back into the ? positions before sql_query() records
     * them.
     *
     * @return list<string>
     */
    private function opDigest(MockDatabase $db): array
    {
        $entries = [];
        foreach ($db->getExecutedQueries() as $raw) {
            $sql = preg_replace('/\s+/', ' ', trim($raw)) ?? $raw;
            $sql = preg_replace(
                '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
                '<uuid>',
                $sql
            ) ?? $sql;

            $verbRaw = strtok($sql, ' ');
            $verb = strtoupper($verbRaw !== false ? $verbRaw : '');

            $table = '';
            if ($verb === 'INSERT' && preg_match('/INSERT\s+INTO\s+(\w+)/i', $sql, $m)) {
                $table = $m[1];
            } elseif ($verb === 'DELETE' && preg_match('/DELETE\s+FROM\s+(\w+)/i', $sql, $m)) {
                $table = $m[1];
            } elseif ($verb === 'UPDATE' && preg_match('/UPDATE\s+(\w+)/i', $sql, $m)) {
                $table = $m[1];
            } elseif (preg_match('/\bFROM\s+(\w+)/i', $sql, $m)) {
                $table = $m[1];
            }

            if ($verb === 'INSERT') {
                $pos = strrpos(strtoupper($sql), 'VALUES');
                $after = $pos !== false ? substr($sql, $pos + 6) : '';
                $entries[] = "INSERT {$table} VALUES({$after})";
            } else {
                $wherePos = stripos($sql, ' WHERE ');
                $after = $wherePos !== false ? substr($sql, $wherePos + 7) : '';
                $entries[] = "{$verb} {$table} WHERE {$after}";
            }
        }
        return $entries;
    }

    /**
     * Build the raw bytes for processAllStarGamesData().
     *
     * Block 0 (bytes 0-1999): Rising Stars record (2000 bytes).
     * Block 1 (bytes 2000-3999): All-Star record (2000 bytes).
     *
     * A null $risingStarsLine yields str_repeat(' ', 2000) for block 0.
     * A null $allStarLine means no block 1 is appended (total = 2000 bytes);
     * this expresses a rising-stars-only input because processAllStarGamesData
     * leaves $allStarLine unset when the data is shorter than RECORD_SIZE * 2.
     *
     * No 1 MB header prefix: the All-Star path reads from offset 0, unlike
     * processScoData which skips ScoFileParser::HEADER_OFFSET_BYTES.
     */
    private function buildAllStarBlocks(?string $risingStarsLine, ?string $allStarLine): string
    {
        $block0 = $risingStarsLine !== null ? str_pad($risingStarsLine, 2000) : str_repeat(' ', 2000);
        if ($allStarLine === null) {
            return $block0;
        }
        return $block0 . str_pad($allStarLine, 2000);
    }

    /**
     * Write $contents to a temp file, register it for tearDown cleanup, and return
     * the path. Pass the returned path to BoxscoreProcessorInterface::processAllStarGames()
     * (which takes string $filePath). To use processAllStarGamesData() instead, pass
     * the raw string directly (its parameter type is string $data, not a path).
     */
    private function writeTempScoFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sco_char_');
        $this->assertNotFalse($path);
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;
        return $path;
    }

    /**
     * Build a minimal 58-char game info line for testing.
     *
     * Format: 2-char month offset, 2-char day offset, 2-char game#,
     * 2-char visitor, 2-char home, 5-char attendance, 5-char capacity,
     * then W/L and quarter scores to fill 58 chars total.
     */
    private function buildGameInfoLine(int $monthOffset = 0, int $dayOffset = 14): string
    {
        // Month offset (0=Oct), day offset (0=day 1), game#=0, visitor=0, home=1
        $line = sprintf('%02d', $monthOffset)  // month offset from Oct
              . sprintf('%02d', $dayOffset)     // day offset (0-indexed)
              . '00'                            // game of that day
              . '00'                            // visitor team (0-indexed → teamid 1)
              . '01'                            // home team (0-indexed → teamid 2)
              . '18000'                         // attendance
              . '20000'                         // capacity
              . '1005'                          // visitor wins/losses
              . '0510'                          // home wins/losses
              . '025030028027000'               // visitor quarter scores (5x3 chars)
              . '022031025030000';              // home quarter scores (5x3 chars)

        return $line;
    }

    /**
     * Build a populated 2000-byte game record (NO 1 MB header): visitor team total
     * (slot 0) + visitor player (slot 1), home team total (slot 15) + home player
     * (slot 16), every other slot blank. This is the raw block the All-Star path
     * reads from offset 0 — pass it as the Rising Stars block (block 0) or the
     * All-Star block (block 1) via buildAllStarBlocks() to exercise the write path.
     * buildScoFileWithOneGame() prepends the 1 MB header to feed processScoData.
     */
    private function buildGameRecord(string $gameInfoLine): string
    {
        // Build 30 player slots × 53 bytes each = 1590 bytes
        // Slot 0: visitor team total (name with pid=0)
        $teamTotalLine = str_pad('Visitor Total', 16) // name (16)
            . '  '                                     // pos (2)
            . '000000'                                 // pid=0 means team total (6)
            . '00'                                     // minutes (2)
            . '3508004003020605060302010203';           // stats (27)
        // Slot 1: visitor player
        $playerLine = str_pad('Test Player', 16)       // name (16)
            . 'PG'                                     // pos (2)
            . '200001'                                 // pid (6)
            . '32'                                     // minutes (2)
            . '0801500030201030402010102';              // stats (25)
        // Pad playerLine to exactly 53 chars
        $playerLine = str_pad($playerLine, 53);
        $teamTotalLine = str_pad($teamTotalLine, 53);

        // Slot 15: home team total
        $homeTeamTotal = str_pad('Home Total', 16)
            . '  '
            . '000000'
            . '00'
            . '3207003002020504050302010203';
        $homeTeamTotal = str_pad($homeTeamTotal, 53);

        // Slot 16: home player
        $homePlayer = str_pad('Home Player', 16)
            . 'SG'
            . '200002'
            . '28'
            . '0701200020201020301010102';
        $homePlayer = str_pad($homePlayer, 53);

        // Build 30 slots: fill unused slots with spaces
        $emptySlot = str_repeat(' ', 53);
        $gameData = $gameInfoLine; // 58 bytes
        // Slots 0-14 (visitor)
        $gameData .= $teamTotalLine;  // slot 0: team total
        $gameData .= $playerLine;     // slot 1: player
        for ($i = 2; $i < 15; $i++) {
            $gameData .= $emptySlot;
        }
        // Slots 15-29 (home)
        $gameData .= $homeTeamTotal;  // slot 15: team total
        $gameData .= $homePlayer;     // slot 16: player
        for ($i = 17; $i < 30; $i++) {
            $gameData .= $emptySlot;
        }

        // Pad to exactly 2000 bytes
        return str_pad($gameData, 2000);
    }

    private function buildScoFileWithOneGame(string $gameInfoLine): string
    {
        $gameData = $this->buildGameRecord($gameInfoLine);

        // Write file: 1MB padding + game data
        $tmpFile = tempnam(sys_get_temp_dir(), 'sco_test_');
        $this->assertNotFalse($tmpFile);
        file_put_contents($tmpFile, str_repeat("\0", 1000000) . $gameData);
        $this->tempFiles[] = $tmpFile;

        return $tmpFile;
    }

    // ── Tests ──────────────────────────────────────────────────────────────

    public function testOpDigestReturnsEmptyForNoExecutedQueries(): void
    {
        $db = new MockDatabase();
        $this->assertSame([], $this->opDigest($db));
    }

    // ── Phase 2: Regular-season import pins ────────────────────────────────

    public function testRegularSeasonImportPinsMutationSequence(): void
    {
        $db = new MockDatabase();
        $db->setReturnTrue(true);
        $db->onQuery('(?s)SELECT.*ibl_box_scores_teams.*LIMIT 1', []);

        $seasonStub = self::createStub(Season::class);
        $repository = new BoxscoreRepository($db);
        $processor = new BoxscoreProcessor($db, $repository, $seasonStub);

        $scoFile = $this->buildScoFileWithOneGame($this->buildGameInfoLine());
        $data = file_get_contents($scoFile);
        $this->assertIsString($data);

        $processor->processScoData($data, 2026, 'Regular Season/Playoffs', skipSimDates: true);

        $this->assertSame([
            "SELECT ibl_schedule WHERE season_year = 2026",
            "SELECT ibl_box_scores_teams WHERE season_year = 2026 AND visitor_teamid IS NOT NULL AND home_teamid IS NOT NULL",
            "SELECT ibl_box_scores_teams WHERE game_date = '2026-10-15' AND visitor_teamid = 1 AND home_teamid = 2 AND game_of_that_day = 1 LIMIT 1",
            "INSERT ibl_box_scores_teams VALUES( ('2026-10-15','Visitor Total',1,1,2,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,35,80,4,0,30,20,60,50,60,30,20,10,20))",
            "INSERT ibl_box_scores_teams VALUES( ('2026-10-15','3Test Player',1,1,2,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,20,801,50,0,30,20,10,30,40,20,10,10,2))",
            "INSERT ibl_box_scores_teams VALUES( ('2026-10-15','Home Total',1,1,2,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,3,207,0,30,2,2,5,4,5,3,2,1,2))",
            "INSERT ibl_box_scores_teams VALUES( ('2026-10-15','03Home Player',1,1,2,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,28,70,12,0,2,2,1,2,3,1,1,1,2))",
        ], $this->opDigest($db));
    }

    public function testRegularSeasonImportPinsReturnContract(): void
    {
        $db = new MockDatabase();
        $db->setReturnTrue(true);
        $db->onQuery('(?s)SELECT.*ibl_box_scores_teams.*LIMIT 1', []);

        $seasonStub = self::createStub(Season::class);
        $repository = new BoxscoreRepository($db);
        $processor = new BoxscoreProcessor($db, $repository, $seasonStub);

        $scoFile = $this->buildScoFileWithOneGame($this->buildGameInfoLine());
        $data = file_get_contents($scoFile);
        $this->assertIsString($data);

        $result = $processor->processScoData($data, 2026, 'Regular Season/Playoffs', skipSimDates: true);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['gamesInserted']);
        $this->assertSame(0, $result['gamesUpdated']);
        $this->assertSame(0, $result['gamesSkipped']);
        $this->assertSame(4, $result['linesProcessed']);
        $this->assertSame([
            'Parsing .sco file for the 2025-2026 Regular Season/Playoffs...',
            'Schedule guard disabled: ibl_schedule has no rows for season 2026; importing without membership validation.',
            'Number of .sco lines processed: 4',
            'Games inserted: 1 | Games updated: 0 | Games skipped: 0 | Games rejected: 0',
        ], $result['messages']);
    }

    public function testRegularSeasonAlreadyPresentGameIsSkipped(): void
    {
        $db = new MockDatabase();
        $db->setReturnTrue(true);
        $db->onQuery('COUNT\(\*\) AS cnt', [['cnt' => 0]]);
        $db->onQuery('(?s)SELECT.*ibl_box_scores_teams.*LIMIT 1', [[
            'visitor_q1_points' => 25, 'visitor_q2_points' => 30, 'visitor_q3_points' => 28,
            'visitor_q4_points' => 27, 'visitor_ot_points' => 0,
            'home_q1_points' => 22, 'home_q2_points' => 31, 'home_q3_points' => 25,
            'home_q4_points' => 30, 'home_ot_points' => 0,
        ]]);

        $seasonStub = self::createStub(Season::class);
        $repository = new BoxscoreRepository($db);
        $processor = new BoxscoreProcessor($db, $repository, $seasonStub);

        $scoFile = $this->buildScoFileWithOneGame($this->buildGameInfoLine());
        $data = file_get_contents($scoFile);
        $this->assertIsString($data);

        $result = $processor->processScoData($data, 2026, 'Regular Season/Playoffs', skipSimDates: true);

        $this->assertSame(1, $result['gamesSkipped']);
        $this->assertSame(0, $result['linesProcessed']);
        $insertOrDelete = array_filter(
            $this->opDigest($db),
            static fn (string $e): bool => str_starts_with($e, 'INSERT') || str_starts_with($e, 'DELETE')
        );
        $this->assertEmpty($insertOrDelete);
    }

    public function testRegularSeasonEmptyRecordProducesNoWrites(): void
    {
        $db = new MockDatabase();
        $db->setReturnTrue(true);
        $db->onQuery('(?s)SELECT.*ibl_box_scores_teams.*LIMIT 1', []);

        $seasonStub = self::createStub(Season::class);
        $repository = new BoxscoreRepository($db);
        $processor = new BoxscoreProcessor($db, $repository, $seasonStub);

        // Build a file with one all-spaces 2000-byte record
        $emptyRecord = str_repeat(' ', 2000);
        $tmpFile = $this->writeTempScoFile(str_repeat("\0", 1000000) . $emptyRecord);
        $data = file_get_contents($tmpFile);
        $this->assertIsString($data);

        $result = $processor->processScoData($data, 2026, 'Regular Season/Playoffs', skipSimDates: true);

        $this->assertSame(0, $result['gamesInserted']);
        $this->assertSame(0, $result['gamesUpdated']);
        $this->assertSame(0, $result['gamesSkipped']);
        $this->assertSame(0, $result['linesProcessed']);
        $insertOrDelete = array_filter(
            $this->opDigest($db),
            static fn (string $e): bool => str_starts_with($e, 'INSERT') || str_starts_with($e, 'DELETE')
        );
        $this->assertEmpty($insertOrDelete);
    }

    // ── Phase 3: All-Star import pins ──────────────────────────────────────

    public function testAllStarOutcomeAExistsWithMatchingScores(): void
    {
        $db = new MockDatabase();
        $db->setReturnTrue(true);
        // findAllStarTeamNames → 2 rows (names exist); checked first — most specific
        $db->onQuery('SELECT\s+name\s+FROM', [['name' => 'East'], ['name' => 'West']]);
        // findTeamBoxscore — scores match fixture (q1=25/22, q2=30/31, q3=28/25, q4=27/30, ot=0/0)
        $db->onQuery('SELECT visitor_q1_points', [[
            'visitor_q1_points' => 25, 'visitor_q2_points' => 30, 'visitor_q3_points' => 28,
            'visitor_q4_points' => 27, 'visitor_ot_points' => 0,
            'home_q1_points' => 22, 'home_q2_points' => 31, 'home_q3_points' => 25,
            'home_q4_points' => 30, 'home_ot_points' => 0,
        ]]);

        $seasonStub = self::createStub(Season::class);
        $seasonStub->method('getLastBoxScoreDate')->willReturn('2026-02-10');
        $repository = new BoxscoreRepository($db);
        $processor = new BoxscoreProcessor($db, $repository, $seasonStub);

        $allStarLine = $this->buildGameInfoLine();
        $data = $this->buildAllStarBlocks(null, $allStarLine);
        $result = $processor->processAllStarGamesData($data, 2026);

        $this->assertTrue($result['success']);
        $this->assertSame('All-Star Game: already exists with matching scores, skipped.', $result['messages'][0] ?? 'WRONG');
        $insertOrDelete = array_filter(
            $this->opDigest($db),
            static fn (string $e): bool => str_starts_with($e, 'INSERT') || str_starts_with($e, 'DELETE')
        );
        $this->assertEmpty($insertOrDelete);
    }

    public function testAllStarOutcomeBExistsScoresDifferNamesRecoverable(): void
    {
        $db = new MockDatabase();
        $db->setReturnTrue(true);
        // findAllStarTeamNames → 2 rows (East/West); registered first — most specific
        $db->onQuery('SELECT\s+name\s+FROM', [['name' => 'East'], ['name' => 'West']]);
        // findTeamBoxscore → all-zero scores (differ from fixture q1=25/22…) → update path
        $db->onQuery('SELECT visitor_q1_points', [[
            'visitor_q1_points' => 0, 'visitor_q2_points' => 0, 'visitor_q3_points' => 0,
            'visitor_q4_points' => 0, 'visitor_ot_points' => 0,
            'home_q1_points' => 0, 'home_q2_points' => 0, 'home_q3_points' => 0,
            'home_q4_points' => 0, 'home_ot_points' => 0,
        ]]);

        $seasonStub = self::createStub(Season::class);
        $seasonStub->method('getLastBoxScoreDate')->willReturn('2026-02-10');
        $repository = new BoxscoreRepository($db);
        $processor = new BoxscoreProcessor($db, $repository, $seasonStub);

        $allStarLine = $this->buildGameInfoLine();
        $data = $this->buildAllStarBlocks(null, $this->buildGameRecord($allStarLine));
        $result = $processor->processAllStarGamesData($data, 2026);

        $this->assertTrue($result['success']);
        $this->assertSame([
            "SELECT ibl_box_scores_teams WHERE game_date = '2026-02-03' AND visitor_teamid = 50 AND home_teamid = 51 ORDER BY id ASC LIMIT 2",
            "SELECT ibl_box_scores_teams WHERE game_date = '2026-02-03' AND visitor_teamid = 50 AND home_teamid = 51 AND game_of_that_day = 1 LIMIT 1",
            "DELETE ibl_box_scores_teams WHERE game_date = '2026-02-03' AND visitor_teamid = 50 AND home_teamid = 51 AND game_of_that_day = 1",
            "DELETE ibl_box_scores WHERE game_date = '2026-02-03' AND visitor_teamid = 50 AND home_teamid = 51 AND game_of_that_day = 1",
            "INSERT ibl_box_scores_teams VALUES( ('2026-02-03','East',1,50,51,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,35,80,4,0,30,20,60,50,60,30,20,10,20))",
            "INSERT ibl_box_scores_teams VALUES( ('2026-02-03','West',1,50,51,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,20,801,50,0,30,20,10,30,40,20,10,10,2))",
            "INSERT ibl_box_scores_teams VALUES( ('2026-02-03','West',1,50,51,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,3,207,0,30,2,2,5,4,5,3,2,1,2))",
            "INSERT ibl_box_scores_teams VALUES( ('2026-02-03','West',1,50,51,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,28,70,12,0,2,2,1,2,3,1,1,1,2))",
        ], $this->opDigest($db));
        $this->assertSame('All-Star Game: updated with existing team names (4 lines).', $result['messages'][0] ?? 'WRONG');
    }

    public function testAllStarOutcomeBNamesNotRecoverable(): void
    {
        $db = new MockDatabase();
        $db->setReturnTrue(true);
        // findAllStarTeamNames → only 1 row → count($rows) < 2 → returns null → Outcome C path
        $db->onQuery('SELECT\s+name\s+FROM', [['name' => 'East']]);
        // processGameUpsert → findTeamBoxscore → not found → 'insert'
        $db->onQuery('SELECT visitor_q1_points', []);

        $seasonStub = self::createStub(Season::class);
        $seasonStub->method('getLastBoxScoreDate')->willReturn('2026-02-10');
        $repository = new BoxscoreRepository($db);
        $processor = new BoxscoreProcessor($db, $repository, $seasonStub);

        $allStarLine = $this->buildGameInfoLine();
        $data = $this->buildAllStarBlocks(null, $this->buildGameRecord($allStarLine));
        $result = $processor->processAllStarGamesData($data, 2026);

        $this->assertTrue($result['success']);
        // count($rows) < 2 → findAllStarTeamNames() returns null → default names.
        // Full digest frozen so the insert COUNT and both default names are pinned
        // (the mutation-killer for the count($rows) < 2 boundary in BoxscoreRepository).
        $digest = $this->opDigest($db);
        $this->assertSame([
            'SELECT ibl_box_scores_teams WHERE game_date = \'2026-02-03\' AND visitor_teamid = 50 AND home_teamid = 51 ORDER BY id ASC LIMIT 2',
            'SELECT ibl_box_scores_teams WHERE game_date = \'2026-02-03\' AND visitor_teamid = 50 AND home_teamid = 51 AND game_of_that_day = 1 LIMIT 1',
            'INSERT ibl_box_scores_teams VALUES( (\'2026-02-03\',\'Team Away\',1,50,51,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,35,80,4,0,30,20,60,50,60,30,20,10,20))',
            'INSERT ibl_box_scores_teams VALUES( (\'2026-02-03\',\'Team Home\',1,50,51,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,20,801,50,0,30,20,10,30,40,20,10,10,2))',
            'INSERT ibl_box_scores_teams VALUES( (\'2026-02-03\',\'Team Home\',1,50,51,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,3,207,0,30,2,2,5,4,5,3,2,1,2))',
            'INSERT ibl_box_scores_teams VALUES( (\'2026-02-03\',\'Team Home\',1,50,51,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,28,70,12,0,2,2,1,2,3,1,1,1,2))',
        ], $digest);
        // Tie the frozen literals to the source constants (guards a constant rename).
        $teamInserts = array_values(array_filter(
            $digest,
            static fn (string $e): bool => str_starts_with($e, 'INSERT ibl_box_scores_teams')
        ));
        $this->assertStringContainsString(BoxscoreProcessor::DEFAULT_AWAY_NAME, $teamInserts[0]);
        $this->assertStringContainsString(BoxscoreProcessor::DEFAULT_HOME_NAME, $teamInserts[1]);
    }

    public function testAllStarOutcomeCDoesNotExist(): void
    {
        $db = new MockDatabase();
        $db->setReturnTrue(true);
        // findAllStarTeamNames → 0 rows → returns null → skips names block
        $db->onQuery('SELECT\s+name\s+FROM', []);
        // processGameUpsert → findTeamBoxscore → not found → 'insert'
        $db->onQuery('SELECT visitor_q1_points', []);

        $seasonStub = self::createStub(Season::class);
        $seasonStub->method('getLastBoxScoreDate')->willReturn('2026-02-10');
        $repository = new BoxscoreRepository($db);
        $processor = new BoxscoreProcessor($db, $repository, $seasonStub);

        $allStarLine = $this->buildGameInfoLine();
        $data = $this->buildAllStarBlocks(null, $this->buildGameRecord($allStarLine));
        $result = $processor->processAllStarGamesData($data, 2026);

        $this->assertTrue($result['success']);
        $this->assertSame([
            'SELECT ibl_box_scores_teams WHERE game_date = \'2026-02-03\' AND visitor_teamid = 50 AND home_teamid = 51 ORDER BY id ASC LIMIT 2',
            'SELECT ibl_box_scores_teams WHERE game_date = \'2026-02-03\' AND visitor_teamid = 50 AND home_teamid = 51 AND game_of_that_day = 1 LIMIT 1',
            'INSERT ibl_box_scores_teams VALUES( (\'2026-02-03\',\'Team Away\',1,50,51,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,35,80,4,0,30,20,60,50,60,30,20,10,20))',
            'INSERT ibl_box_scores_teams VALUES( (\'2026-02-03\',\'Team Home\',1,50,51,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,20,801,50,0,30,20,10,30,40,20,10,10,2))',
            'INSERT ibl_box_scores_teams VALUES( (\'2026-02-03\',\'Team Home\',1,50,51,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,3,207,0,30,2,2,5,4,5,3,2,1,2))',
            'INSERT ibl_box_scores_teams VALUES( (\'2026-02-03\',\'Team Home\',1,50,51,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,28,70,12,0,2,2,1,2,3,1,1,1,2))',
        ], $this->opDigest($db));
        $this->assertSame('All-Star Game: inserted (4 lines).', $result['messages'][0] ?? 'WRONG');
    }

    public function testAllStarBeforeBreakCutoffIsSkipped(): void
    {
        $db = new MockDatabase();
        $db->setReturnTrue(true);

        $seasonStub = self::createStub(Season::class);
        // Date equal to cutoff: $allStarCutoff = '2026-02-04'
        $seasonStub->method('getLastBoxScoreDate')->willReturn('2026-02-04');
        $repository = new BoxscoreRepository($db);
        $processor = new BoxscoreProcessor($db, $repository, $seasonStub);

        $allStarLine = $this->buildGameInfoLine();
        $data = $this->buildAllStarBlocks(null, $allStarLine);
        $result = $processor->processAllStarGamesData($data, 2026);

        $this->assertTrue($result['success']);
        $this->assertSame('All-Star Weekend not yet reached', $result['skipped']);
        $this->assertEmpty($this->opDigest($db));
    }

    // ── Phase 4: Rising-Stars import pins ──────────────────────────────────

    public function testRisingStarsImportPinsMutationSequence(): void
    {
        $db = new MockDatabase();
        $db->setReturnTrue(true);
        // processGameUpsert for Rising Stars: findTeamBoxscore → not found → 'insert'
        $db->onQuery('SELECT visitor_q1_points', []);

        $seasonStub = self::createStub(Season::class);
        $seasonStub->method('getLastBoxScoreDate')->willReturn('2026-02-10');
        $repository = new BoxscoreRepository($db);
        $processor = new BoxscoreProcessor($db, $repository, $seasonStub);

        $risingStarsLine = $this->buildGameInfoLine();
        // 2000-byte input: only the Rising Stars block (no All-Star line), populated
        // with team totals + players so the write path (overrideGameContext → TIDs
        // 40/41, Rookies/Sophomores) actually emits INSERTs to pin.
        $data = $this->buildAllStarBlocks($this->buildGameRecord($risingStarsLine), null);
        $processor->processAllStarGamesData($data, 2026);

        $this->assertSame([
            'SELECT ibl_box_scores_teams WHERE game_date = \'2026-02-02\' AND visitor_teamid = 40 AND home_teamid = 41 AND game_of_that_day = 1 LIMIT 1',
            'INSERT ibl_box_scores_teams VALUES( (\'2026-02-02\',\'Rookies\',1,40,41,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,35,80,4,0,30,20,60,50,60,30,20,10,20))',
            'INSERT ibl_box_scores_teams VALUES( (\'2026-02-02\',\'Sophomores\',1,40,41,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,20,801,50,0,30,20,10,30,40,20,10,10,2))',
            'INSERT ibl_box_scores_teams VALUES( (\'2026-02-02\',\'Sophomores\',1,40,41,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,3,207,0,30,2,2,5,4,5,3,2,1,2))',
            'INSERT ibl_box_scores_teams VALUES( (\'2026-02-02\',\'Sophomores\',1,40,41,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,28,70,12,0,2,2,1,2,3,1,1,1,2))',
        ], $this->opDigest($db));
    }

    public function testRisingStarsImportPinsMessage(): void
    {
        $db = new MockDatabase();
        $db->setReturnTrue(true);
        $db->onQuery('SELECT visitor_q1_points', []);

        $seasonStub = self::createStub(Season::class);
        $seasonStub->method('getLastBoxScoreDate')->willReturn('2026-02-10');
        $repository = new BoxscoreRepository($db);
        $processor = new BoxscoreProcessor($db, $repository, $seasonStub);

        $risingStarsLine = $this->buildGameInfoLine();
        $data = $this->buildAllStarBlocks($this->buildGameRecord($risingStarsLine), null);
        $result = $processor->processAllStarGamesData($data, 2026);

        $this->assertSame('Rising Stars Game: inserted (4 lines).', $result['messages'][0] ?? 'WRONG');
    }

    public function testRisingStarsAlreadyExists(): void
    {
        $db = new MockDatabase();
        $db->setReturnTrue(true);
        // findTeamBoxscore → matching → scoresMatch → hasNullTeamId returns false → 'skip'
        $db->onQuery('COUNT\(\*\) AS cnt', [['cnt' => 0]]);
        $db->onQuery('SELECT visitor_q1_points', [[
            'visitor_q1_points' => 25, 'visitor_q2_points' => 30, 'visitor_q3_points' => 28,
            'visitor_q4_points' => 27, 'visitor_ot_points' => 0,
            'home_q1_points' => 22, 'home_q2_points' => 31, 'home_q3_points' => 25,
            'home_q4_points' => 30, 'home_ot_points' => 0,
        ]]);

        $seasonStub = self::createStub(Season::class);
        $seasonStub->method('getLastBoxScoreDate')->willReturn('2026-02-10');
        $repository = new BoxscoreRepository($db);
        $processor = new BoxscoreProcessor($db, $repository, $seasonStub);

        $risingStarsLine = $this->buildGameInfoLine();
        $data = $this->buildAllStarBlocks($risingStarsLine, null);
        $result = $processor->processAllStarGamesData($data, 2026);

        $this->assertSame('Rising Stars Game: already exists, skipped.', $result['messages'][0] ?? 'WRONG');
        $insertOrDelete = array_filter(
            $this->opDigest($db),
            static fn (string $e): bool => str_starts_with($e, 'INSERT') || str_starts_with($e, 'DELETE')
        );
        $this->assertEmpty($insertOrDelete);
    }

    public function testRisingStarsOneTeamTotalOnly(): void
    {
        $db = new MockDatabase();
        $db->setReturnTrue(true);
        $db->onQuery('SELECT visitor_q1_points', []);

        $seasonStub = self::createStub(Season::class);
        $seasonStub->method('getLastBoxScoreDate')->willReturn('2026-02-10');
        $repository = new BoxscoreRepository($db);
        $processor = new BoxscoreProcessor($db, $repository, $seasonStub);

        // Build a record with only the visitor team total slot populated
        $gameInfoLine = $this->buildGameInfoLine();
        $teamTotalLine = str_pad(str_pad('Rookies', 16) . '  ' . '000000' . '00' . '3508004003020605060302010203', 53);
        $emptySlot = str_repeat(' ', 53);
        $record = $gameInfoLine;
        $record .= $teamTotalLine;  // slot 0: visitor team total
        for ($i = 1; $i < 30; $i++) {
            $record .= $emptySlot;  // all other slots empty
        }
        $record = str_pad($record, 2000);
        $data = $record;  // 2000-byte Rising Stars input (no 1MB header for all-star path)

        $processor->processAllStarGamesData($data, 2026);

        $this->assertSame([
            'SELECT ibl_box_scores_teams WHERE game_date = \'2026-02-02\' AND visitor_teamid = 40 AND home_teamid = 41 AND game_of_that_day = 1 LIMIT 1',
            'INSERT ibl_box_scores_teams VALUES( (\'2026-02-02\',\'Rookies\',1,40,41,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,35,80,4,0,30,20,60,50,60,30,20,10,20))',
            'INSERT ibl_box_scores_teams VALUES( (\'2026-02-02\',\'Sophomores\',1,40,41,18000,20000,10,5,5,10,25,30,28,27,0,22,31,25,30,0,0,0,0,0,0,0,0,0,0,0,0,0,0))',
        ], $this->opDigest($db));
    }
}
