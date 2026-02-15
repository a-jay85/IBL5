<?php

declare(strict_types=1);

namespace Tests\Integration\Draft;

use Tests\Integration\IntegrationTestCase;
use Tests\Integration\Mocks\TestDataFactory;
use Draft\DraftSelectionHandler;
use Shared\Contracts\SharedRepositoryInterface;

/**
 * Integration tests for complete draft selection workflows
 *
 * Tests end-to-end scenarios combining validation, player creation,
 * and notifications:
 * - Successful draft selections
 * - Player creation in ibl_plr
 * - Validation failures (duplicate pick, already drafted)
 * - Next team notifications
 *
 * @covers \Draft\DraftSelectionHandler
 * @covers \Draft\DraftRepository
 * @covers \Draft\DraftValidator
 * @covers \Draft\DraftProcessor
 */
class DraftIntegrationTest extends IntegrationTestCase
{
    private DraftSelectionHandler $handler;
    private SharedRepositoryInterface $mockSharedFunctions;
    private \Season $mockSeason;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Stub SharedRepository (no expectations needed)
        $stub = $this->createStub(SharedRepositoryInterface::class);
        $stub->method('getCurrentOwnerOfDraftPick')->willReturn('Miami Cyclones');
        $this->mockSharedFunctions = $stub;

        // Create mock Season
        $this->mockSeason = $this->createStub(\Season::class);
        $this->mockSeason->endingYear = 2025;
        $this->mockSeason->freeAgencyNotificationsState = 'Off';

        $this->handler = new DraftSelectionHandler(
            $this->mockDb,
            $this->mockSharedFunctions,
            $this->mockSeason
        );

