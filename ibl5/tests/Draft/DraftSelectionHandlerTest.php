<?php

declare(strict_types=1);

namespace Tests\Draft;

use PHPUnit\Framework\TestCase;
use Draft\DraftSelectionHandler;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Season\Season;
use Tests\WideUnit\Mocks\MockDatabase;

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
    private MockDatabase $mockDb;
    private TeamIdentityRepositoryInterface $mockCommonRepository;
    private Season $mockSeason;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $GLOBALS['mysqli_db'] = $this->mockDb;
        $this->setupMockDependencies();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    private function setupMockDependencies(): void
    {
        // Stub CommonMysqliRepository (no expectations needed)
        $this->mockCommonRepository = self::createStub(TeamIdentityRepositoryInterface::class);

        // Mock Season object
        $this->mockSeason = self::createStub(Season::class);
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
            $this->mockCommonRepository,
            $this->mockSeason
        );

        $this->assertInstanceOf(DraftSelectionHandler::class, $handler);
    }

    public function testHandlerImplementsCorrectInterface(): void
    {
        $handler = new DraftSelectionHandler(
            $this->mockDb,
            $this->mockCommonRepository,
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
            $this->mockCommonRepository,
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
            $this->mockCommonRepository,
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
            $this->mockCommonRepository,
            $this->mockSeason
        );
        $handler2 = new DraftSelectionHandler(
            $this->mockDb,
            $this->mockCommonRepository,
            $this->mockSeason
        );

        $this->assertInstanceOf(DraftSelectionHandler::class, $handler1);
        $this->assertInstanceOf(DraftSelectionHandler::class, $handler2);
        $this->assertNotSame($handler1, $handler2);
    }
}
