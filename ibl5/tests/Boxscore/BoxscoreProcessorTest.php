<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\Boxscore;
use Boxscore\BoxscoreProcessor;
use Boxscore\BoxscoreRepository;
use Boxscore\Contracts\BoxscoreProcessorInterface;
use PHPUnit\Framework\TestCase;
use Season\Season;
use Tests\Integration\Mocks\MockDatabase;

/**
 * Test subclass exposing protected methods for unit testing.
 */
class TestableBoxscoreProcessor extends BoxscoreProcessor
{
    public function exposedProcessGameUpsert(Boxscore $boxscoreGameInfo): string
    {
        return $this->processGameUpsert($boxscoreGameInfo);
    }

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
    private \MockDatabase $mockDb;
    private string|false $previousErrorLog = false;

    /** @var list<string> Temp files to clean up */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        // Suppress error logs from Season constructor DB calls
        $this->previousErrorLog = ini_get('error_log') ?: '';
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

    public function testImplementsInterface(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->mockDb->setMockData([
            ['name' => 'Current Season Phase', 'value' => 'Regular Season'],
            ['Sim' => 1, 'start_date' => '2025-01-01', 'end_date' => '2025-01-07'],
        ]);

        $processor = new BoxscoreProcessor($this->mockDb);

        $this->assertInstanceOf(BoxscoreProcessorInterface::class, $processor);
    }

    public function testProcessScoFileReturnsErrorForMissingFile(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->mockDb->setMockData([
            ['name' => 'Current Season Phase', 'value' => 'Regular Season'],
            ['Sim' => 1, 'start_date' => '2025-01-01', 'end_date' => '2025-01-07'],
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
            ['Sim' => 0, 'start_date' => '', 'end_date' => ''],
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
            ['Sim' => 1, 'start_date' => '2025-01-01', 'end_date' => '2025-01-07'],
        ]);

        $processor = new BoxscoreProcessor($this->mockDb);
        $result = $processor->processScoFile($tmpFile, 1991, 'HEAT');

        unlink($tmpFile);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('1990-1991 HEAT', $result['messages'][0]);
    }

    public function testProcessScoFilePreseasonSkipsSimDates(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sco_test_');
        $this->assertNotFalse($tmpFile);
        file_put_contents($tmpFile, str_repeat("\0", 1000000));

        $this->mockDb->setReturnTrue(true);
        $this->mockDb->setMockData([
            ['name' => 'Current Season Phase', 'value' => 'Preseason'],
            ['Sim' => 0, 'start_date' => '', 'end_date' => ''],
        ]);

        $processor = new BoxscoreProcessor($this->mockDb);
        $result = $processor->processScoFile($tmpFile, 2025, 'Preseason');

        unlink($tmpFile);

        $this->assertTrue($result['success']);
        $lastMessage = end($result['messages']);
        $this->assertStringContainsString('Preseason', $lastMessage);
        $this->assertStringContainsString('not updated', $lastMessage);
    }

    public function testConstructorAcceptsOptionalLeagueContext(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->mockDb->setMockData([
            ['name' => 'Current Season Phase', 'value' => 'Regular Season'],
            ['Sim' => 1, 'start_date' => '2025-01-01', 'end_date' => '2025-01-07'],
        ]);

        $leagueContext = $this->createStub(\League\LeagueContext::class);
        $processor = new BoxscoreProcessor($this->mockDb, null, null, $leagueContext);

        $this->assertInstanceOf(BoxscoreProcessorInterface::class, $processor);
    }

