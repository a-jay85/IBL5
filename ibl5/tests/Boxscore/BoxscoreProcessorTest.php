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
    private MockDatabase $mockDb;
    private string|false $previousErrorLog = false;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
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
}
