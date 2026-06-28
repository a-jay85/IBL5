<?php

declare(strict_types=1);

namespace Tests\Draft;

use PHPUnit\Framework\TestCase;
use Draft\DraftController;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Season\Season;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * DraftControllerTest - Tests for draft controller
 *
 * Tests:
 * - Controller instantiation
 * - Interface compliance
 * - Validation flow (handleDraftSelection and submitSelection)
 */
class DraftControllerTest extends TestCase
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
        $this->mockCommonRepository = self::createStub(TeamIdentityRepositoryInterface::class);

        $this->mockSeason = self::createStub(Season::class);
        $this->mockSeason->beginningYear = 2024;
        $this->mockSeason->endingYear = 2025;
        $this->mockSeason->phase = 'Draft';
    }

    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    public function testControllerCanBeInstantiated(): void
    {
        $controller = new DraftController(
            $this->mockDb,
            $this->mockCommonRepository,
            $this->mockSeason
        );

        $this->assertIsObject($controller);
    }

    // ============================================
    // VALIDATION TESTS
    // ============================================

    public function testHandleDraftSelectionReturnsErrorForNullPlayerName(): void
    {
        $controller = new DraftController(
            $this->mockDb,
            $this->mockCommonRepository,
            $this->mockSeason
        );

        $result = $controller->handleDraftSelection('Test Team', null, 1, 1);

        $this->assertIsString($result);
        $this->assertStringContainsString('select a player', $result);
    }

    public function testHandleDraftSelectionReturnsErrorForEmptyPlayerName(): void
    {
        $controller = new DraftController(
            $this->mockDb,
            $this->mockCommonRepository,
            $this->mockSeason
        );

        $result = $controller->handleDraftSelection('Test Team', '', 1, 1);

        $this->assertIsString($result);
    }

    public function testSubmitSelectionWithoutPlayerKeyReturnsValidationError(): void
    {
        $controller = new DraftController($this->mockDb, $this->mockCommonRepository, $this->mockSeason);
        $result = $controller->submitSelection(['teamname' => 'Test Team', 'draft_round' => '1', 'draft_pick' => '1']);
        $this->assertStringContainsString('select a player', $result);
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleControllersCanBeInstantiated(): void
    {
        $controller1 = new DraftController(
            $this->mockDb,
            $this->mockCommonRepository,
            $this->mockSeason
        );
        $controller2 = new DraftController(
            $this->mockDb,
            $this->mockCommonRepository,
            $this->mockSeason
        );

        $this->assertNotSame($controller1, $controller2);
    }
}
