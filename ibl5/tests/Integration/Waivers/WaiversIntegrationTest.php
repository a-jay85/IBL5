<?php

declare(strict_types=1);

namespace Tests\Integration\Waivers;

use Tests\Integration\IntegrationTestCase;
use Tests\Integration\Mocks\TestDataFactory;
use Waivers\WaiversRepository;
use Waivers\WaiversProcessor;
use Waivers\WaiversValidator;
use Season;

/**
 * Integration tests for complete waiver wire workflows
 *
 * Tests end-to-end scenarios combining validation, contract determination,
 * timing calculations, and database persistence:
 * - Successful drop to waivers
 * - Successful add from waivers (with existing contract)
 * - Successful add from waivers (with veteran minimum)
 * - Validation failures (cap violations, roster violations)
 * - Waiver wait time calculations
 * - Contract determination logic
 *
 * @covers \Waivers\WaiversRepository
 * @covers \Waivers\WaiversProcessor
 * @covers \Waivers\WaiversValidator
 */
class WaiversIntegrationTest extends IntegrationTestCase
{
    private WaiversRepository $repository;
    private WaiversProcessor $processor;
    private WaiversValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new WaiversRepository($this->mockDb);
        $this->processor = new WaiversProcessor();
        $this->validator = new WaiversValidator();

