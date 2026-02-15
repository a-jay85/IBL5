<?php

declare(strict_types=1);

namespace Tests\Draft;

use PHPUnit\Framework\TestCase;
use Draft\DraftSelectionHandler;
use Shared\Contracts\SharedRepositoryInterface;

/**
 * DraftSelectionHandlerTest - Tests for draft pick selection handling
 *
 * Tests:
 * - Handler instantiation
 * - Interface compliance
 * - Validation flow
 */
class DraftSelectionHandlerTest extends TestCase
{
    private \MockDatabase $mockDb;
    private object $mockMysqliDb;
    private SharedRepositoryInterface $mockSharedFunctions;
    private \Season $mockSeason;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->setupMockMysqliDb();
        $this->setupMockDependencies();
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
                // Don't call parent::__construct() to avoid real DB connection
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

    private function setupMockDependencies(): void
    {
        // Stub SharedRepository (no expectations needed)
        $stub = $this->createStub(SharedRepositoryInterface::class);
        $stub->method('getCurrentOwnerOfDraftPick')->willReturn('Test Team');
        $this->mockSharedFunctions = $stub;
        
        // Mock Season object
        $this->mockSeason = $this->createStub(\Season::class);
        $this->mockSeason->beginningYear = 2024;
        $this->mockSeason->endingYear = 2025;
        $this->mockSeason->phase = 'Draft';
    }

    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    public function testHandlerCanBeInstantiated(): void
    {
        $handler = new DraftSelectionHandler(
            $this->mockDb,
            $this->mockSharedFunctions,
            $this->mockSeason
        );
        
        $this->assertInstanceOf(DraftSelectionHandler::class, $handler);
    }

    public function testHandlerImplementsCorrectInterface(): void
    {
        $handler = new DraftSelectionHandler(
            $this->mockDb,
            $this->mockSharedFunctions,
            $this->mockSeason
        );
        
        $this->assertInstanceOf(
            \Draft\Contracts\DraftSelectionHandlerInterface::class,
            $handler
        );
    }

    // ============================================
    // VALIDATION TESTS
    // ============================================

    public function testHandleDraftSelectionReturnsErrorForNullPlayerName(): void
    {
        $handler = new DraftSelectionHandler(
            $this->mockDb,
            $this->mockSharedFunctions,
            $this->mockSeason
        );

        $result = $handler->handleDraftSelection('Test Team', null, 1, 1);
        
        $this->assertIsString($result);
        // Should return validation error message containing "didn't select"
        $this->assertStringContainsString('select a player', $result);
    }

    public function testHandleDraftSelectionReturnsErrorForEmptyPlayerName(): void
    {
        $handler = new DraftSelectionHandler(
            $this->mockDb,
            $this->mockSharedFunctions,
            $this->mockSeason
        );

        $result = $handler->handleDraftSelection('Test Team', '', 1, 1);
        
        $this->assertIsString($result);
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleHandlersCanBeInstantiated(): void
    {
        $handler1 = new DraftSelectionHandler(
            $this->mockDb,
            $this->mockSharedFunctions,
            $this->mockSeason
        );
        $handler2 = new DraftSelectionHandler(
            $this->mockDb,
            $this->mockSharedFunctions,
            $this->mockSeason
        );
        
        $this->assertInstanceOf(DraftSelectionHandler::class, $handler1);
        $this->assertInstanceOf(DraftSelectionHandler::class, $handler2);
        $this->assertNotSame($handler1, $handler2);
    }
}
