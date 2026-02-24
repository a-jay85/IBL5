<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use PHPUnit\Framework\TestCase;
use DepthChartEntry\DepthChartEntryApiHandler;

/**
 * Tests for DepthChartEntryApiHandler
 *
 * Validates display mode whitelist and split parameter support.
 */
class DepthChartEntryApiHandlerTest extends TestCase
{
    private \MockDatabase $mockDb;
    private object $mockMysqliDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->setupMockMysqliDb();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    private function setupMockMysqliDb(): void
    {
        $mockDb = $this->mockDb;

        $this->mockMysqliDb = new class($mockDb) extends \mysqli {
            private \MockDatabase $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct(\MockDatabase $mockDb)
            {
                $this->mockDb = $mockDb;
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $query): \MockPreparedStatement|false
            {
                return new \MockPreparedStatement($this->mockDb, $query);
            }

            #[\ReturnTypeWillChange]
            public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): \mysqli_result|bool
            {
                $result = $this->mockDb->sql_query($query);
                if ($result instanceof \MockDatabaseResult) {
                    return false;
                }
                return (bool) $result;
            }

            public function real_escape_string(string $string): string
            {
                return addslashes($string);
            }
        };

        $GLOBALS['mysqli_db'] = $this->mockMysqliDb;
    }

    public function testCanBeInstantiated(): void
    {
        $handler = new DepthChartEntryApiHandler($this->mockMysqliDb);

        $this->assertInstanceOf(DepthChartEntryApiHandler::class, $handler);
    }

    public function testValidDisplayModesIncludePlayoffs(): void
    {
        $reflection = new \ReflectionClass(DepthChartEntryApiHandler::class);
        $constant = $reflection->getReflectionConstant('VALID_DISPLAY_MODES');
        $this->assertNotFalse($constant);

        /** @var list<string> $modes */
        $modes = $constant->getValue();
        $this->assertContains('playoffs', $modes);
    }

    public function testValidDisplayModesIncludeSplit(): void
    {
        $reflection = new \ReflectionClass(DepthChartEntryApiHandler::class);
        $constant = $reflection->getReflectionConstant('VALID_DISPLAY_MODES');
        $this->assertNotFalse($constant);

        /** @var list<string> $modes */
        $modes = $constant->getValue();
        $this->assertContains('split', $modes);
    }

    public function testValidDisplayModesMatchTeamApiHandler(): void
    {
        $dceReflection = new \ReflectionClass(DepthChartEntryApiHandler::class);
        $dceConstant = $dceReflection->getReflectionConstant('VALID_DISPLAY_MODES');
        $this->assertNotFalse($dceConstant);

        $teamReflection = new \ReflectionClass(\Team\TeamApiHandler::class);
        $teamConstant = $teamReflection->getReflectionConstant('VALID_DISPLAY_MODES');
        $this->assertNotFalse($teamConstant);

        /** @var list<string> $dceModes */
        $dceModes = $dceConstant->getValue();
        /** @var list<string> $teamModes */
        $teamModes = $teamConstant->getValue();

        sort($dceModes);
        sort($teamModes);

        $this->assertSame($teamModes, $dceModes, 'DepthChartEntry and Team API handlers should support the same display modes');
    }

    public function testValidDisplayModesContainsAllExpectedModes(): void
    {
        $reflection = new \ReflectionClass(DepthChartEntryApiHandler::class);
        $constant = $reflection->getReflectionConstant('VALID_DISPLAY_MODES');
        $this->assertNotFalse($constant);

        /** @var list<string> $modes */
        $modes = $constant->getValue();

        $expected = ['ratings', 'total_s', 'avg_s', 'per36mins', 'chunk', 'playoffs', 'contracts', 'split'];
        sort($expected);
        sort($modes);

        $this->assertSame($expected, $modes);
    }
}
