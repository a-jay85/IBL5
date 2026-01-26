<?php

declare(strict_types=1);

namespace Tests\Integration\RookieOption;

use Tests\Integration\IntegrationTestCase;
use Tests\Integration\Mocks\TestDataFactory;
use RookieOption\RookieOptionRepository;
use RookieOption\RookieOptionValidator;

/**
 * Integration tests for complete rookie option workflows
 *
 * Tests end-to-end scenarios combining validation and database persistence:
 * - Successful rookie option exercise for first round picks (cy4)
 * - Successful rookie option exercise for second round picks (cy3)
 * - Validation failures (ownership, eligibility, draft round)
 * - Database update operations
 * - Experience-based eligibility rules
 *
 * @covers \RookieOption\RookieOptionRepository
 * @covers \RookieOption\RookieOptionValidator
 */
class RookieOptionIntegrationTest extends IntegrationTestCase
{
    private RookieOptionRepository $repository;
    private RookieOptionValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new RookieOptionRepository($this->mockDb);
        $this->validator = new RookieOptionValidator();
    }

    protected function tearDown(): void
    {
        unset($this->repository);
        unset($this->validator);
        parent::tearDown();
    }

    // ========== FIRST ROUND PICK SUCCESS SCENARIOS ==========

    /**
     * @group integration
     * @group rookieoption
     * @group first-round
     */
    public function testSuccessfulRookieOptionExerciseFirstRoundPick(): void
    {
        // Arrange - First round pick with 3 years experience
        $playerID = 100;
        $draftRound = 1;
        $extensionAmount = 200; // $200K option year salary

        $mockPlayer = $this->createMockPlayerObject(
            teamName: 'Miami Cyclones',
            canRookieOption: true,
            draftRound: $draftRound,
            cy2Salary: 150,
            cy3Salary: 175  // Final year of first round rookie contract
        );

        $this->mockDb->setAffectedRows(1);

        // Act - Validate ownership
        $ownershipResult = $this->validator->validatePlayerOwnership($mockPlayer, 'Miami Cyclones');

        // Act - Validate eligibility
        $eligibilityResult = $this->validator->validateEligibilityAndGetSalary($mockPlayer, 'Regular Season');

        // Act - Update database
        $updateResult = $this->repository->updatePlayerRookieOption($playerID, $draftRound, $extensionAmount);

        // Assert
        $this->assertTrue($ownershipResult['valid'], 'Ownership validation should pass');
        $this->assertTrue($eligibilityResult['valid'], 'Eligibility validation should pass');
        $this->assertEquals(175, $eligibilityResult['finalYearSalary'], 'Should return cy3 for first round');
        $this->assertTrue($updateResult, 'Database update should succeed');

        // Assert - Correct contract year updated (cy4 for first round)
        $this->assertQueryExecuted('UPDATE ibl_plr');
        $this->assertQueryExecuted('cy4');
        $this->assertQueryExecuted((string) $extensionAmount);
        $this->assertQueryNotExecuted('cy3');
    }

    /**
     * @group integration
     * @group rookieoption
     * @group first-round
     */
    public function testFirstRoundPickWorkflowDuringFreeAgency(): void
    {
        // Arrange - First round pick during Free Agency phase (2 years exp)
        $playerID = 101;
        $draftRound = 1;
        $extensionAmount = 225;

        $mockPlayer = $this->createMockPlayerObject(
            teamName: 'Chicago Fire',
            canRookieOption: true,
            draftRound: $draftRound,
            cy2Salary: 160,
            cy3Salary: 180
        );

        $this->mockDb->setAffectedRows(1);

        // Act
        $ownershipResult = $this->validator->validatePlayerOwnership($mockPlayer, 'Chicago Fire');
        $eligibilityResult = $this->validator->validateEligibilityAndGetSalary($mockPlayer, 'Free Agency');
        $updateResult = $this->repository->updatePlayerRookieOption($playerID, $draftRound, $extensionAmount);

        // Assert
        $this->assertTrue($ownershipResult['valid']);
        $this->assertTrue($eligibilityResult['valid']);
        $this->assertTrue($updateResult);
        $this->assertQueryExecuted('cy4');
    }

    // ========== SECOND ROUND PICK SUCCESS SCENARIOS ==========

    /**
     * @group integration
     * @group rookieoption
     * @group second-round
     */
    public function testSuccessfulRookieOptionExerciseSecondRoundPick(): void
    {
        // Arrange - Second round pick with 2 years experience
        $playerID = 200;
        $draftRound = 2;
        $extensionAmount = 120; // $120K option year salary

        $mockPlayer = $this->createMockPlayerObject(
            teamName: 'New York Liberty',
            canRookieOption: true,
            draftRound: $draftRound,
            cy2Salary: 110,  // Final year of second round rookie contract
            cy3Salary: 0
        );

        $this->mockDb->setAffectedRows(1);

        // Act
        $ownershipResult = $this->validator->validatePlayerOwnership($mockPlayer, 'New York Liberty');
        $eligibilityResult = $this->validator->validateEligibilityAndGetSalary($mockPlayer, 'Regular Season');
        $updateResult = $this->repository->updatePlayerRookieOption($playerID, $draftRound, $extensionAmount);

        // Assert
        $this->assertTrue($ownershipResult['valid']);
        $this->assertTrue($eligibilityResult['valid']);
        $this->assertEquals(110, $eligibilityResult['finalYearSalary'], 'Should return cy2 for second round');
        $this->assertTrue($updateResult);

        // Assert - Correct contract year updated (cy3 for second round)
        $this->assertQueryExecuted('UPDATE ibl_plr');
        $this->assertQueryExecuted('cy3');
        $this->assertQueryExecuted((string) $extensionAmount);
        $this->assertQueryNotExecuted('cy4');
    }

    /**
     * @group integration
     * @group rookieoption
     * @group second-round
     */
    public function testSecondRoundPickWorkflowDuringFreeAgency(): void
    {
        // Arrange - Second round pick during Free Agency (1 year exp)
        $playerID = 201;
        $draftRound = 2;
        $extensionAmount = 105;

        $mockPlayer = $this->createMockPlayerObject(
            teamName: 'Boston Celtics',
            canRookieOption: true,
            draftRound: $draftRound,
            cy2Salary: 103, // Rookie minimum
            cy3Salary: 0
        );

        $this->mockDb->setAffectedRows(1);

        // Act
        $ownershipResult = $this->validator->validatePlayerOwnership($mockPlayer, 'Boston Celtics');
        $eligibilityResult = $this->validator->validateEligibilityAndGetSalary($mockPlayer, 'Free Agency');
        $updateResult = $this->repository->updatePlayerRookieOption($playerID, $draftRound, $extensionAmount);

        // Assert
        $this->assertTrue($ownershipResult['valid']);
        $this->assertTrue($eligibilityResult['valid']);
        $this->assertTrue($updateResult);
        $this->assertQueryExecuted('cy3');
    }

    // ========== OWNERSHIP VALIDATION FAILURES ==========

    /**
     * @group integration
     * @group rookieoption
     * @group validation-failure
     */
    public function testRookieOptionFailsWhenPlayerNotOnTeam(): void
    {
        // Arrange - Player on different team
        $mockPlayer = $this->createMockPlayerObject(
            teamName: 'Los Angeles Stars',
            canRookieOption: true,
            draftRound: 1,
            cy2Salary: 150,
            cy3Salary: 175
        );

        // Act
        $ownershipResult = $this->validator->validatePlayerOwnership($mockPlayer, 'Miami Cyclones');

        // Assert
        $this->assertFalse($ownershipResult['valid']);
        $this->assertArrayHasKey('error', $ownershipResult);
        $this->assertStringContainsString('not on your team', $ownershipResult['error']);
        $this->assertStringContainsString('Test Rookie', $ownershipResult['error']);

        // Database should NOT be updated
        $this->assertQueryNotExecuted('UPDATE ibl_plr');
    }

    /**
     * @group integration
     * @group rookieoption
     * @group validation-failure
     */
    public function testRookieOptionFailsWithEmptyTeamName(): void
    {
        // Arrange - Player with empty team name (free agent)
        $mockPlayer = $this->createMockPlayerObject(
            teamName: '',
            canRookieOption: true,
            draftRound: 1,
            cy2Salary: 150,
            cy3Salary: 175
        );

        // Act
        $ownershipResult = $this->validator->validatePlayerOwnership($mockPlayer, 'Miami Cyclones');

        // Assert
        $this->assertFalse($ownershipResult['valid']);
        $this->assertArrayHasKey('error', $ownershipResult);
    }

    // ========== ELIGIBILITY VALIDATION FAILURES ==========

    /**
     * @group integration
     * @group rookieoption
     * @group validation-failure
     */
    public function testRookieOptionFailsWhenPlayerNotEligible(): void
    {
        // Arrange - Player cannot exercise rookie option
        $mockPlayer = $this->createMockPlayerObject(
            teamName: 'Miami Cyclones',
            canRookieOption: false, // Not eligible
            draftRound: 1,
            cy2Salary: 150,
            cy3Salary: 175
        );

        // Act
        $eligibilityResult = $this->validator->validateEligibilityAndGetSalary($mockPlayer, 'Regular Season');

        // Assert
        $this->assertFalse($eligibilityResult['valid']);
        $this->assertArrayHasKey('error', $eligibilityResult);
        $this->assertStringContainsString('not eligible', $eligibilityResult['error']);
        $this->assertStringContainsString('first or second round draft picks', $eligibilityResult['error']);
    }

    /**
     * @group integration
     * @group rookieoption
     * @group validation-failure
     */
    public function testRookieOptionFailsWithZeroFinalYearSalary(): void
    {
        // Arrange - Player has zero salary in final contract year
        $mockPlayer = $this->createMockPlayerObject(
            teamName: 'Miami Cyclones',
            canRookieOption: true,
            draftRound: 1,
            cy2Salary: 0,
            cy3Salary: 0 // Zero final year salary
        );

        // Act
        $eligibilityResult = $this->validator->validateEligibilityAndGetSalary($mockPlayer, 'Regular Season');

        // Assert
        $this->assertFalse($eligibilityResult['valid']);
        $this->assertArrayHasKey('error', $eligibilityResult);
        $this->assertStringContainsString('not eligible', $eligibilityResult['error']);
    }

    /**
     * @group integration
     * @group rookieoption
     * @group validation-failure
     */
    public function testRookieOptionFailsForSecondRoundWithZeroCy2(): void
    {
        // Arrange - Second round pick with no cy2 salary
        $mockPlayer = $this->createMockPlayerObject(
            teamName: 'Miami Cyclones',
            canRookieOption: true,
            draftRound: 2,
            cy2Salary: 0, // Zero = invalid
            cy3Salary: 100
        );

        // Act
        $eligibilityResult = $this->validator->validateEligibilityAndGetSalary($mockPlayer, 'Regular Season');

        // Assert
        $this->assertFalse($eligibilityResult['valid']);
    }

    // ========== DATABASE OPERATIONS ==========

    /**
     * @group integration
     * @group rookieoption
     * @group database
     */
    public function testDatabaseUpdateIncludesCorrectPlayerID(): void
    {
        // Arrange
        $playerID = 12345;
        $draftRound = 1;
        $extensionAmount = 250;
        $this->mockDb->setAffectedRows(1);

        // Act
        $result = $this->repository->updatePlayerRookieOption($playerID, $draftRound, $extensionAmount);

        // Assert
        $this->assertTrue($result);
        $queries = $this->getExecutedQueries();
        $lastQuery = end($queries);
        $this->assertStringContainsString("pid = {$playerID}", $lastQuery);
    }

    /**
     * @group integration
     * @group rookieoption
     * @group database
     */
    public function testDatabaseUpdateThrowsExceptionOnFailure(): void
    {
        // Arrange - Database failure scenario
        // BaseMysqliRepository throws RuntimeException when execute fails
        $playerID = 99999;
        $draftRound = 1;
        $extensionAmount = 200;
        $this->mockDb->setReturnTrue(false);

        // Assert - Expect exception
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to execute query');

        // Act
        $this->repository->updatePlayerRookieOption($playerID, $draftRound, $extensionAmount);
    }

    /**
     * @group integration
     * @group rookieoption
     * @group database
     */
    public function testMultipleRookieOptionsCanBeExercised(): void
    {
        // Arrange - Multiple players exercising options
        $players = [
            ['id' => 100, 'round' => 1, 'amount' => 200],
            ['id' => 101, 'round' => 2, 'amount' => 120],
            ['id' => 102, 'round' => 1, 'amount' => 250],
        ];
        $this->mockDb->setAffectedRows(1);

        // Act
        $results = [];
        foreach ($players as $player) {
            $results[] = $this->repository->updatePlayerRookieOption(
                $player['id'],
                $player['round'],
                $player['amount']
            );
        }

        // Assert
        $this->assertCount(3, $results);
        foreach ($results as $result) {
            $this->assertTrue($result);
        }

        // Verify correct contract years were updated
        $queries = $this->getExecutedQueries();
        $this->assertCount(3, $queries);

        // First and third are round 1 (cy4), second is round 2 (cy3)
        $this->assertStringContainsString('cy4', $queries[0]);
        $this->assertStringContainsString('cy3', $queries[1]);
        $this->assertStringContainsString('cy4', $queries[2]);
    }

    // ========== COMPLETE WORKFLOW TESTS ==========

    /**
     * @group integration
     * @group rookieoption
     * @group workflow
     */
    public function testCompleteWorkflowFirstRoundRegularSeason(): void
    {
        // Arrange
        $playerID = 500;
        $teamName = 'Denver Nuggets';
        $draftRound = 1;
        $extensionAmount = 300;

        $mockPlayer = $this->createMockPlayerObject(
            teamName: $teamName,
            canRookieOption: true,
            draftRound: $draftRound,
            cy2Salary: 200,
            cy3Salary: 250
        );

        $this->mockDb->setAffectedRows(1);

        // Act - Complete workflow
        $step1 = $this->validator->validatePlayerOwnership($mockPlayer, $teamName);
        $step2 = $this->validator->validateEligibilityAndGetSalary($mockPlayer, 'Regular Season');
        $step3 = false;

        if ($step1['valid'] && $step2['valid']) {
            $step3 = $this->repository->updatePlayerRookieOption($playerID, $draftRound, $extensionAmount);
        }

        // Assert - All steps succeeded
        $this->assertTrue($step1['valid'], 'Step 1: Ownership validation');
        $this->assertTrue($step2['valid'], 'Step 2: Eligibility validation');
        $this->assertEquals(250, $step2['finalYearSalary'], 'Step 2: Final year salary');
        $this->assertTrue($step3, 'Step 3: Database update');

        // Verify database was updated correctly
        $this->assertQueryExecuted('UPDATE ibl_plr');
        $this->assertQueryExecuted('cy4');
        $this->assertQueryExecuted('300');
    }

    /**
     * @group integration
     * @group rookieoption
     * @group workflow
     */
    public function testCompleteWorkflowSecondRoundFreeAgency(): void
    {
        // Arrange
        $playerID = 501;
        $teamName = 'Phoenix Suns';
        $draftRound = 2;
        $extensionAmount = 115;

        $mockPlayer = $this->createMockPlayerObject(
            teamName: $teamName,
            canRookieOption: true,
            draftRound: $draftRound,
            cy2Salary: 108,
            cy3Salary: 0
        );

        $this->mockDb->setAffectedRows(1);

        // Act - Complete workflow
        $step1 = $this->validator->validatePlayerOwnership($mockPlayer, $teamName);
        $step2 = $this->validator->validateEligibilityAndGetSalary($mockPlayer, 'Free Agency');
        $step3 = false;

        if ($step1['valid'] && $step2['valid']) {
            $step3 = $this->repository->updatePlayerRookieOption($playerID, $draftRound, $extensionAmount);
        }

        // Assert
        $this->assertTrue($step1['valid']);
        $this->assertTrue($step2['valid']);
        $this->assertEquals(108, $step2['finalYearSalary']);
        $this->assertTrue($step3);
        $this->assertQueryExecuted('cy3');
    }

    /**
     * @group integration
     * @group rookieoption
     * @group workflow
     */
    public function testWorkflowStopsAtOwnershipFailure(): void
    {
        // Arrange
        $playerID = 600;
        $teamName = 'Wrong Team';

        $mockPlayer = $this->createMockPlayerObject(
            teamName: 'Actual Team',
            canRookieOption: true,
            draftRound: 1,
            cy2Salary: 150,
            cy3Salary: 175
        );

        // Act - Workflow should stop at ownership check
        $step1 = $this->validator->validatePlayerOwnership($mockPlayer, $teamName);

        // Should NOT proceed to eligibility or database
        $this->assertFalse($step1['valid']);

        // Verify no database operation occurred
        $this->assertQueryNotExecuted('UPDATE');
    }

    /**
     * @group integration
     * @group rookieoption
     * @group workflow
     */
    public function testWorkflowStopsAtEligibilityFailure(): void
    {
        // Arrange
        $playerID = 601;
        $teamName = 'Miami Cyclones';

        $mockPlayer = $this->createMockPlayerObject(
            teamName: $teamName,
            canRookieOption: false, // Not eligible
            draftRound: 1,
            cy2Salary: 150,
            cy3Salary: 175
        );

        // Act
        $step1 = $this->validator->validatePlayerOwnership($mockPlayer, $teamName);
        $step2 = $this->validator->validateEligibilityAndGetSalary($mockPlayer, 'Regular Season');

        // Assert - Ownership passes, eligibility fails
        $this->assertTrue($step1['valid']);
        $this->assertFalse($step2['valid']);

        // Verify no database operation occurred
        $this->assertQueryNotExecuted('UPDATE');
    }

    // ========== EDGE CASES ==========

    /**
     * @group integration
     * @group rookieoption
     * @group edge-case
     */
    public function testMinimumExtensionAmount(): void
    {
        // Arrange - Minimum salary extension
        $playerID = 700;
        $draftRound = 2;
        $extensionAmount = 103; // League minimum
        $this->mockDb->setAffectedRows(1);

        // Act
        $result = $this->repository->updatePlayerRookieOption($playerID, $draftRound, $extensionAmount);

        // Assert
        $this->assertTrue($result);
        $this->assertQueryExecuted('103');
    }

    /**
     * @group integration
     * @group rookieoption
     * @group edge-case
     */
    public function testHighExtensionAmount(): void
    {
        // Arrange - High salary extension (lottery pick)
        $playerID = 701;
        $draftRound = 1;
        $extensionAmount = 1500; // $1.5M for top pick
        $this->mockDb->setAffectedRows(1);

        // Act
        $result = $this->repository->updatePlayerRookieOption($playerID, $draftRound, $extensionAmount);

        // Assert
        $this->assertTrue($result);
        $this->assertQueryExecuted('1500');
        $this->assertQueryExecuted('cy4');
    }

    /**
     * @group integration
     * @group rookieoption
     * @group edge-case
     */
    public function testDifferentSeasonPhases(): void
    {
        // Arrange - Same player, different phases should have same validation result
        $mockPlayer = $this->createMockPlayerObject(
            teamName: 'Test Team',
            canRookieOption: true,
            draftRound: 1,
            cy2Salary: 150,
            cy3Salary: 175
        );

        // Act - Test multiple phases
        $phases = ['Regular Season', 'Preseason', 'HEAT', 'Free Agency', 'Playoffs'];
        $results = [];

        foreach ($phases as $phase) {
            $results[$phase] = $this->validator->validateEligibilityAndGetSalary($mockPlayer, $phase);
        }

        // Assert - All should be valid (canRookieOption returns true)
        foreach ($results as $phase => $result) {
            $this->assertTrue($result['valid'], "Should be valid during {$phase}");
            $this->assertEquals(175, $result['finalYearSalary'], "Final year salary should be 175 during {$phase}");
        }
    }

    /**
     * @group integration
     * @group rookieoption
     * @group edge-case
     */
    public function testErrorMessageContainsPlayerInfo(): void
    {
        // Arrange
        $mockPlayer = $this->createMockPlayerObject(
            teamName: 'Miami Cyclones',
            canRookieOption: false,
            draftRound: 1,
            cy2Salary: 150,
            cy3Salary: 175,
            position: 'PF',
            name: 'John Rookie'
        );

        // Act
        $result = $this->validator->validateEligibilityAndGetSalary($mockPlayer, 'Regular Season');

        // Assert - Error message contains player position and name
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('PF', $result['error']);
        $this->assertStringContainsString('John Rookie', $result['error']);
    }

    // ========== HELPER METHODS ==========

    /**
     * Create a mock player object for testing
     */
    private function createMockPlayerObject(
        string $teamName,
        bool $canRookieOption,
        int $draftRound,
        int $cy2Salary,
        int $cy3Salary,
        string $position = 'G',
        string $name = 'Test Rookie'
    ): object {
        return new class($teamName, $canRookieOption, $draftRound, $cy2Salary, $cy3Salary, $position, $name) {
            public string $teamName;
            public string $position;
            public string $name;
            public int $draftRound;
            private bool $canOption;
            private int $cy2Salary;
            private int $cy3Salary;

            public function __construct(
                string $teamName,
                bool $canRookieOption,
                int $draftRound,
                int $cy2Salary,
                int $cy3Salary,
                string $position,
                string $name
            ) {
                $this->teamName = $teamName;
                $this->canOption = $canRookieOption;
                $this->draftRound = $draftRound;
                $this->cy2Salary = $cy2Salary;
                $this->cy3Salary = $cy3Salary;
                $this->position = $position;
                $this->name = $name;
            }

            public function canRookieOption(string $seasonPhase): bool
            {
                return $this->canOption;
            }

            public function getFinalYearRookieContractSalary(): int
            {
                // First round: cy3 is final year of 3-year contract
                // Second round: cy2 is final year of 2-year contract
                return ($this->draftRound == 1) ? $this->cy3Salary : $this->cy2Salary;
            }
        };
    }
}