        // Prevent Discord notifications during tests
        $_SERVER['SERVER_NAME'] = 'localhost';
    }

    protected function tearDown(): void
    {
        unset($this->repository);
        unset($this->processor);
        unset($this->validator);
        unset($_SERVER['SERVER_NAME']);
        parent::tearDown();
    }

    // ========== DROP TO WAIVERS SUCCESS SCENARIOS ==========

    /**
     * @group integration
     * @group waivers
     * @group drop-success
     */
    public function testSuccessfulDropPlayerToWaivers(): void
    {
        // Arrange
        $playerID = 100;
        $timestamp = time();

        $this->mockDb->setMockData([
            $this->getBasePlayerData(['pid' => $playerID])
        ]);
        $this->mockDb->setAffectedRows(1);

        // Act
        $result = $this->repository->dropPlayerToWaivers($playerID, $timestamp);

        // Assert
        $this->assertTrue($result);
        $this->assertQueryExecuted('UPDATE ibl_plr');
        $this->assertQueryExecuted("ordinal` = '1000'");
        $this->assertQueryExecuted('droptime');
    }

    /**
     * @group integration
     * @group waivers
     * @group drop-success
     */
    public function testDropPlayerValidationPassesWithRoomUnderCap(): void
    {
        // Arrange - Team with 12 players but under hard cap
        $rosterSlots = 3; // 15 - 3 = 12 players
        $totalSalary = 6500; // Under HARD_CAP_MAX (7000)

        // Act
        $result = $this->validator->validateDrop($rosterSlots, $totalSalary);

        // Assert
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    /**
     * @group integration
     * @group waivers
     * @group drop-success
     */
    public function testDropPlayerValidationPassesWithTwoOrFewerRosterSlots(): void
    {
        // Arrange - rosterSlots <= 2 means over 12 players (full/near-full roster)
        // When rosterSlots <= 2 AND over cap, drop is ALLOWED (to get under cap)
        $rosterSlots = 2;
        $totalSalary = 8000; // Over HARD_CAP_MAX

        // Act
        $result = $this->validator->validateDrop($rosterSlots, $totalSalary);

        // Assert - Allowed when rosterSlots <= 2 (logic: 2 > 2 is false, so rule doesn't apply)
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    // ========== DROP TO WAIVERS FAILURE SCENARIOS ==========

    /**
     * @group integration
     * @group waivers
     * @group drop-failure
     */
    public function testDropPlayerValidationFailsWhenRosterSlotsGreaterThanTwoAndOverCap(): void
    {
        // Arrange - rosterSlots > 2 AND over hard cap = FAIL
        // The rule prevents dropping when you have 3+ open slots and are over cap
        $rosterSlots = 3;
        $totalSalary = 7500; // Over HARD_CAP_MAX (7000)

        // Act
        $result = $this->validator->validateDrop($rosterSlots, $totalSalary);

        // Assert
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('12 players', $errors[0]);
        $this->assertStringContainsString('hard cap', $errors[0]);
    }

    /**
     * @group integration
     * @group waivers
     * @group drop-failure
     */
    public function testDropPlayerRepositoryHandlesDatabaseFailure(): void
    {
        // Arrange
        $playerID = 100;
        $timestamp = time();

        // Simulate database failure: set return to false and affected rows to 0
        $this->mockDb->setReturnTrue(false);
        $this->mockDb->setAffectedRows(0);
        $this->suppressErrorLog();

        // Act
        $result = $this->repository->dropPlayerToWaivers($playerID, $timestamp);

        // Assert
        $this->assertFalse($result);
    }

    // ========== ADD FROM WAIVERS SUCCESS SCENARIOS ==========

    /**
     * @group integration
     * @group waivers
     * @group add-success
     */
    public function testSuccessfulAddPlayerWithExistingContract(): void
    {
        // Arrange
        $playerID = 100;
        $team = ['team_name' => 'Miami Cyclones', 'teamid' => 5];
        $contractData = ['hasExistingContract' => true, 'salary' => 500];

        $this->mockDb->setMockData([
            $this->getBasePlayerData(['pid' => $playerID, 'cy1' => 500, 'cy' => 1])
        ]);
        $this->mockDb->setAffectedRows(1);

        // Act
        $result = $this->repository->signPlayerFromWaivers($playerID, $team, $contractData);

        // Assert
        $this->assertTrue($result);
        $this->assertQueryExecuted('UPDATE ibl_plr');
        $this->assertQueryExecuted("ordinal` = '800'");
        $this->assertQueryExecuted('bird` = 0');
        $this->assertQueryExecuted('droptime` = 0');
        $this->assertQueryExecuted("'Miami Cyclones'");
        // Should NOT update contract fields when hasExistingContract = true
        $this->assertQueryNotExecuted('cy` = 0');
    }

    /**
     * @group integration
     * @group waivers
     * @group add-success
     */
    public function testSuccessfulAddPlayerWithVeteranMinimum(): void
    {
        // Arrange
        $playerID = 100;
        $team = ['team_name' => 'Chicago Fire', 'teamid' => 8];
        $contractData = ['hasExistingContract' => false, 'salary' => 103];

        $this->mockDb->setMockData([
            $this->getBasePlayerData(['pid' => $playerID, 'cy1' => 0, 'cy' => 0])
        ]);
        $this->mockDb->setAffectedRows(1);

        // Act
        $result = $this->repository->signPlayerFromWaivers($playerID, $team, $contractData);

        // Assert
        $this->assertTrue($result);
        $this->assertQueryExecuted('UPDATE ibl_plr');
        $this->assertQueryExecuted("ordinal` = '800'");
        $this->assertQueryExecuted('cy` = 0');
        $this->assertQueryExecuted('cyt` = 1');
        $this->assertQueryExecuted("cy1` = 103");
        $this->assertQueryExecuted("'Chicago Fire'");
    }

    /**
     * @group integration
     * @group waivers
     * @group add-success
     */
    public function testAddPlayerValidationPassesWithRosterSpace(): void
    {
        // Arrange
        $playerID = 100;
        $healthyRosterSlots = 5; // Plenty of room
        $totalSalary = 5000;
        $playerSalary = 500;

        // Act
        $result = $this->validator->validateAdd($playerID, $healthyRosterSlots, $totalSalary, $playerSalary);

        // Assert
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    /**
     * @group integration
     * @group waivers
     * @group add-success
     */
    public function testAddPlayerValidationPassesWithVetMinOverCap(): void
    {
        // Arrange - Over cap but signing vet min with <12 healthy players
        $playerID = 100;
        $healthyRosterSlots = 5; // Under 12 healthy players
        $totalSalary = 7000; // At hard cap
        $playerSalary = 103; // Veteran minimum

        // Act
        $result = $this->validator->validateAdd($playerID, $healthyRosterSlots, $totalSalary, $playerSalary);

        // Assert - Allowed because player salary is vet min
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    // ========== ADD FROM WAIVERS FAILURE SCENARIOS ==========

    /**
     * @group integration
     * @group waivers
     * @group add-failure
     */
    public function testAddPlayerValidationFailsWithNoPlayerId(): void
    {
        // Arrange
        $playerID = null;
        $healthyRosterSlots = 5;
        $totalSalary = 5000;
        $playerSalary = 500;

        // Act
        $result = $this->validator->validateAdd($playerID, $healthyRosterSlots, $totalSalary, $playerSalary);

        // Assert
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('select a valid player', $errors[0]);
    }

    /**
     * @group integration
     * @group waivers
     * @group add-failure
     */
    public function testAddPlayerValidationFailsWithZeroPlayerId(): void
    {
        // Arrange
        $playerID = 0;
        $healthyRosterSlots = 5;
        $totalSalary = 5000;
        $playerSalary = 500;

        // Act
        $result = $this->validator->validateAdd($playerID, $healthyRosterSlots, $totalSalary, $playerSalary);

        // Assert
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertStringContainsString('select a valid player', $errors[0]);
    }

    /**
     * @group integration
     * @group waivers
     * @group add-failure
     */
    public function testAddPlayerValidationFailsWithFullRoster(): void
    {
        // Arrange
        $playerID = 100;
        $healthyRosterSlots = 0; // Full roster
        $totalSalary = 5000;
        $playerSalary = 500;

        // Act
        $result = $this->validator->validateAdd($playerID, $healthyRosterSlots, $totalSalary, $playerSalary);

        // Assert
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('full roster', $errors[0]);
    }

    /**
     * @group integration
     * @group waivers
     * @group add-failure
     */
    public function testAddPlayerValidationFailsOverCapWith12PlusHealthyPlayers(): void
    {
        // Arrange - 12+ healthy players and signing would exceed hard cap
        $playerID = 100;
        $healthyRosterSlots = 3; // 15 - 3 = 12 healthy players
        $totalSalary = 6800;
        $playerSalary = 500; // Would push to 7300, over HARD_CAP_MAX

        // Act
        $result = $this->validator->validateAdd($playerID, $healthyRosterSlots, $totalSalary, $playerSalary);

        // Assert
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('12 or more healthy players', $errors[0]);
        $this->assertStringContainsString('hard cap', $errors[0]);
    }

    /**
     * @group integration
     * @group waivers
     * @group add-failure
     */
    public function testAddPlayerValidationFailsOverCapNonVetMin(): void
    {
        // Arrange - Over cap with <12 healthy players but non-vet-min salary
        $playerID = 100;
        $healthyRosterSlots = 5; // Under 12 healthy players
        $totalSalary = 7000; // At hard cap
        $playerSalary = 500; // Over vet min (103)

        // Act
        $result = $this->validator->validateAdd($playerID, $healthyRosterSlots, $totalSalary, $playerSalary);

        // Assert
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('over the hard cap', $errors[0]);
        $this->assertStringContainsString('veteran minimum', $errors[0]);
    }

    // ========== CONTRACT DETERMINATION TESTS ==========

    /**
     * @group integration
     * @group waivers
     * @group contract
     */
    public function testDetermineContractDataWithExistingContract(): void
    {
        // Arrange
        $playerData = TestDataFactory::createPlayer([
            'cy' => 1,
            'cyt' => 3,
            'cy1' => 500,
            'cy2' => 550,
            'cy3' => 600,
            'exp' => 5,
        ]);
        $season = $this->createMockSeason('Regular Season');

        // Act
        $result = $this->processor->determineContractData($playerData, $season);

        // Assert
        $this->assertTrue($result['hasExistingContract']);
        $this->assertEquals(500, $result['salary']);
    }

    /**
     * @group integration
     * @group waivers
     * @group contract
     */
    public function testDetermineContractDataWithNoContract(): void
    {
        // Arrange
        $playerData = TestDataFactory::createPlayer([
            'cy' => 0,
            'cyt' => 0,
            'cy1' => 0,
            'cy2' => 0,
            'cy3' => 0,
            'exp' => 3,
        ]);
        $season = $this->createMockSeason('Regular Season');

        // Act
        $result = $this->processor->determineContractData($playerData, $season);

        // Assert
        $this->assertFalse($result['hasExistingContract']);
        // Veteran minimum for 3 years experience
        $expectedVetMin = \ContractRules::getVeteranMinimumSalary(3);
        $this->assertEquals($expectedVetMin, $result['salary']);
    }

    /**
     * @group integration
     * @group waivers
     * @group contract
     */
    public function testDetermineContractDataDuringFreeAgencyPhase(): void
    {
        // Arrange - During Free Agency, look at next season salary
        $playerData = TestDataFactory::createPlayer([
            'cy' => 1,
            'cyt' => 2,
            'cy1' => 400, // Current season salary
            'cy2' => 450, // Next season salary
            'cy3' => 0,
            'exp' => 4,
        ]);
        $season = $this->createMockSeason('Free Agency');

        // Act
        $result = $this->processor->determineContractData($playerData, $season);

        // Assert - Should use cy2 (next season) during Free Agency
        $this->assertTrue($result['hasExistingContract']);
        $this->assertEquals(450, $result['salary']);
    }

    // ========== WAIVER WAIT TIME TESTS ==========

    /**
     * @group integration
     * @group waivers
     * @group timing
     */
    public function testWaiverWaitTimeReturnsCountdownWhenNotCleared(): void
    {
        // Arrange - Player dropped 12 hours ago
        $currentTime = time();
        $dropTime = $currentTime - (12 * 3600); // 12 hours ago

        // Act
        $result = $this->processor->getWaiverWaitTime($dropTime, $currentTime);

        // Assert - Should show remaining time
        $this->assertStringContainsString('Clears in', $result);
        $this->assertStringContainsString('12 h', $result);
    }

    /**
     * @group integration
     * @group waivers
     * @group timing
     */
    public function testWaiverWaitTimeReturnsEmptyWhenCleared(): void
    {
        // Arrange - Player dropped 25 hours ago (past 24-hour window)
        $currentTime = time();
        $dropTime = $currentTime - (25 * 3600); // 25 hours ago

        // Act
        $result = $this->processor->getWaiverWaitTime($dropTime, $currentTime);

        // Assert - Should be empty (player cleared waivers)
        $this->assertEmpty($result);
    }

    /**
     * @group integration
     * @group waivers
     * @group timing
     */
    public function testWaiverWaitTimeReturnsEmptyAtExactly24Hours(): void
    {
        // Arrange - Player dropped exactly 24 hours ago
        $currentTime = time();
        $dropTime = $currentTime - 86400; // Exactly 24 hours

        // Act
        $result = $this->processor->getWaiverWaitTime($dropTime, $currentTime);

        // Assert - Should be empty (player just cleared)
        $this->assertEmpty($result);
    }

    /**
     * @group integration
     * @group waivers
     * @group timing
     */
    public function testWaiverWaitTimeShowsCorrectMinutesAndSeconds(): void
    {
        // Arrange - Player dropped 23 hours and 30 minutes ago
        $currentTime = time();
        $dropTime = $currentTime - (23 * 3600 + 30 * 60); // 23h 30m ago

        // Act
        $result = $this->processor->getWaiverWaitTime($dropTime, $currentTime);

        // Assert - Should show ~30 minutes remaining
        $this->assertStringContainsString('Clears in', $result);
        $this->assertStringContainsString('0 h', $result);
        $this->assertStringContainsString('30 m', $result);
    }

    // ========== VETERAN MINIMUM CALCULATION TESTS ==========

    /**
     * @group integration
     * @group waivers
     * @group vetmin
     */
    public function testCalculateVeteranMinimumSalaryForRookie(): void
    {
        // Act
        $result = $this->processor->calculateVeteranMinimumSalary(0);

        // Assert
        $expectedVetMin = \ContractRules::getVeteranMinimumSalary(0);
        $this->assertEquals($expectedVetMin, $result);
    }

    /**
     * @group integration
     * @group waivers
     * @group vetmin
     */
    public function testCalculateVeteranMinimumSalaryForVeteran(): void
    {
        // Act
        $result = $this->processor->calculateVeteranMinimumSalary(10);

        // Assert
        $expectedVetMin = \ContractRules::getVeteranMinimumSalary(10);
        $this->assertEquals($expectedVetMin, $result);
    }

    // ========== VALIDATOR ERROR CLEARING TESTS ==========

    /**
     * @group integration
     * @group waivers
     * @group validation
     */
    public function testValidatorClearsErrorsBetweenValidations(): void
    {
        // Arrange - First validation fails
        $this->validator->validateAdd(null, 5, 5000, 500);
        $this->assertNotEmpty($this->validator->getErrors());

        // Act - Second validation passes
        $result = $this->validator->validateAdd(100, 5, 5000, 500);

        // Assert - Errors should be cleared
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    /**
     * @group integration
     * @group waivers
     * @group validation
     */
    public function testManualClearErrors(): void
    {
        // Arrange - Fail validation (rosterSlots > 2 AND over cap)
        $this->validator->validateDrop(3, 8000);
        $this->assertNotEmpty($this->validator->getErrors());

        // Act
        $this->validator->clearErrors();

        // Assert
        $this->assertEmpty($this->validator->getErrors());
    }

    // ========== ADDITIONAL COVERAGE TESTS ==========

    /**
     * @group integration
     * @group waivers
     * @group drop-success
     */
    public function testDropSetsOrdinalAndDroptime(): void
    {
        // Arrange
        $playerID = 200;
        $timestamp = time();

        $this->mockDb->setAffectedRows(1);
        $this->mockDb->setMockData([
            $this->getBasePlayerData(['pid' => 200])
        ]);

        // Act
        $result = $this->repository->dropPlayerToWaivers($playerID, $timestamp);

        // Assert
        $this->assertTrue($result);
        $this->assertQueryExecuted('ordinal');
        $this->assertQueryExecuted('droptime');
        $this->assertQueryExecuted('UPDATE ibl_plr');
    }

    /**
     * @group integration
     * @group waivers
     * @group contract
     */
    public function testClaimAssignsVetMinWhenNoExistingContract(): void
    {
        // Arrange
        $playerData = $this->getBasePlayerData([
            'cy' => 0,
            'cyt' => 0,
            'cy1' => 0,
            'exp' => 5,
        ]);
        $season = $this->createMockSeason('Regular Season');

        // Act
        $result = $this->processor->determineContractData($playerData, $season);

        // Assert
        $this->assertFalse($result['hasExistingContract']);
        $this->assertEquals(\ContractRules::getVeteranMinimumSalary(5), $result['salary']);
    }

    /**
     * @group integration
     * @group waivers
     * @group contract
     */
    public function testClaimKeepsExistingContractWhenPresent(): void
    {
        // Arrange
        $playerData = $this->getBasePlayerData([
            'cy' => 2,
            'cyt' => 3,
            'cy1' => 500,
            'cy2' => 550,
            'cy3' => 600,
            'exp' => 5,
        ]);
        $season = $this->createMockSeason('Regular Season');

        // Act
        $result = $this->processor->determineContractData($playerData, $season);

        // Assert
        $this->assertTrue($result['hasExistingContract']);
        $this->assertEquals(550, $result['salary']);
    }

    /**
     * @group integration
     * @group waivers
     * @group add-failure
     */
    public function testClaimBlockedWhenRosterFull(): void
    {
        // Act
        $result = $this->validator->validateAdd(100, 0, 5000, 500);

        // Assert
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('full roster', $errors[0]);
    }

    /**
     * @group integration
     * @group waivers
     * @group add-failure
     */
    public function testClaimBlockedWhenOverCapWithHealthyRoster(): void
    {
        // Act - 3 slots = 12 healthy players, total 6800+500=7300 > 7000
        $result = $this->validator->validateAdd(100, 3, 6800, 500);

        // Assert
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('hard cap', $errors[0]);
    }

    /**
     * @group integration
     * @group waivers
     * @group add-success
     */
    public function testClaimAllowedAtVetMinWhenUnderRosterMinimum(): void
    {
        // Act - 5 slots = 10 healthy players (<12), at cap but vet min salary
        $result = $this->validator->validateAdd(100, 5, 7000, 103);

        // Assert
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    // ========== HELPER METHODS ==========

    /**
     * Create base player data for waiver tests
     */
    private function getBasePlayerData(array $overrides = []): array
    {
        return TestDataFactory::createPlayer(array_merge([
            'pid' => 100,
            'name' => 'Test Waiver Player',
            'tid' => 0,
            'teamname' => 'Free Agent',
            'ordinal' => 1000, // On waiver wire
            'droptime' => time() - 86400, // Dropped 24 hours ago (cleared)
            'exp' => 3,
            'bird_years' => 0,
            'bird' => 0,
            'cy' => 0,
            'cy1' => 0,
            'cy2' => 0,
            'cy3' => 0,
            'cy4' => 0,
            'cy5' => 0,
            'cy6' => 0,
            'cyt' => 0,
        ], $overrides));
    }

    /**
     * Create a stub Season object for testing
     *
     * Since Season has public properties (not magic __get), we create
     * a stub and directly set the public property.
     */
    private function createMockSeason(string $phase): Season
    {
        $stubSeason = $this->createStub(\Season::class);
        $stubSeason->phase = $phase;

        return $stubSeason;
    }
}
