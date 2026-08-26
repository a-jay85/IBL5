<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\Boxscore;
use Boxscore\BoxscoreProcessor;
use Boxscore\BoxscoreRepository;
use Boxscore\Contracts\BoxscoreProcessorInterface;
use Boxscore\Contracts\ProgressReporterInterface;
use Boxscore\RejectedGame;
use Boxscore\ScheduleMembershipGuard;
use JsbParser\ScoFileParser;
use PHPUnit\Framework\TestCase;
use Season\Season;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Test subclass exposing protected methods for unit testing.
 */
class TestableBoxscoreProcessor extends BoxscoreProcessor
{
    public ?ScheduleMembershipGuard $guardOverride = null;

    protected function makeScheduleGuard(int $seasonEndingYear): ScheduleMembershipGuard
    {
        return $this->guardOverride ?? parent::makeScheduleGuard($seasonEndingYear);
    }

    public function exposedProcessGameUpsert(Boxscore $boxscoreGameInfo): string
    {
        return $this->processGameUpsert($boxscoreGameInfo);
    }

    /**
     * @return list<string>
     */
    public function exposedUpdateSimDates(string $phase): array
    {
        return $this->updateSimDates($phase);
    }
}

/**
 * @covers \Boxscore\BoxscoreProcessor
 */
class BoxscoreProcessorTest extends TestCase
{
    private MockDatabase $mockDb;
    private string|false $previousErrorLog = false;