    public function testOlympicsContextSkipsAllStarGames(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->mockDb->setMockData([
            ['name' => 'Current Season Phase', 'value' => 'Regular Season'],
            ['Sim' => 1, 'start_date' => '2025-01-01', 'end_date' => '2025-01-07'],
        ]);

        $olympicsContext = $this->createStub(\League\LeagueContext::class);
        $olympicsContext->method('isOlympics')->willReturn(true);
        $olympicsContext->method('getTableName')->willReturnArgument(0);

        $processor = new BoxscoreProcessor($this->mockDb, null, null, $olympicsContext);
        $result = $processor->processAllStarGames('/nonexistent/file.sco', 2025);

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
        $mockDb = new \MockDatabase();
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
        $mockDb = new \MockDatabase();
        $mockDb->setReturnTrue(true);
        // findTeamBoxscore returns matching quarter scores
        // buildGameInfoLine defaults: visitor Q scores = 025,030,028,027,000 = 110; home = 022,031,025,030,000 = 108
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', [[
            'visitorQ1points' => 25, 'visitorQ2points' => 30, 'visitorQ3points' => 28,
            'visitorQ4points' => 27, 'visitorOTpoints' => 0,
            'homeQ1points' => 22, 'homeQ2points' => 31, 'homeQ3points' => 25,
            'homeQ4points' => 30, 'homeOTpoints' => 0,
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
        $mockDb = new \MockDatabase();
        $mockDb->setReturnTrue(true);
        // findTeamBoxscore returns different scores (all zeros — clearly different)
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', [[
            'visitorQ1points' => 0, 'visitorQ2points' => 0, 'visitorQ3points' => 0,
            'visitorQ4points' => 0, 'visitorOTpoints' => 0,
            'homeQ1points' => 0, 'homeQ2points' => 0, 'homeQ3points' => 0,
            'homeQ4points' => 0, 'homeOTpoints' => 0,
        ]]);

        $repository = new BoxscoreRepository($mockDb);
        $season = new Season($mockDb);
        $processor = new TestableBoxscoreProcessor($mockDb, $repository, $season);

        $boxscore = Boxscore::withGameInfoLine($this->buildGameInfoLine(3, 10), 2026, 'Regular Season/Playoffs');

        $this->assertSame('update', $processor->exposedProcessGameUpsert($boxscore));
    }

    public function testProcessGameUpsertReturnsUpdateWhenScoresMatchButNullTeamId(): void
    {
        $mockDb = new \MockDatabase();
        $mockDb->setReturnTrue(true);
        // findTeamBoxscore returns matching scores
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', [[
            'visitorQ1points' => 25, 'visitorQ2points' => 30, 'visitorQ3points' => 28,
            'visitorQ4points' => 27, 'visitorOTpoints' => 0,
            'homeQ1points' => 22, 'homeQ2points' => 31, 'homeQ3points' => 25,
            'homeQ4points' => 30, 'homeOTpoints' => 0,
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
        $mockDb = new \MockDatabase();
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
        $seasonStub = $this->createStub(Season::class);
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
        $mockDb = new \MockDatabase();
        $mockDb->setReturnTrue(true);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = $this->createStub(Season::class);
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
        $mockDb = new \MockDatabase();
        $mockDb->setReturnTrue(true);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = $this->createStub(Season::class);
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
        $mockDb = new \MockDatabase();
        $mockDb->setReturnTrue(true);
        // findTeamBoxscore returns null (game not in DB)
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = $this->createStub(Season::class);
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

    public function testProcessScoFileSkipsMatchingGame(): void
    {
        $mockDb = new \MockDatabase();
        $mockDb->setReturnTrue(true);
        // findTeamBoxscore returns matching scores
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', [[
            'visitorQ1points' => 25, 'visitorQ2points' => 30, 'visitorQ3points' => 28,
            'visitorQ4points' => 27, 'visitorOTpoints' => 0,
            'homeQ1points' => 22, 'homeQ2points' => 31, 'homeQ3points' => 25,
            'homeQ4points' => 30, 'homeOTpoints' => 0,
        ]]);
        $mockDb->onQuery('(?s)COUNT.*teamid IS NULL', [['cnt' => 0]]);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = $this->createStub(Season::class);
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
        $mockDb = new \MockDatabase();
        $mockDb->setReturnTrue(true);
        // findTeamBoxscore returns different scores
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', [[
            'visitorQ1points' => 0, 'visitorQ2points' => 0, 'visitorQ3points' => 0,
            'visitorQ4points' => 0, 'visitorOTpoints' => 0,
            'homeQ1points' => 0, 'homeQ2points' => 0, 'homeQ3points' => 0,
            'homeQ4points' => 0, 'homeOTpoints' => 0,
        ]]);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = $this->createStub(Season::class);
        $seasonStub->lastSimEndDate = '';

        $scoFile = $this->buildScoFileWithOneGame($this->buildGameInfoLine(3, 10));
        $processor = new BoxscoreProcessor($mockDb, $repository, $seasonStub);

        $result = $processor->processScoFile($scoFile, 2026, 'Regular Season/Playoffs', skipSimDates: true);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['gamesUpdated']);
        $this->assertSame(0, $result['gamesInserted']);
    }

    // --- processAllStarGames tests ---

    public function testProcessAllStarGamesSkipsBeforeCutoff(): void
    {
        $mockDb = new \MockDatabase();
        $mockDb->setReturnTrue(true);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = $this->createStub(Season::class);
        // Last box score date is before the All-Star cutoff (Feb 4)
        $seasonStub->method('getLastBoxScoreDate')->willReturn('2026-01-15');

        $processor = new BoxscoreProcessor($mockDb, $repository, $seasonStub);
        $result = $processor->processAllStarGames('/nonexistent/file.sco', 2026);

        $this->assertTrue($result['success']);
        $this->assertSame('All-Star Weekend not yet reached', $result['skipped']);
    }

    public function testProcessAllStarGamesSkipsWhenNoBoxScoreDateExists(): void
    {
        $mockDb = new \MockDatabase();
        $mockDb->setReturnTrue(true);

        $repository = new BoxscoreRepository($mockDb);
        $seasonStub = $this->createStub(Season::class);
        $seasonStub->method('getLastBoxScoreDate')->willReturn('');

        $processor = new BoxscoreProcessor($mockDb, $repository, $seasonStub);
        $result = $processor->processAllStarGames('/nonexistent/file.sco', 2026);

        $this->assertTrue($result['success']);
        $this->assertSame('All-Star Weekend not yet reached', $result['skipped']);
    }

    // --- Helper methods ---

    /**
     * Build a minimal .sco file with one game at the correct 1MB offset.
     *
     * The game contains 2 team total rows (playerID=0) and 2 player rows.
     * This is enough to exercise the insert/skip/update code paths.
     */
    private function buildScoFileWithOneGame(string $gameInfoLine): string
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
        $gameData = str_pad($gameData, 2000);

        // Write file: 1MB padding + game data
        $tmpFile = tempnam(sys_get_temp_dir(), 'sco_test_');
        $this->assertNotFalse($tmpFile);
        file_put_contents($tmpFile, str_repeat("\0", 1000000) . $gameData);
        $this->tempFiles[] = $tmpFile;

        return $tmpFile;
    }
}
