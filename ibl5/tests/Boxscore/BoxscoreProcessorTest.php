<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\BoxscoreProcessor;
use Boxscore\Contracts\BoxscoreProcessorInterface;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Mocks\MockDatabase;

/**
 * @covers \Boxscore\BoxscoreProcessor
 */
class BoxscoreProcessorTest extends TestCase
{
    private \MockDatabase $mockDb;
    private string|false $previousErrorLog = false;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        // Suppress error logs from Season constructor DB calls
        $this->previousErrorLog = ini_get('error_log') ?: '';
        ini_set('error_log', '/dev/null');
    }

    protected function tearDown(): void
    {
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
            ['Sim' => 1, 'Start Date' => '2025-01-01', 'End Date' => '2025-01-07'],
        ]);

        $processor = new BoxscoreProcessor($this->mockDb);

        $this->assertInstanceOf(BoxscoreProcessorInterface::class, $processor);
    }

    public function testProcessScoFileReturnsErrorForMissingFile(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->mockDb->setMockData([
            ['name' => 'Current Season Phase', 'value' => 'Regular Season'],
            ['Sim' => 1, 'Start Date' => '2025-01-01', 'End Date' => '2025-01-07'],
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
            ['Sim' => 0, 'Start Date' => '', 'End Date' => ''],
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
            ['Sim' => 1, 'Start Date' => '2025-01-01', 'End Date' => '2025-01-07'],
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
            ['Sim' => 0, 'Start Date' => '', 'End Date' => ''],
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
            ['Sim' => 1, 'Start Date' => '2025-01-01', 'End Date' => '2025-01-07'],
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
            ['Sim' => 1, 'Start Date' => '2025-01-01', 'End Date' => '2025-01-07'],
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
              . '00'                            // visitor team (0-indexed → tid 1)
              . '01'                            // home team (0-indexed → tid 2)
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
        $boxscore = \Boxscore::withGameInfoLine($gameInfoLine, 2003, 'Regular Season/Playoffs', 'olympics');

        $this->assertSame('08', $boxscore->gameMonth);
        $this->assertSame(2003, $boxscore->gameYear);
        $this->assertStringStartsWith('2003-08-', $boxscore->gameDate);
    }

    public function testOlympicsLeagueUsesEndingYear(): void
    {
        $gameInfoLine = $this->buildGameInfoLine(2, 5); // month offset 2 = December in IBL
        $boxscore = \Boxscore::withGameInfoLine($gameInfoLine, 2005, 'Regular Season/Playoffs', 'olympics');

        $this->assertSame(2005, $boxscore->gameYear);
        $this->assertSame('08', $boxscore->gameMonth);
    }

    public function testIblLeaguePreservesOriginalDateLogic(): void
    {
        // Month offset 1 = November (10+1=11), should be in starting year
        $gameInfoLine = $this->buildGameInfoLine(1, 10);
        $boxscore = \Boxscore::withGameInfoLine($gameInfoLine, 2026, 'Regular Season/Playoffs', 'ibl');

        $this->assertSame('11', $boxscore->gameMonth);
        $this->assertSame(2025, $boxscore->gameYear); // Starting year for November
    }

    public function testDefaultLeagueParameterUsesIblLogic(): void
    {
        // Default (no league param) should behave like IBL
        $gameInfoLine = $this->buildGameInfoLine(1, 10);
        $boxscore = \Boxscore::withGameInfoLine($gameInfoLine, 2026, 'Regular Season/Playoffs');

        $this->assertSame('11', $boxscore->gameMonth);
        $this->assertSame(2025, $boxscore->gameYear);
    }

    public function testOlympicsLeagueIsCaseInsensitive(): void
    {
        $gameInfoLine = $this->buildGameInfoLine(0, 1);
        $boxscore = \Boxscore::withGameInfoLine($gameInfoLine, 2003, 'Regular Season/Playoffs', 'Olympics');

        $this->assertSame('08', $boxscore->gameMonth);
        $this->assertSame(2003, $boxscore->gameYear);
    }
}