    /** @var list<string> Temp files to clean up */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        // Suppress error logs from Season constructor DB calls
        $logResult = ini_get('error_log');
        $this->previousErrorLog = $logResult !== false ? $logResult : '';
        ini_set('error_log', '/dev/null');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        if ($this->previousErrorLog !== false) {
            ini_set('error_log', $this->previousErrorLog);
            $this->previousErrorLog = false;
        }
        parent::tearDown();
    }

    public function testProcessScoFileReturnsErrorForMissingFile(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->mockDb->setMockData([
            ['name' => 'Current Season Phase', 'value' => 'Regular Season'],
            ['sim' => 1, 'start_date' => '2025-01-01', 'end_date' => '2025-01-07'],
        ]);

        $processor = new BoxscoreProcessor($this->mockDb);
        $result = $processor->processScoFile('/nonexistent/file.sco', 2025, 'Regular Season');

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['gamesInserted']);
        $this->assertSame(0, $result['gamesUpdated']);
        $this->assertSame(0, $result['gamesSkipped']);
        $this->assertSame(0, $result['linesProcessed']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testProcessScoFileReturnsStructuredResult(): void
    {
        // Create a minimal empty .sco file (1MB of null bytes as header)
        $tmpFile = tempnam(sys_get_temp_dir(), 'sco_test_');
        $this->assertNotFalse($tmpFile);
        // Write just enough to pass the 1MB fseek — actual content is empty so no games parsed
        file_put_contents($tmpFile, str_repeat("\0", 1000000));

        $this->mockDb->setReturnTrue(true);
        $this->mockDb->setMockData([
            ['name' => 'Current Season Phase', 'value' => 'Preseason'],
            ['sim' => 0, 'start_date' => '', 'end_date' => ''],
        ]);

        $processor = new BoxscoreProcessor($this->mockDb);
        $result = $processor->processScoFile($tmpFile, 2025, 'Preseason');

        unlink($tmpFile);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['gamesInserted']);
        $this->assertSame(0, $result['gamesUpdated']);
        $this->assertSame(0, $result['gamesSkipped']);
        $this->assertSame(0, $result['linesProcessed']);
        $this->assertIsArray($result['messages']);
        $this->assertNotEmpty($result['messages']);
    }

    public function testProcessScoFileUsesProvidedSeasonParams(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sco_test_');
        $this->assertNotFalse($tmpFile);
        file_put_contents($tmpFile, str_repeat("\0", 1000000));

        $this->mockDb->setReturnTrue(true);
        $this->mockDb->setMockData([
            ['name' => 'Current Season Phase', 'value' => 'Regular Season'],
            ['sim' => 1, 'start_date' => '2025-01-01', 'end_date' => '2025-01-07'],
        ]);

        $processor = new BoxscoreProcessor($this->mockDb);
        $result = $processor->processScoFile($tmpFile, 1991, 'HEAT');

        unlink($tmpFile);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('1990-1991 HEAT', $result['messages'][0]);
    }

    public function testProcessScoFilePreseasonUpdatesSimDates(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sco_test_');
        $this->assertNotFalse($tmpFile);
        file_put_contents($tmpFile, str_repeat("\0", 1000000));

        $this->mockDb->setReturnTrue(true);
        $this->mockDb->setMockData([
            ['name' => 'Current Season Phase', 'value' => 'Preseason'],
            ['sim' => 0, 'start_date' => '', 'end_date' => ''],
        ]);

        $processor = new BoxscoreProcessor($this->mockDb);
        $result = $processor->processScoFile($tmpFile, 2025, 'Preseason');

        unlink($tmpFile);

        $this->assertTrue($result['success']);
        $lastMessage = end($result['messages']);
        $this->assertStringNotContainsString('not updated', $lastMessage);
    }

    public function testConstructorAcceptsOptionalLeagueContext(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->mockDb->setMockData([
            ['name' => 'Current Season Phase', 'value' => 'Regular Season'],
            ['sim' => 1, 'start_date' => '2025-01-01', 'end_date' => '2025-01-07'],
        ]);

        $leagueContext = self::createStub(\League\LeagueContext::class);
        $processor = new BoxscoreProcessor($this->mockDb, null, null, $leagueContext);

        // Verify the processor works with a league context by processing a missing file
        $result = $processor->processScoFile('/nonexistent/file.sco', 2025, 'Regular Season');
        $this->assertFalse($result['success']);
    }

    public function testOlympicsContextSkipsAllStarGames(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->mockDb->setMockData([
            ['name' => 'Current Season Phase', 'value' => 'Regular Season'],
            ['sim' => 1, 'start_date' => '2025-01-01', 'end_date' => '2025-01-07'],
        ]);

        $olympicsContext = self::createStub(\League\LeagueContext::class);
        $olympicsContext->method('isOlympics')->willReturn(true);
        $olympicsContext->method('getTableName')->willReturnArgument(0);

        $processor = new BoxscoreProcessor($this->mockDb, null, null, $olympicsContext);
        $result = $processor->processAllStarGamesData('dummy data', 2025);

        $this->assertTrue($result['success']);
        $this->assertSame('Olympics context', $result['skipped']);
    }

    // --- Merged from BoxscoreDateMappingTest ---

    /**
     * Build a minimal 58-char game info line for testing.
     *
     * Format: 2-char month offset, 2-char day offset, 2-char game#,
     * 2-char visitor, 2-char home, 5-char attendance, 5-char capacity,
     * then W/L and quarter scores to fill 58 chars total.
     */
    private function buildGameInfoLine(int $monthOffset = 0, int $dayOffset = 14, int $gameOfDay = 0, int $visitorIndex = 0, int $homeIndex = 1): string
    {
        // Month offset (0=Oct), day offset (0=day 1), game#=0, visitor=0, home=1
        $line = sprintf('%02d', $monthOffset)   // month offset from Oct
              . sprintf('%02d', $dayOffset)      // day offset (0-indexed)
              . sprintf('%02d', $gameOfDay)      // game of that day (0-indexed)
              . sprintf('%02d', $visitorIndex)   // visitor team (0-indexed → teamid = index+1)
              . sprintf('%02d', $homeIndex)      // home team (0-indexed → teamid = index+1)
              . '18000'                          // attendance
              . '20000'                          // capacity
              . '1005'                           // visitor wins/losses
              . '0510'                           // home wins/losses
              . '025030028027000'                // visitor quarter scores (5x3 chars)
              . '022031025030000';               // home quarter scores (5x3 chars)

        return $line;
    }

    /**
     * Build a game-info line from a concrete calendar date and teamid triple.
     *
     * Computes the month/day/gotd/team offsets from the given values and
     * self-checks the round-trip via Boxscore::withGameInfoLine to catch any
     * formula mistake at test-authoring time.
     *
     * monthOffset formula: ((month - 10) + 12) % 12
     *   Oct=0, Nov=1, Dec=2, Jan=3, Feb=4, Mar=5, Apr=6, May=7, Jun=8
     */
    private function gameInfoLineForGame(
        string $isoDate,
        int $gameOfThatDay,
        int $visitorTeamid,
        int $homeTeamid,
        int $seasonEndingYear,
    ): string {
        $monthOffset  = (((int) substr($isoDate, 5, 2)) - 10 + 12) % 12;
        $dayOffset    = ((int) substr($isoDate, 8, 2)) - 1;
        $gameOfDay    = $gameOfThatDay - 1;
        $visitorIndex = $visitorTeamid - 1;
        $homeIndex    = $homeTeamid - 1;

        $line = $this->buildGameInfoLine($monthOffset, $dayOffset, $gameOfDay, $visitorIndex, $homeIndex);

        // Self-check: round-trip must recover the original date.
        $probe = Boxscore::withGameInfoLine($line, $seasonEndingYear, 'Regular Season/Playoffs', 'ibl');
        self::assertSame($isoDate, $probe->gameDate, 'gameInfoLineForGame offset arithmetic is wrong');

        return $line;
    }

    public function testOlympicsLeagueMapsAllDatesToAugust(): void
    {
        $gameInfoLine = $this->buildGameInfoLine(0, 14); // month offset 0 = October in IBL
        $boxscore = Boxscore::withGameInfoLine($gameInfoLine, 2003, 'Regular Season/Playoffs', 'olympics');

        $this->assertSame('08', $boxscore->gameMonth);
        $this->assertSame(2003, $boxscore->gameYear);
        $this->assertStringStartsWith('2003-08-', $boxscore->gameDate);
    }

    public function testOlympicsLeagueUsesEndingYear(): void
    {
        $gameInfoLine = $this->buildGameInfoLine(2, 5); // month offset 2 = December in IBL
        $boxscore = Boxscore::withGameInfoLine($gameInfoLine, 2005, 'Regular Season/Playoffs', 'olympics');

        $this->assertSame(2005, $boxscore->gameYear);
        $this->assertSame('08', $boxscore->gameMonth);
    }

    public function testIblLeaguePreservesOriginalDateLogic(): void
    {
        // Month offset 1 = November (10+1=11), should be in starting year
        $gameInfoLine = $this->buildGameInfoLine(1, 10);
        $boxscore = Boxscore::withGameInfoLine($gameInfoLine, 2026, 'Regular Season/Playoffs', 'ibl');

        $this->assertSame('11', $boxscore->gameMonth);
        $this->assertSame(2025, $boxscore->gameYear); // Starting year for November
    }

    public function testDefaultLeagueParameterUsesIblLogic(): void
    {
        // Default (no league param) should behave like IBL
        $gameInfoLine = $this->buildGameInfoLine(1, 10);
        $boxscore = Boxscore::withGameInfoLine($gameInfoLine, 2026, 'Regular Season/Playoffs');

        $this->assertSame('11', $boxscore->gameMonth);
        $this->assertSame(2025, $boxscore->gameYear);
    }

    public function testOlympicsLeagueIsCaseInsensitive(): void
    {
        $gameInfoLine = $this->buildGameInfoLine(0, 1);
        $boxscore = Boxscore::withGameInfoLine($gameInfoLine, 2003, 'Regular Season/Playoffs', 'Olympics');

        $this->assertSame('08', $boxscore->gameMonth);
        $this->assertSame(2003, $boxscore->gameYear);
    }

    // --- processGameUpsert tests ---

    public function testProcessGameUpsertReturnsInsertForNewGame(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        // findTeamBoxscore returns null (no matching game)
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []);

        $repository = new BoxscoreRepository($mockDb);
        $season = new Season($mockDb);
        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $season);

        $boxscore = Boxscore::withGameInfoLine($this->buildGameInfoLine(3, 10), 2026, 'Regular Season/Playoffs');

        $this->assertSame('insert', $processor->exposedProcessGameUpsert($boxscore));
    }

    public function testProcessGameUpsertReturnsSkipWhenScoresMatchAndNoNullTeamId(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        // findTeamBoxscore returns matching quarter scores
        // buildGameInfoLine defaults: visitor Q scores = 025,030,028,027,000 = 110; home = 022,031,025,030,000 = 108
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', [[
            'visitor_q1_points' => 25, 'visitor_q2_points' => 30, 'visitor_q3_points' => 28,
            'visitor_q4_points' => 27, 'visitor_ot_points' => 0,
            'home_q1_points' => 22, 'home_q2_points' => 31, 'home_q3_points' => 25,
            'home_q4_points' => 30, 'home_ot_points' => 0,
        ]]);
        // hasNullTeamIdPlayerBoxscores returns false (cnt=0)
        $mockDb->onQuery('(?s)COUNT.*teamid IS NULL', [['cnt' => 0]]);

        $repository = new BoxscoreRepository($mockDb);
        $season = new Season($mockDb);
        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $season);

        $boxscore = Boxscore::withGameInfoLine($this->buildGameInfoLine(3, 10), 2026, 'Regular Season/Playoffs');

        $this->assertSame('skip', $processor->exposedProcessGameUpsert($boxscore));
    }

    public function testProcessGameUpsertReturnsUpdateWhenScoresDiffer(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        // findTeamBoxscore returns different scores (all zeros — clearly different)
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', [[
            'visitor_q1_points' => 0, 'visitor_q2_points' => 0, 'visitor_q3_points' => 0,
            'visitor_q4_points' => 0, 'visitor_ot_points' => 0,
            'home_q1_points' => 0, 'home_q2_points' => 0, 'home_q3_points' => 0,
            'home_q4_points' => 0, 'home_ot_points' => 0,
        ]]);

        $repository = new BoxscoreRepository($mockDb);
        $season = new Season($mockDb);
        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $season);

        $boxscore = Boxscore::withGameInfoLine($this->buildGameInfoLine(3, 10), 2026, 'Regular Season/Playoffs');

        $this->assertSame('update', $processor->exposedProcessGameUpsert($boxscore));
    }

    public function testProcessGameUpsertReturnsUpdateWhenScoresMatchButNullTeamId(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        // findTeamBoxscore returns matching scores
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', [[
            'visitor_q1_points' => 25, 'visitor_q2_points' => 30, 'visitor_q3_points' => 28,
            'visitor_q4_points' => 27, 'visitor_ot_points' => 0,
            'home_q1_points' => 22, 'home_q2_points' => 31, 'home_q3_points' => 25,
            'home_q4_points' => 30, 'home_ot_points' => 0,
        ]]);
        // hasNullTeamIdPlayerBoxscores returns true (cnt=1)
        $mockDb->onQuery('(?s)COUNT.*teamid IS NULL', [['cnt' => 1]]);

        $repository = new BoxscoreRepository($mockDb);
        $season = new Season($mockDb);
        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $season);

        $boxscore = Boxscore::withGameInfoLine($this->buildGameInfoLine(3, 10), 2026, 'Regular Season/Playoffs');

        $this->assertSame('update', $processor->exposedProcessGameUpsert($boxscore));
    }

    // --- updateSimDates tests ---

    public function testUpdateSimDatesAdvancesFromLastEndDate(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);

        $repository = new BoxscoreRepository($mockDb);
        $season = new Season($mockDb);
        $season->lastSimEndDate = '2025-12-01';
        $season->lastSimNumber = 3;
        // getLastBoxScoreDate() returns lastSimEndDate on the mock, so set it to the new date
        // We need to manipulate this carefully — Mock Season returns lastSimEndDate from getLastBoxScoreDate()
        // So we create two processor instances: one for setup, one after updating lastSimEndDate
        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $season);

        // The mock Season's getLastBoxScoreDate() returns $this->lastSimEndDate
        // We need it to return a *different* date. Override it by changing the property AFTER construction
        // but getLastBoxScoreDate returns lastSimEndDate. So we need a different approach.
        // Actually: setLastSimDatesArray is called which updates lastSimEndDate. But getLastBoxScoreDate
        // returns the CURRENT lastSimEndDate. We need it to return '2025-12-08'.

        // The solution: set lastSimEndDate to what getLastBoxScoreDate should return,
        // but also track the "old" value. Looking at updateSimDates():
        // - $newSimEndDate = $this->season->getLastBoxScoreDate() -> returns lastSimEndDate
        // - $this->season->lastSimEndDate is compared to $newSimEndDate
        // So if lastSimEndDate = '2025-12-08', the comparison lastSimEndDate !== newSimEndDate
        // is false (same value), and we get "haven't been added". That's wrong.

        // We need getLastBoxScoreDate() to return something different from lastSimEndDate.
        // But the mock returns lastSimEndDate! This means we can't test the advancement path
        // with the current mock Season. Let's create a custom Season mock.

        // Actually, the simplest fix: create a stub for Season with custom behavior.
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '2025-12-01';
        $seasonStub->lastSimNumber = 3;
        $seasonStub->lastSimStartDate = '2025-11-25';
        $seasonStub->method('getLastBoxScoreDate')->willReturn('2025-12-08');
        $seasonStub->method('setLastSimDatesArray')->willReturn(1);

        $processor2 = new TestableBoxscoreProcessor($mockDb, $repository, $seasonStub);
        $messages = $processor2->exposedUpdateSimDates('Regular Season/Playoffs');

        $found = false;
        foreach ($messages as $msg) {
            if (str_contains($msg, '2025-12-02') && str_contains($msg, '2025-12-08')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected message about box scores from 2025-12-02 through 2025-12-08');
    }

    public function testUpdateSimDatesFirstSimUsesFirstBoxScoreDate(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';
        $seasonStub->method('getFirstBoxScoreDate')->willReturn('2025-11-01');
        $seasonStub->method('getLastBoxScoreDate')->willReturn('2025-11-08');
        $seasonStub->method('setLastSimDatesArray')->willReturn(1);

        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $seasonStub);
        $messages = $processor->exposedUpdateSimDates('Regular Season/Playoffs');

        $found = false;
        foreach ($messages as $msg) {
            if (str_contains($msg, '2025-11-01') && str_contains($msg, '2025-11-08')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected message about box scores from 2025-11-01 through 2025-11-08');
    }

    public function testUpdateSimDatesNoChangeWhenDatesUnchanged(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '2025-12-01';
        $seasonStub->lastSimStartDate = '2025-11-25';
        $seasonStub->method('getLastBoxScoreDate')->willReturn('2025-12-01');

        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $seasonStub);
        $messages = $processor->exposedUpdateSimDates('Regular Season/Playoffs');

        $found = false;
        foreach ($messages as $msg) {
            if (str_contains($msg, "haven't been added")) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, "Expected message about new box scores haven't been added");
    }

    // --- processScoFile end-to-end tests ---

    public function testProcessScoFileInsertsNewGame(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        // findTeamBoxscore returns null (game not in DB)
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';
        $seasonStub->method('getLastBoxScoreDate')->willReturn('2026-01-11');
        $seasonStub->method('getFirstBoxScoreDate')->willReturn('2026-01-11');
        $seasonStub->method('setLastSimDatesArray')->willReturn(1);

        $scoFile = $this->buildScoFileWithOneGame($this->buildGameInfoLine(3, 10));
        $processor = new BoxscoreProcessor($mockDb, $repository, $seasonStub);

        $result = $processor->processScoFile($scoFile, 2026, 'Regular Season/Playoffs', skipSimDates: true);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['gamesInserted']);
        $this->assertSame(0, $result['gamesSkipped']);
        $this->assertGreaterThan(0, $result['linesProcessed']);
    }

    /**
     * JSB writes the final record of a .sco without its 352-byte trailing padding, so the
     * file ends 1,648 bytes past the last whole record. Bounding the parse loop on
     * RECORD_SIZE silently dropped that game — for an end-of-season file, the
     * championship-clinching one.
     */
    public function testProcessScoFileImportsUnpaddedTrailingRecord(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $trailingRecord = $this->buildGameRecord($this->buildGameInfoLine(3, 10), padToRecordSize: false);
        $this->assertSame(ScoFileParser::GAME_PAYLOAD_SIZE, strlen($trailingRecord));

        $scoFile = $this->buildScoFile([$trailingRecord]);
        $processor = new BoxscoreProcessor($mockDb, $repository, $seasonStub);

        $result = $processor->processScoFile($scoFile, 2026, 'Regular Season/Playoffs', skipSimDates: true);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['gamesInserted']);
        $this->assertGreaterThan(0, $result['linesProcessed']);
    }

    /**
     * The realistic shape: whole records followed by a short final one. Proves the offset
     * arithmetic still advances by RECORD_SIZE for the padded records.
     */
    public function testProcessScoFileImportsBothPaddedAndUnpaddedTrailingRecords(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $scoFile = $this->buildScoFile([
            $this->buildGameRecord($this->buildGameInfoLine(3, 10)),
            $this->buildGameRecord($this->buildGameInfoLine(3, 11), padToRecordSize: false),
        ]);
        $processor = new BoxscoreProcessor($mockDb, $repository, $seasonStub);

        $result = $processor->processScoFile($scoFile, 2026, 'Regular Season/Playoffs', skipSimDates: true);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['gamesInserted']);
    }

    /**
     * A short trailing run of blank bytes is now long enough to enter the loop; it must
     * still import nothing.
     */
    public function testProcessScoFileIgnoresBlankTrailingRemainder(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $scoFile = $this->buildScoFile([
            $this->buildGameRecord($this->buildGameInfoLine(3, 10)),
            str_repeat(' ', ScoFileParser::GAME_PAYLOAD_SIZE),
        ]);
        $processor = new BoxscoreProcessor($mockDb, $repository, $seasonStub);

        $result = $processor->processScoFile($scoFile, 2026, 'Regular Season/Playoffs', skipSimDates: true);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['gamesInserted']);
        $this->assertSame(0, $result['gamesUpdated']);
    }

    public function testProcessScoFileSkipsMatchingGame(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        // findTeamBoxscore returns matching scores
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', [[
            'visitor_q1_points' => 25, 'visitor_q2_points' => 30, 'visitor_q3_points' => 28,
            'visitor_q4_points' => 27, 'visitor_ot_points' => 0,
            'home_q1_points' => 22, 'home_q2_points' => 31, 'home_q3_points' => 25,
            'home_q4_points' => 30, 'home_ot_points' => 0,
        ]]);
        $mockDb->onQuery('(?s)COUNT.*teamid IS NULL', [['cnt' => 0]]);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $scoFile = $this->buildScoFileWithOneGame($this->buildGameInfoLine(3, 10));
        $processor = new BoxscoreProcessor($mockDb, $repository, $seasonStub);

        $result = $processor->processScoFile($scoFile, 2026, 'Regular Season/Playoffs', skipSimDates: true);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['gamesInserted']);
        $this->assertSame(1, $result['gamesSkipped']);
    }

    public function testProcessScoFileUpdatesChangedGame(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        // findTeamBoxscore returns different scores
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', [[
            'visitor_q1_points' => 0, 'visitor_q2_points' => 0, 'visitor_q3_points' => 0,
            'visitor_q4_points' => 0, 'visitor_ot_points' => 0,
            'home_q1_points' => 0, 'home_q2_points' => 0, 'home_q3_points' => 0,
            'home_q4_points' => 0, 'home_ot_points' => 0,
        ]]);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $scoFile = $this->buildScoFileWithOneGame($this->buildGameInfoLine(3, 10));
        $processor = new BoxscoreProcessor($mockDb, $repository, $seasonStub);

        $result = $processor->processScoFile($scoFile, 2026, 'Regular Season/Playoffs', skipSimDates: true);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['gamesUpdated']);
        $this->assertSame(0, $result['gamesInserted']);
    }

    // --- processAllStarGames tests ---

    public function testProcessAllStarGamesDataSkipsBeforeCutoff(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->method('getLastBoxScoreDate')->willReturn('2026-01-15');

        $processor = new BoxscoreProcessor($mockDb, $repository, $seasonStub);
        $result = $processor->processAllStarGamesData('dummy data', 2026);

        $this->assertTrue($result['success']);
        $this->assertSame('All-Star Weekend not yet reached', $result['skipped']);
    }

    public function testProcessAllStarGamesDataSkipsWhenNoBoxScoreDateExists(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->method('getLastBoxScoreDate')->willReturn('');

        $processor = new BoxscoreProcessor($mockDb, $repository, $seasonStub);
        $result = $processor->processAllStarGamesData('dummy data', 2026);

        $this->assertTrue($result['success']);
        $this->assertSame('All-Star Weekend not yet reached', $result['skipped']);
    }

    public function testProcessScoDataReturnsErrorForTooShortData(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $processor = new BoxscoreProcessor($mockDb, $repository, $seasonStub);
        $result = $processor->processScoData('too short', 2026, 'Regular Season');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('too short', $result['error']);
    }

    public function testProcessScoDataInsertsNewGame(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';
        $seasonStub->method('getLastBoxScoreDate')->willReturn('2026-01-11');
        $seasonStub->method('getFirstBoxScoreDate')->willReturn('2026-01-11');
        $seasonStub->method('setLastSimDatesArray')->willReturn(1);

        $scoFile = $this->buildScoFileWithOneGame($this->buildGameInfoLine(3, 10));
        $data = file_get_contents($scoFile);
        $this->assertNotFalse($data);

        $processor = new BoxscoreProcessor($mockDb, $repository, $seasonStub);
        $result = $processor->processScoData($data, 2026, 'Regular Season/Playoffs', skipSimDates: true);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['gamesInserted']);
        $this->assertSame(0, $result['gamesSkipped']);
        $this->assertGreaterThan(0, $result['linesProcessed']);
    }

    /**
     * The schedule-membership guard rejects games absent from ibl_schedule.
     *
     * The phantom-game incident (07-08_36_playoffs.zip) showed that processScoData
     * was inserting games regardless of ibl_schedule membership. The guard added
     * in Phase 4 flips this: an unscheduled triple is rejected and counted, but
     * the run still returns success=true (never-abort contract).
     *
     * 2008-04-05 visitor=21 @ home=17 gotd=1: absent from ibl_schedule.
     */
    public function testProcessScoDataRejectsGameAbsentFromSchedule(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $scoFile = $this->buildScoFileWithGames([
            $this->gameInfoLineForGame('2008-04-05', 1, 21, 17, 2008),
        ]);
        $data = file_get_contents($scoFile);
        $this->assertNotFalse($data);

        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $seasonStub);
        // Non-empty schedule that omits the 2008-04-05 21@17 triple
        $processor->guardOverride = new ScheduleMembershipGuard(2008, ['2008-04-01' => [1 => [2 => true]]], []);
        $result = $processor->processScoData($data, 2008, 'Regular Season/Playoffs', skipSimDates: true);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['gamesInserted']);
        $this->assertSame(1, $result['gamesRejected']);
        $this->assertCount(1, $result['rejectedGames']);
        $this->assertSame(RejectedGame::REASON_NOT_IN_SCHEDULE, $result['rejectedGames'][0]->reason);
    }

    /**
     * The whitelist in ScheduleMembershipGuard (teamids 40/41/50/51) ensures
     * All-Star games from the main loop are still imported even with the guard active.
     *
     * 2008-02-03 visitor=50 @ home=51 gotd=1.
     * The schedule index has no 50@51 entry — the exempt rule, not the fixture, is
     * why these games pass through.
     */
    public function testProcessScoDataStillImportsAllStarTeamidsFromMainLoop(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $scoFile = $this->buildScoFileWithGames([
            $this->gameInfoLineForGame('2008-02-03', 1, 50, 51, 2008),
        ]);
        $data = file_get_contents($scoFile);
        $this->assertNotFalse($data);

        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $seasonStub);
        // Non-empty schedule omitting 50@51 — the exempt rule, not a missing fixture, allows import
        $processor->guardOverride = new ScheduleMembershipGuard(2008, ['2008-04-01' => [1 => [2 => true]]], []);
        $result = $processor->processScoData($data, 2008, 'Regular Season/Playoffs', skipSimDates: true);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['gamesInserted']);
        $this->assertSame(0, $result['gamesRejected']);
    }

    // --- Schedule guard tests (Phase 4) ---

    /**
     * The never-abort contract: valid and invalid games in a single run.
     *
     * Three records ordered: scheduled, unscheduled, scheduled.
     * The third record proves the loop continued past the rejected second.
     */
    public function testProcessScoDataImportsValidGamesAndRejectsInvalidOnesInOneRun(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $scoFile = $this->buildScoFileWithGames([
            $this->gameInfoLineForGame('2008-04-01', 1, 1, 2, 2008),  // scheduled
            $this->gameInfoLineForGame('2008-04-05', 1, 21, 17, 2008), // NOT scheduled
            $this->gameInfoLineForGame('2008-04-03', 1, 3, 4, 2008),   // scheduled
        ]);
        $data = file_get_contents($scoFile);
        $this->assertNotFalse($data);

        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $seasonStub);
        $processor->guardOverride = new ScheduleMembershipGuard(2008, [
            '2008-04-01' => [1 => [2 => true]],
            '2008-04-03' => [3 => [4 => true]],
        ], []);
        $result = $processor->processScoData($data, 2008, 'Regular Season/Playoffs', skipSimDates: true);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['gamesInserted']);
        $this->assertSame(1, $result['gamesRejected']);
        $this->assertCount(1, $result['rejectedGames']);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function testProcessScoDataRejectsDuplicateTripleAtDifferentGameOfThatDay(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        // Triple is scheduled, but already stored at gotd=1; incoming game has gotd=4
        $scoFile = $this->buildScoFileWithGames([
            $this->gameInfoLineForGame('2008-04-01', 4, 1, 2, 2008),
        ]);
        $data = file_get_contents($scoFile);
        $this->assertNotFalse($data);

        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $seasonStub);
        $processor->guardOverride = new ScheduleMembershipGuard(
            2008,
            ['2008-04-01' => [1 => [2 => true]]],
            ['2008-04-01' => [1 => [2 => [1]]]]  // already stored at gotd=1
        );
        $result = $processor->processScoData($data, 2008, 'Regular Season/Playoffs', skipSimDates: true);

        $this->assertSame(1, $result['gamesRejected']);
        $this->assertSame(RejectedGame::REASON_DUPLICATE_TRIPLE, $result['rejectedGames'][0]->reason);
    }

    /**
     * Negative path: guard built from an empty schedule fails open.
     *
     * A season with no ibl_schedule rows must not have all games rejected.
     */
    public function testProcessScoDataFailsOpenAndWarnsWhenScheduleIsEmpty(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $scoFile = $this->buildScoFileWithGames([
            $this->gameInfoLineForGame('2008-04-05', 1, 21, 17, 2008),
        ]);
        $data = file_get_contents($scoFile);
        $this->assertNotFalse($data);

        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $seasonStub);
        $processor->guardOverride = new ScheduleMembershipGuard(2008, [], []);  // empty — guard disabled
        $result = $processor->processScoData($data, 2008, 'Regular Season/Playoffs', skipSimDates: true);

        $this->assertSame(0, $result['gamesRejected']);
        $this->assertSame(1, $result['gamesInserted']);

        $found = false;
        foreach ($result['messages'] as $message) {
            if (preg_match('/Schedule guard disabled/i', $message) === 1) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected a "Schedule guard disabled" warning message');
    }

    /**
     * Boundary: the too-short early-return carries gamesRejected/rejectedGames keys.
     *
     * BoxscoreView must never dereference a missing key on the error path.
     */
    public function testProcessScoDataTooShortStillReturnsRejectKeys(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $processor = new BoxscoreProcessor($mockDb, $repository, $seasonStub);
        $result = $processor->processScoData('too short', 2026, 'Regular Season', skipSimDates: true);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['gamesRejected']);
        $this->assertSame([], $result['rejectedGames']);
    }

    /**
     * A rejected game performs no SELECT, DELETE, or INSERT against ibl_box_scores.
     */
    public function testRejectedGameNeverTouchesTheDatabase(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $scoFile = $this->buildScoFileWithGames([
            $this->gameInfoLineForGame('2008-04-05', 1, 21, 17, 2008),
        ]);
        $data = file_get_contents($scoFile);
        $this->assertNotFalse($data);

        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $seasonStub);
        // Non-empty schedule that excludes this triple
        $processor->guardOverride = new ScheduleMembershipGuard(2008, ['2008-04-01' => [1 => [2 => true]]], []);

        $mockDb->clearQueries();
        $processor->processScoData($data, 2008, 'Regular Season/Playoffs', skipSimDates: true);

        $queries = $mockDb->getExecutedQueries();
        $boxscoreInserts = array_filter($queries, static fn (string $q): bool => preg_match('/INSERT INTO.*ibl_box_scores/i', $q) === 1);
        $boxscoreDeletes = array_filter($queries, static fn (string $q): bool => preg_match('/DELETE FROM.*ibl_box_scores/i', $q) === 1);
        $this->assertCount(0, $boxscoreInserts, 'Rejected game must not INSERT to ibl_box_scores');
        $this->assertCount(0, $boxscoreDeletes, 'Rejected game must not DELETE from ibl_box_scores');
    }

    // --- ProgressReporter seam tests ---

    public function testProcessorUsesInjectedProgressReporterAndDoesNotFlushOnNoOp(): void
    {
        $spy = new class implements ProgressReporterInterface {
            /** @var list<int> */
            public array $counts = [];
            public function report(int $processedCount): void { $this->counts[] = $processedCount; }
        };

        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []); // new game -> insert

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $scoFile = $this->buildScoFileWithOneGame($this->buildGameInfoLine(3, 10));
        $data = file_get_contents($scoFile);
        self::assertNotFalse($data);
        $processor = new BoxscoreProcessor($mockDb, $repository, $seasonStub, null, $spy);

        $result = $processor->processScoData($data, 2026, 'Regular Season/Playoffs', skipSimDates: true);

        self::assertTrue($result['success']);
        self::assertSame(1, $result['gamesInserted']);
        self::assertSame([1], $spy->counts); // report() called once per processed game; no flush side effect (spy is no-op)
    }

    public function testDefaultConstructionWiresFlushProgressReporterAndRunsGame(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []); // new game -> insert

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $scoFile = $this->buildScoFileWithOneGame($this->buildGameInfoLine(3, 10));
        $data = file_get_contents($scoFile);
        self::assertNotFalse($data);
        $processor = new BoxscoreProcessor($mockDb, $repository, $seasonStub); // no 5th arg — defaults to FlushProgressReporter

        $result = $processor->processScoData($data, 2026, 'Regular Season/Playoffs', skipSimDates: true);

        self::assertTrue($result['success']);
        self::assertSame(1, $result['gamesInserted']);
    }

    // ── Phase 8 — date window (detector B) tests ──────────────────────────────

    /**
     * Helper: build a guard that fails open (empty schedule → all games accepted)
     * but whose scheduleDateWindow() returns a custom window.
     * This lets us test the window tally independently of the rejection gate.
     */
    /**
     * A real guard whose schedule index spans the given dates for matchup 1@2.
     *
     * scheduleDateWindow() folds these keys, so [min(dates), max(dates)] is the
     * window under test. Built from the real class rather than a subclass: an
     * override would let the guard report a window it has no index for, a state
     * production can never reach.
     */
    private function makeGuardWithScheduleDates(string ...$dates): ScheduleMembershipGuard
    {
        /** @var array<string, array<int, array<int, true>>> $index */
        $index = [];
        foreach ($dates as $date) {
            $index[$date] = [1 => [2 => true]];
        }

        return new ScheduleMembershipGuard(2008, $index, []);
    }

    public function testWarnsWhenDecodedDatesFallOutsideScheduleWindow(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        // Game dated 2007-11-05 (regular season month, not exempt).
        $scoFile = $this->buildScoFileWithGames([
            $this->gameInfoLineForGame('2007-11-05', 1, 1, 2, 2008),
        ]);
        $data = file_get_contents($scoFile);
        self::assertNotFalse($data);

        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $seasonStub);
        // Window covers only April 2008 — November 2007 is before it.
        $processor->guardOverride = $this->makeGuardWithScheduleDates('2008-04-01', '2008-06-30');

        $result = $processor->processScoData($data, 2008, 'Regular Season/Playoffs', skipSimDates: true);

        self::assertTrue($result['success']);
        self::assertGreaterThan(0, $result['outOfWindowGames'] ?? 0);

        $hasWindowWarning = false;
        foreach ($result['messages'] as $msg) {
            if (str_contains($msg, 'outside the 2008 schedule window')) {
                $hasWindowWarning = true;
            }
        }
        self::assertTrue($hasWindowWarning, 'Expected a date-window WARNING message');
    }

    public function testNoWindowWarningWhenAllDatesAreInsideWindow(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $scoFile = $this->buildScoFileWithGames([
            $this->gameInfoLineForGame('2008-04-15', 1, 1, 2, 2008),
        ]);
        $data = file_get_contents($scoFile);
        self::assertNotFalse($data);

        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $seasonStub);
        // Game is on 2008-04-15, which is inside [2008-04-01, 2008-06-30] and is
        // itself scheduled, so the guard accepts it and the window check sees it.
        $processor->guardOverride = $this->makeGuardWithScheduleDates('2008-04-01', '2008-04-15', '2008-06-30');

        $result = $processor->processScoData($data, 2008, 'Regular Season/Playoffs', skipSimDates: true);

        self::assertSame(0, $result['outOfWindowGames'] ?? 0);
        foreach ($result['messages'] as $msg) {
            self::assertStringNotContainsString('outside the 2008 schedule window', $msg);
        }
    }

    public function testWindowWarningIgnoresAllStarAndOffScheduleMonths(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        // All-Star game (teamids 50@51) in February (month 02) — exempt by teamid
        // and a preseason game (month offset for August → outside-window month 8) — exempt by month
        $scoFile = $this->buildScoFileWithGames([
            $this->gameInfoLineForGame('2008-02-03', 1, 50, 51, 2008), // All-Star (exempt)
            $this->gameInfoLineForGame('2008-09-05', 1,  1,  2, 2008), // Preseason (month 9, OFF_SCHEDULE_MONTHS)
        ]);
        $data = file_get_contents($scoFile);
        self::assertNotFalse($data);

        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $seasonStub);
        // Window is April–June; both games are outside it — but both should be exempt.
        $processor->guardOverride = $this->makeGuardWithScheduleDates('2008-04-01', '2008-06-30');

        $result = $processor->processScoData($data, 2008, 'Regular Season/Playoffs', skipSimDates: true);

        self::assertSame(0, $result['outOfWindowGames'] ?? 0);
    }

    public function testWindowWarningSkippedWhenScheduleIndexIsEmpty(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $scoFile = $this->buildScoFileWithGames([
            $this->gameInfoLineForGame('2007-11-05', 1, 1, 2, 2008),
        ]);
        $data = file_get_contents($scoFile);
        self::assertNotFalse($data);

        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $seasonStub);
        // Empty schedule → scheduleDateWindow() returns null → window check skipped.
        $processor->guardOverride = new ScheduleMembershipGuard(2008, [], []);

        $result = $processor->processScoData($data, 2008, 'Regular Season/Playoffs', skipSimDates: true);

        self::assertSame(0, $result['outOfWindowGames'] ?? 0);
        foreach ($result['messages'] as $msg) {
            self::assertStringNotContainsString('outside the', $msg);
        }
    }

    public function testResultCarriesOperatingSeasonKeysOnShortDataError(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);

        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $seasonStub);
        // Data too short to contain even the header.
        $result = $processor->processScoData('', 2008, 'Regular Season/Playoffs', skipSimDates: true);

        self::assertFalse($result['success']);
        self::assertArrayHasKey('operatingSeasonEndingYear', $result);
        self::assertArrayHasKey('operatingSeasonPhase', $result);
        self::assertSame(2008, $result['operatingSeasonEndingYear']);
        self::assertSame('Regular Season/Playoffs', $result['operatingSeasonPhase']);
    }

    // --- Phase 6.7 tests: sourceArchive threading and scheduleGuardEnabled ----

    public function testSourceArchiveIsThreadedThroughToResultKey(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $scoFile = $this->buildScoFileWithGames([
            $this->gameInfoLineForGame('2008-04-05', 1, 21, 17, 2008),
        ]);
        $data = file_get_contents($scoFile);
        self::assertNotFalse($data);

        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $seasonStub);
        $processor->guardOverride = new ScheduleMembershipGuard(2008, ['2008-04-01' => [1 => [2 => true]]], []);

        $result = $processor->processScoData($data, 2008, 'Regular Season/Playoffs', skipSimDates: true, sourceArchive: '07-08_36_playoffs.zip');

        self::assertTrue($result['success']);
        self::assertSame('07-08_36_playoffs.zip', $result['sourceArchive']);
    }

    public function testProcessScoFileDefaultsSourceArchiveToBasename(): void
    {
        // Build a valid .sco file then copy it to a path with a known basename.
        $srcFile = $this->buildScoFileWithGames([]);
        $namedFile = sys_get_temp_dir() . '/07-08_36_playoffs.zip.sco';
        copy($srcFile, $namedFile);
        $this->tempFiles[] = $namedFile;

        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $seasonStub);
        $result = $processor->processScoFile($namedFile, 2008, 'Regular Season/Playoffs', skipSimDates: true);

        self::assertTrue($result['success']);
        self::assertSame('07-08_36_playoffs.zip.sco', $result['sourceArchive']);
    }

    public function testSourceArchiveNullDoesNotAffectAcceptOrReject(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $scoFile = $this->buildScoFileWithGames([
            $this->gameInfoLineForGame('2008-04-05', 1, 21, 17, 2008),
        ]);
        $data = file_get_contents($scoFile);
        self::assertNotFalse($data);

        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $seasonStub);
        $processor->guardOverride = new ScheduleMembershipGuard(2008, ['2008-04-01' => [1 => [2 => true]]], []);

        $result = $processor->processScoData($data, 2008, 'Regular Season/Playoffs', skipSimDates: true, sourceArchive: null);

        self::assertTrue($result['success']);
        self::assertSame(1, $result['gamesRejected']);
        self::assertNull($result['sourceArchive']);
    }

    public function testResultCarriesScheduleGuardEnabledOnBothReturns(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $seasonStub);

        // Early return path (data too short).
        $shortResult = $processor->processScoData("\x00\x00\x00", 2008, 'Regular Season/Playoffs', skipSimDates: true);
        self::assertFalse($shortResult['success']);
        self::assertArrayHasKey('scheduleGuardEnabled', $shortResult);
        self::assertIsBool($shortResult['scheduleGuardEnabled']);

        // Normal success path.
        $scoFile = $this->buildScoFileWithGames([]);
        $data = file_get_contents($scoFile);
        self::assertNotFalse($data);
        $successResult = $processor->processScoData($data, 2008, 'Regular Season/Playoffs', skipSimDates: true);
        self::assertTrue($successResult['success']);
        self::assertArrayHasKey('scheduleGuardEnabled', $successResult);
        self::assertIsBool($successResult['scheduleGuardEnabled']);
    }

    // --- Helper methods ---

    /**
     * Build a minimal .sco file with one game at the correct 1MB offset.
     *
     * The game contains 2 team total rows (playerID=0) and 2 player rows.
     * This is enough to exercise the insert/skip/update code paths.
     */
    /** @param string[] $gameInfoLines */
    private function buildScoFileWithGames(array $gameInfoLines): string
    {
        $records = array_map(fn (string $line): string => $this->buildGameRecord($line), $gameInfoLines);

        return $this->buildScoFile($records);
    }

    private function buildScoFileWithOneGame(string $gameInfoLine): string
    {
        return $this->buildScoFileWithGames([$gameInfoLine]);
    }

    /**
     * Build a single game record.
     *
     * @param bool $padToRecordSize When false, the record stops at GAME_PAYLOAD_SIZE (1,648 bytes)
     *                              with no trailing padding — exactly how JSB writes the final
     *                              record of a .sco file.
     */
    private function buildGameRecord(string $gameInfoLine, bool $padToRecordSize = true): string
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
        $playerLine = substr(str_pad($playerLine, ScoFileParser::PLAYER_SLOT_SIZE), 0, ScoFileParser::PLAYER_SLOT_SIZE);
        $teamTotalLine = substr(str_pad($teamTotalLine, ScoFileParser::PLAYER_SLOT_SIZE), 0, ScoFileParser::PLAYER_SLOT_SIZE);

        // Slot 15: home team total
        $homeTeamTotal = str_pad('Home Total', 16)
            . '  '
            . '000000'
            . '00'
            . '3207003002020504050302010203';
        $homeTeamTotal = substr(str_pad($homeTeamTotal, ScoFileParser::PLAYER_SLOT_SIZE), 0, ScoFileParser::PLAYER_SLOT_SIZE);

        // Slot 16: home player
        $homePlayer = str_pad('Home Player', 16)
            . 'SG'
            . '200002'
            . '28'
            . '0701200020201020301010102';
        $homePlayer = substr(str_pad($homePlayer, ScoFileParser::PLAYER_SLOT_SIZE), 0, ScoFileParser::PLAYER_SLOT_SIZE);

        // Build 30 slots: fill unused slots with spaces
        $emptySlot = str_repeat(' ', ScoFileParser::PLAYER_SLOT_SIZE);
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

        if ($padToRecordSize) {
            $gameData = str_pad($gameData, ScoFileParser::RECORD_SIZE);
        }

        return $gameData;
    }

    /**
     * Write a .sco file: the 1MB metadata header followed by the given game records.
     *
     * @param list<string> $records
     */
    private function buildScoFile(array $records): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sco_test_');
        $this->assertNotFalse($tmpFile);
        file_put_contents($tmpFile, str_repeat("\0", ScoFileParser::HEADER_OFFSET_BYTES) . implode('', $records));
        $this->tempFiles[] = $tmpFile;

        return $tmpFile;
    }

    /**
     * §13.2 wiring lock: when a BoxscoreRepository is passed explicitly, the processor
     * uses it — it does NOT silently build a second one from its own $db connection.
     *
     * Mechanism: makeScheduleGuard() calls ScheduleMembershipGuard::fromRepository(),
     * which calls fetchScheduledGameIndex() and fetchBoxscoreGameOfThatDayIndex() on
     * $this->repository. We give the explicit repo a distinct MockDatabase (dbA) and
     * the processor a different one (dbB). After processScoData() returns (even on the
     * "data too short" early path, which runs AFTER makeScheduleGuard()), dbA must show
     * the ibl_schedule query while dbB must not — proving the guard used the passed repo.
     */
    public function testExplicitRepositoryIsUsedInsteadOfBuildingItsOwn(): void
    {
        // dbA backs the explicit repository; dbB backs the processor's own $db.
        $dbA = new MockDatabase();
        $dbB = new MockDatabase();

        // The guard needs fetchScheduledGameIndex (ibl_schedule) and
        // fetchBoxscoreGameOfThatDayIndex (ibl_box_scores_teams) to return arrays.
        $dbA->onQuery('ibl_schedule', []);
        $dbA->onQuery('ibl_box_scores_teams', []);

        $repoA = new BoxscoreRepository($dbA);
        $seasonStub = self::createStub(Season::class);
        $seasonStub->endingYear = 2008;
        $seasonStub->phase = 'Regular Season/Playoffs';
        $seasonStub->lastSimEndDate = '';

        $processor = new TestableBoxscoreProcessor($dbB, $repoA, $seasonStub);

        // Empty data triggers the "data too short" return — but makeScheduleGuard()
        // fires unconditionally before that check, so the repo is always called.
        $processor->processScoData('', 2008, 'Regular Season/Playoffs', skipSimDates: true);

        $queriesOnRepoDb = $dbA->getExecutedQueries();
        $queriesOnProcessorDb = $dbB->getExecutedQueries();

        // dbA must have received the ibl_schedule query from the guard.
        $scheduleQueryFound = false;
        foreach ($queriesOnRepoDb as $q) {
            if (str_contains($q, 'ibl_schedule')) {
                $scheduleQueryFound = true;
                break;
            }
        }
        self::assertTrue($scheduleQueryFound, 'Expected the explicit repository\'s DB to receive the ibl_schedule query from makeScheduleGuard()');

        // dbB (processor's own $db) must NOT have received the ibl_schedule query,
        // proving a second repository was not silently constructed from $dbB.
        $scheduleQueryOnProcessorDb = false;
        foreach ($queriesOnProcessorDb as $q) {
            if (str_contains($q, 'ibl_schedule')) {
                $scheduleQueryOnProcessorDb = true;
                break;
            }
        }
        self::assertFalse($scheduleQueryOnProcessorDb, 'Processor\'s own $db must not receive an ibl_schedule query — that would indicate a second BoxscoreRepository was constructed');
    }
}