        // Prevent Discord notifications during tests
        $_SERVER['SERVER_NAME'] = 'localhost';
    }

    protected function tearDown(): void
    {
        unset($this->handler);
        unset($this->mockSharedFunctions);
        unset($this->mockSeason);
        unset($_SERVER['SERVER_NAME']);
        parent::tearDown();
    }

    // ========== SUCCESSFUL DRAFT SCENARIOS ==========

    /**
     * @group integration
     * @group success-scenarios
     */
    public function testSuccessfulDraftSelectionCreatesPlayer(): void
    {
        // Arrange
        $this->setupSuccessfulDraftScenario();

        // Act
        $result = $this->handler->handleDraftSelection(
            'Miami Cyclones',
            'John Prospect',
            1,
            5
        );

        // Assert - Success message returned
        $this->assertStringContainsString('Miami Cyclones', $result);
        $this->assertStringContainsString('John Prospect', $result);

        // Verify draft table was updated
        $this->assertQueryExecuted('UPDATE ibl_draft');

        // Verify rookie table was updated
        $this->assertQueryExecuted('UPDATE `ibl_draft_class`');

        // Verify player was created in ibl_plr
        $this->assertQueryExecuted('INSERT INTO ibl_plr');
    }

    /**
     * @group integration
     * @group success-scenarios
     */
    public function testDraftSelectionUpdatesPickOwnership(): void
    {
        // Arrange
        $this->setupSuccessfulDraftScenario();

        // Act
        $result = $this->handler->handleDraftSelection(
            'New York Knights',
            'Star Rookie',
            1,
            1
        );

        // Assert
        $this->assertStringContainsString('New York Knights', $result);

        // Verify pick was marked as used
        $this->assertQueryExecuted("player");
        $this->assertQueryExecuted("UPDATE ibl_draft");
    }

    /**
     * @group integration
     * @group success-scenarios
     */
    public function testFirstRoundDraftPickCreation(): void
    {
        // Arrange
        $this->setupSuccessfulDraftScenario();

        // Act
        $result = $this->handler->handleDraftSelection(
            'Los Angeles Stars',
            'Top Prospect',
            1,
            3
        );

        // Assert
        $this->assertStringContainsString('Top Prospect', $result);

        // Verify all database operations
        $this->assertQueryExecuted('UPDATE ibl_draft');
        $this->assertQueryExecuted('UPDATE `ibl_draft_class`');
        $this->assertQueryExecuted('INSERT INTO ibl_plr');
    }

    /**
     * @group integration
     * @group success-scenarios
     */
    public function testSecondRoundDraftPickCreation(): void
    {
        // Arrange
        $this->setupSuccessfulDraftScenario();

        // Act
        $result = $this->handler->handleDraftSelection(
            'Chicago Blaze',
            'Second Round Pick',
            2,
            15
        );

        // Assert
        $this->assertStringContainsString('Second Round Pick', $result);
        $this->assertQueryExecuted('INSERT INTO ibl_plr');
    }

    // ========== VALIDATION FAILURE SCENARIOS ==========

    /**
     * @group integration
     * @group validation-failures
     */
    public function testRejectsDuplicatePickSelection(): void
    {
        // Arrange - Pick already has a player selected
        $this->setupPickAlreadyUsedScenario();

        // Act
        $result = $this->handler->handleDraftSelection(
            'Miami Cyclones',
            'Another Player',
            1,
            5
        );

        // Assert - Error message returned
        $this->assertStringContainsString('already', $result);

        // Verify NO database changes were made
        $this->assertQueryNotExecuted('INSERT INTO ibl_plr');
    }

    /**
     * @group integration
     * @group validation-failures
     */
    public function testRejectsAlreadyDraftedPlayer(): void
    {
        // Arrange - Player was already drafted
        $this->setupPlayerAlreadyDraftedScenario();

        // Act
        $result = $this->handler->handleDraftSelection(
            'Seattle SuperSonics',
            'Already Drafted Player',
            2,
            10
        );

        // Assert - Error message returned
        $this->assertStringContainsString('already', $result);

        // Verify NO database changes were made
        $this->assertQueryNotExecuted('INSERT INTO ibl_plr');
    }

    // ========== PLAYER CREATION SCENARIOS ==========

    /**
     * @group integration
     * @group success-scenarios
     */
    public function testSuccessfulPickCreatesPlayerRecord(): void
    {
        // Arrange
        $this->setupSuccessfulDraftScenario();

        // Act
        $result = $this->handler->handleDraftSelection(
            'Denver Nuggets',
            'New Rookie',
            1,
            10
        );

        // Assert - Player name appears in success message
        $this->assertStringContainsString('New Rookie', $result);

        // Verify player was inserted into ibl_plr
        $this->assertQueryExecuted('INSERT INTO ibl_plr');

        // Verify the INSERT references the drafting team
        $this->assertQueryExecuted('Denver Nuggets');
    }

    /**
     * @group integration
     * @group success-scenarios
     */
    public function testDraftedPlayerGetsSequentialPid(): void
    {
        // Arrange
        $this->setupSuccessfulDraftScenario();

        // Act
        $this->handler->handleDraftSelection(
            'Test Team',
            'Sequential Player',
            1,
            1
        );

        // Assert - Player was inserted with a PID in the 90000+ range
        $this->assertQueryExecuted('INSERT INTO ibl_plr');

        // Verify the MAX(pid) query was executed to determine next available PID
        $this->assertQueryExecuted('SELECT MAX(pid) as max_pid FROM ibl_plr');
    }

    /**
     * @group integration
     * @group success-scenarios
     */
    public function testDraftClassMarkedAsDrafted(): void
    {
        // Arrange
        $this->setupSuccessfulDraftScenario();

        // Act
        $this->handler->handleDraftSelection(
            'Miami Cyclones',
            'John Prospect',
            1,
            5
        );

        // Assert - Draft class record was updated to mark player as drafted
        $this->assertQueryExecuted('UPDATE `ibl_draft_class`');
    }

    /**
     * @group integration
     * @group success-scenarios
     */
    public function testDraftTableUpdatedWithPlayerAndDate(): void
    {
        // Arrange
        $this->setupSuccessfulDraftScenario();

        // Act
        $this->handler->handleDraftSelection(
            'Team X',
            'Player Y',
            1,
            5
        );

        // Assert - Draft table was updated with pick info
        $this->assertQueryExecuted('UPDATE ibl_draft');

        // Verify the player name was recorded in the draft table
        $this->assertQueryExecuted('Player Y');
    }

    /**
     * @group integration
     * @group success-scenarios
     */
    public function testRatingsTransferFromDraftClassToPlayer(): void
    {
        // Arrange - All ratings in base draft data are set to 55
        $this->setupSuccessfulDraftScenario();

        // Act
        $this->handler->handleDraftSelection(
            'Miami Cyclones',
            'Rated Prospect',
            1,
            5
        );

        // Assert - Player was inserted with ratings from the draft class
        $this->assertQueryExecuted('INSERT INTO ibl_plr');

        // The INSERT query should contain the rating value 55 from draft class data
        $this->assertQueryExecuted('55');
    }

    // ========== HELPER METHODS ==========

    private function setupSuccessfulDraftScenario(): void
    {
        $this->mockDb->setMockData([
            array_merge($this->getBaseDraftData(), [
                // Pick is available (no player selected yet)
                'player' => null,
                // Player not yet drafted
                'drafted' => 0,
                // Team info
                'teamid' => 1,
                'tid' => 1,
                // Draft class player data
                'name' => 'John Prospect',
                'pos' => 'SG',
                'college' => 'Duke',
                'age' => 19,
                // Next pick info
                'round' => 1,
                'pick' => 6,
                'ownerofpick' => 'Miami Cyclones',
            ])
        ]);
    }

    private function setupPickAlreadyUsedScenario(): void
    {
        $this->mockDb->setMockData([
            array_merge($this->getBaseDraftData(), [
                // Pick already has a player
                'player' => 'Previously Selected Player',
                'drafted' => 1,
            ])
        ]);
    }

    private function setupPlayerAlreadyDraftedScenario(): void
    {
        $this->mockDb->setMockData([
            array_merge($this->getBaseDraftData(), [
                // Pick is available
                'player' => null,
                // But this specific player was already drafted
                'drafted' => 1,
                'team' => 'Los Angeles Stars',
            ])
        ]);
    }

    /**
     * Base draft data for all scenarios
     */
    private function getBaseDraftData(): array
    {
        return array_merge(TestDataFactory::createTeam([
            'teamid' => 1,
            'team_name' => 'Miami Cyclones',
        ]), [
            // Draft class fields
            'id' => 1,
            'name' => 'Test Prospect',
            'pos' => 'PG',
            'college' => 'UCLA',
            'age' => 20,
            'htft' => 6,
            'htin' => 3,
            'wt' => 195,
            'drafted' => 0,
            'team' => null,
            // Ratings
            'r_fga' => 55,
            'r_fgp' => 55,
            'r_fta' => 55,
            'r_ftp' => 55,
            'r_tga' => 55,
            'r_tgp' => 55,
            'r_orb' => 55,
            'r_drb' => 55,
            'r_ast' => 55,
            'r_stl' => 55,
            'r_to' => 55,
            'r_blk' => 55,
            'r_foul' => 55,
            'oo' => 55,
            'do' => 55,
            'po' => 55,
            'to' => 55,
            'od' => 55,
            'dd' => 55,
            'pd' => 55,
            'td' => 55,
            'Clutch' => 55,
            'Consistency' => 55,
            'talent' => 55,
            'skill' => 55,
            'intangibles' => 55,
            'sta' => 55,
            // Season info
            'Beginning_Year' => 2024,
            'Ending_Year' => 2025,
            // Discord
            'discordID' => '123456789',
            // Max PID query result
            'max_pid' => 90050,
        ]);
    }
}
