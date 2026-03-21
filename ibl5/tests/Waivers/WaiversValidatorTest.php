<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use League\League;
use Waivers\WaiversValidator;

class WaiversValidatorTest extends TestCase
{
    private $validator;
    
    protected function setUp(): void
    {
        $this->validator = new WaiversValidator();
    }
    
    public function testValidateDropSucceedsWithNormalRosterAndSalary()
    {
        $result = $this->validator->validateDrop(
            10, // roster slots
            6000 // total salary (under cap)
        );
        
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }
    
    public function testValidateDropFailsWithFullRosterOverCap()
    {
        $result = $this->validator->validateDrop(
            13, // more than 2 roster slots (12+ players)
            7500 // over hard cap
        );
        
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("12 players", $errors[0]);
        $this->assertStringContainsString("over the hard cap", $errors[0]);
    }
    
    public function testValidateDropSucceedsWithFullRosterUnderCap()
    {
        $result = $this->validator->validateDrop(
            13, // more than 2 roster slots
            6000 // under hard cap
        );
        
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }
    
    public function testValidateAddFailsWithNullPlayerID()
    {
        $result = $this->validator->validateAdd(
            null,  // no player selected
            5,     // healthy roster slots
            6000,  // total salary
            100    // player salary
        );
        
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("didn't select a valid player", $errors[0]);
    }
    
    public function testValidateAddFailsWithZeroPlayerID()
    {
        $result = $this->validator->validateAdd(
            0,     // invalid player ID
            5,     // healthy roster slots
            6000,  // total salary
            100    // player salary
        );
        
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("didn't select a valid player", $errors[0]);
    }
    
    public function testValidateAddFailsWithFullRoster()
    {
        $result = $this->validator->validateAdd(
            123,   // valid player ID
            0,     // no healthy roster slots available
            6000,  // total salary
            100    // player salary
        );
        
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("full roster", $errors[0]);
    }
    
    public function testValidateAddFailsWith12PlusHealthyPlayersOverCap()
    {
        $result = $this->validator->validateAdd(
            123,   // valid player ID
            3,     // 12 healthy players (4-1)
            6800,  // current salary
            300    // player salary (would put over cap)
        );
        
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("12 or more healthy players", $errors[0]);
        $this->assertStringContainsString("over the hard cap", $errors[0]);
    }
    
    public function testValidateAddSucceedsWith12HealthyPlayersUnderCap()
    {
        $result = $this->validator->validateAdd(
            123,   // valid player ID
            3,     // 12 healthy players
            6000,  // current salary
            100    // player salary (stays under cap)
        );
        
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }
    
    public function testValidateAddFailsOverCapWithNonVetMin()
    {
        $result = $this->validator->validateAdd(
            123,   // valid player ID
            5,     // under 12 healthy players
            6980,  // current salary
            400    // player salary above vet min (103), would put over cap
        );
        
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("over the hard cap", $errors[0]);
        $this->assertStringContainsString("veteran minimum", $errors[0]);
    }
    
    public function testValidateAddSucceedsOverCapWithVetMin()
    {
        $result = $this->validator->validateAdd(
            123,   // valid player ID
            5,     // under 12 healthy players
            7100,  // over hard cap
            103    // vet min salary
        );
        
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }
    
    public function testValidateAddSucceedsWithNormalConditions()
    {
        $result = $this->validator->validateAdd(
            123,   // valid player ID
            5,     // healthy roster slots available
            6000,  // total salary
            500    // player salary
        );
        
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }
    
    public function testClearErrorsRemovesAllErrors()
    {
        // First create an error
        $this->validator->validateAdd(null, 5, 6000, 100);
        $this->assertNotEmpty($this->validator->getErrors());
        
        // Clear errors
        $this->validator->clearErrors();
        $this->assertEmpty($this->validator->getErrors());
    }
    
    public function testValidateAddEdgeCaseAtExactCap()
    {
        $result = $this->validator->validateAdd(
            123,   // valid player ID
            5,     // healthy roster slots
            6900,  // current salary
            100    // player salary brings to exactly 7000
        );

        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    // --- Merged from WaiversValidatorEdgeCaseTest ---

    // ============================================
    // PLAYER ID EDGE CASES
    // ============================================

    /**
     * Test validateAdd with empty string player ID (cast to 0)
     *
     * Note: PHP type coercion means empty string passed to ?int becomes 0
     * This test documents that behavior.
     */
    public function testValidateAddWithZeroPlayerIdFails(): void
    {
        $result = $this->validator->validateAdd(
            0,     // Zero player ID
            5,     // healthy roster slots
            6000,  // total salary
            100    // player salary
        );

        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertStringContainsString("didn't select a valid player", $errors[0]);
    }

    /**
     * Test validateAdd with negative player ID
     */
    public function testValidateAddWithNegativePlayerIdSucceeds(): void
    {
        // Negative player ID passes the null/zero check but is invalid
        // The validator only checks for null and 0
        $result = $this->validator->validateAdd(
            -1,    // Negative player ID
            5,     // healthy roster slots
            6000,  // total salary
            100    // player salary
        );

        // Currently passes because validator doesn't check for negative
        $this->assertTrue($result);
    }

    /**
     * Test validateAdd with very large player ID
     */
    public function testValidateAddWithLargePlayerIdSucceeds(): void
    {
        $result = $this->validator->validateAdd(
            999999999,  // Large player ID
            5,          // healthy roster slots
            6000,       // total salary
            100         // player salary
        );

        $this->assertTrue($result);
    }

    // ============================================
    // ROSTER SLOT BOUNDARY TESTS
    // ============================================

    /**
     * Test validateAdd with exactly 0 roster slots (full roster)
     */
    public function testValidateAddWithZeroRosterSlotsFails(): void
    {
        $result = $this->validator->validateAdd(
            123,   // valid player ID
            0,     // exactly 0 healthy roster slots
            6000,  // total salary
            100    // player salary
        );

        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertStringContainsString('full roster', $errors[0]);
    }

    /**
     * Test validateAdd with exactly 1 roster slot
     */
    public function testValidateAddWithOneRosterSlotSucceeds(): void
    {
        $result = $this->validator->validateAdd(
            123,   // valid player ID
            1,     // exactly 1 healthy roster slot
            6000,  // total salary
            100    // player salary
        );

        $this->assertTrue($result);
    }

    /**
     * Test validateAdd with exactly 3 roster slots (boundary for 12+ player rule)
     */
    public function testValidateAddWithThreeRosterSlotsAndOverCapFails(): void
    {
        // 3 slots means 12 healthy players (15 - 3 = 12)
        $result = $this->validator->validateAdd(
            123,   // valid player ID
            3,     // 3 healthy roster slots = 12 healthy players
            6900,  // current salary
            200    // player salary (would put at 7100)
        );

        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertStringContainsString('12 or more healthy players', $errors[0]);
    }

    /**
     * Test validateAdd with exactly 4 roster slots (boundary - under 12 players)
     */
    public function testValidateAddWithFourRosterSlotsAllowsVetMinOverCap(): void
    {
        // 4 slots means 11 healthy players (15 - 4 = 11)
        $result = $this->validator->validateAdd(
            123,   // valid player ID
            4,     // 4 healthy roster slots = 11 healthy players
            7100,  // over hard cap
            103    // vet min salary
        );

        $this->assertTrue($result);
    }

    /**
     * Test validateAdd with 4 slots and non-vet-min salary over cap fails
     */
    public function testValidateAddWithFourRosterSlotsNonVetMinOverCapFails(): void
    {
        // Under 12 players but over cap with non-vet-min salary
        $result = $this->validator->validateAdd(
            123,   // valid player ID
            4,     // 4 healthy roster slots = 11 healthy players
            7100,  // over hard cap
            200    // non-vet-min salary
        );

        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertStringContainsString('over the hard cap', $errors[0]);
        $this->assertStringContainsString('veteran minimum', $errors[0]);
    }

    // ============================================
    // SALARY BOUNDARY TESTS
    // ============================================

    /**
     * Test validateAdd with salary bringing team exactly to hard cap
     */
    public function testValidateAddExactlyAtHardCapSucceeds(): void
    {
        $hardCap = League::HARD_CAP_MAX;
        $result = $this->validator->validateAdd(
            123,            // valid player ID
            5,              // healthy roster slots
            $hardCap - 100, // current salary
            100             // player salary brings to exactly hard cap
        );

        $this->assertTrue($result);
    }

    /**
     * Test validateAdd with salary putting team one over hard cap
     */
    public function testValidateAddOneOverHardCapWithVetMinSucceeds(): void
    {
        $hardCap = League::HARD_CAP_MAX;
        $result = $this->validator->validateAdd(
            123,       // valid player ID
            5,         // healthy roster slots (under 12 players)
            $hardCap,  // at hard cap
            103        // vet min salary
        );

        // Vet min signings allowed when over cap if under 12 players
        $this->assertTrue($result);
    }

    /**
     * Test validateAdd with salary exactly at vet min threshold (103)
     */
    public function testValidateAddAtExactVetMinThreshold(): void
    {
        $result = $this->validator->validateAdd(
            123,   // valid player ID
            5,     // healthy roster slots
            7100,  // over hard cap
            103    // exactly vet min
        );

        $this->assertTrue($result);
    }

    /**
     * Test validateAdd with salary one over vet min threshold (104)
     */
    public function testValidateAddOneOverVetMinThresholdFails(): void
    {
        $result = $this->validator->validateAdd(
            123,   // valid player ID
            5,     // healthy roster slots
            7100,  // over hard cap
            104    // one over vet min
        );

        $this->assertFalse($result);
    }

    /**
     * Test validateAdd with zero player salary
     */
    public function testValidateAddWithZeroPlayerSalarySucceeds(): void
    {
        $result = $this->validator->validateAdd(
            123,   // valid player ID
            5,     // healthy roster slots
            6000,  // total salary
            0      // zero player salary
        );

        $this->assertTrue($result);
    }

    // ============================================
    // DROP VALIDATION EDGE CASES
    // ============================================

    /**
     * Test validateDrop with exactly 2 roster slots (boundary)
     */
    public function testValidateDropWithTwoRosterSlotsOverCapSucceeds(): void
    {
        // 2 slots means 13 players, but rule is "more than 2"
        $result = $this->validator->validateDrop(
            2,     // exactly 2 roster slots
            7500   // over hard cap
        );

        // 2 slots is NOT more than 2, so this passes
        $this->assertTrue($result);
    }

    /**
     * Test validateDrop with exactly 3 roster slots (just over boundary)
     */
    public function testValidateDropWithThreeRosterSlotsOverCapFails(): void
    {
        // 3 slots is more than 2, triggers the rule
        $result = $this->validator->validateDrop(
            3,     // 3 roster slots (more than 2)
            7500   // over hard cap
        );

        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertStringContainsString('12 players', $errors[0]);
    }

    /**
     * Test validateDrop with 3 roster slots exactly at hard cap
     */
    public function testValidateDropWithThreeRosterSlotsAtCapSucceeds(): void
    {
        $hardCap = League::HARD_CAP_MAX;
        $result = $this->validator->validateDrop(
            3,        // 3 roster slots
            $hardCap  // exactly at hard cap
        );

        // Not OVER cap, so this passes
        $this->assertTrue($result);
    }

    /**
     * Test validateDrop with 3 roster slots one over hard cap
     */
    public function testValidateDropWithThreeRosterSlotsOneOverCapFails(): void
    {
        $hardCap = League::HARD_CAP_MAX;
        $result = $this->validator->validateDrop(
            3,            // 3 roster slots
            $hardCap + 1  // one over hard cap
        );

        $this->assertFalse($result);
    }

    /**
     * Test validateDrop with zero roster slots
     */
    public function testValidateDropWithZeroRosterSlotsSucceeds(): void
    {
        $result = $this->validator->validateDrop(
            0,     // 0 roster slots (full roster)
            7500   // over hard cap
        );

        // 0 is not more than 2, so this passes
        $this->assertTrue($result);
    }

    /**
     * Test validateDrop with negative roster slots
     */
    public function testValidateDropWithNegativeRosterSlotsSucceeds(): void
    {
        $result = $this->validator->validateDrop(
            -1,    // negative roster slots (invalid but not checked)
            7500   // over hard cap
        );

        // -1 is not more than 2, so this passes
        $this->assertTrue($result);
    }

    // ============================================
    // ERROR STATE TESTS
    // ============================================

    /**
     * Test that errors are cleared between validations
     */
    public function testErrorsClearedBetweenValidations(): void
    {
        // First validation fails
        $this->validator->validateAdd(null, 5, 6000, 100);
        $this->assertNotEmpty($this->validator->getErrors());

        // Second validation succeeds - errors should be cleared
        $this->validator->validateAdd(123, 5, 6000, 100);
        $this->assertEmpty($this->validator->getErrors());
    }

    /**
     * Test that validateDrop clears errors from previous validateAdd
     */
    public function testValidateDropClearsAddErrors(): void
    {
        // Add validation fails
        $this->validator->validateAdd(null, 5, 6000, 100);
        $this->assertNotEmpty($this->validator->getErrors());

        // Drop validation succeeds - should clear errors
        $this->validator->validateDrop(2, 6000);
        $this->assertEmpty($this->validator->getErrors());
    }

    /**
     * Test multiple consecutive failures accumulate correctly
     */
    public function testMultipleValidationsOnlyCaptureLatestErrors(): void
    {
        // First failure
        $this->validator->validateAdd(null, 5, 6000, 100);
        $errors1 = $this->validator->getErrors();

        // Second failure with different error
        $this->validator->validateAdd(123, 0, 6000, 100);
        $errors2 = $this->validator->getErrors();

        // Should only have the second error
        $this->assertCount(1, $errors2);
        $this->assertStringContainsString('full roster', $errors2[0]);
    }

    // ============================================
    // DATA PROVIDER TESTS
    // ============================================

    /**     */
    #[DataProvider('rosterSlotBoundaryProvider')]
    public function testRosterSlotBoundaries(
        int $healthySlots,
        int $totalSalary,
        int $playerSalary,
        bool $expectedResult
    ): void {
        $result = $this->validator->validateAdd(
            123,
            $healthySlots,
            $totalSalary,
            $playerSalary
        );

        $this->assertEquals($expectedResult, $result);
    }

    public static function rosterSlotBoundaryProvider(): array
    {
        $hardCap = League::HARD_CAP_MAX;

        return [
            '0 slots fails (full roster)' => [0, 6000, 100, false],
            '1 slot under cap succeeds' => [1, 6000, 100, true],
            '3 slots under cap succeeds' => [3, 6000, 100, true],
            '3 slots over cap fails' => [3, $hardCap + 100, 100, false],
            '4 slots over cap vet min succeeds' => [4, $hardCap + 100, 103, true],
            '4 slots over cap non-vet-min fails' => [4, $hardCap + 100, 200, false],
            '5 slots at cap succeeds' => [5, $hardCap - 100, 100, true],
            '5 slots over cap vet min succeeds' => [5, $hardCap + 100, 103, true],
        ];
    }

    /**     */
    #[DataProvider('salaryBoundaryProvider')]
    public function testSalaryBoundaries(
        int $totalSalary,
        int $playerSalary,
        int $healthySlots,
        bool $expectedResult
    ): void {
        $result = $this->validator->validateAdd(
            123,
            $healthySlots,
            $totalSalary,
            $playerSalary
        );

        $this->assertEquals($expectedResult, $result);
    }

    public static function salaryBoundaryProvider(): array
    {
        $hardCap = League::HARD_CAP_MAX;

        return [
            'exactly at cap succeeds' => [$hardCap - 100, 100, 5, true],
            'one over cap with vet min succeeds' => [$hardCap, 103, 5, true],
            'one over cap with 104 fails' => [$hardCap, 104, 5, false],
            'way over cap with vet min succeeds' => [$hardCap + 1000, 103, 5, true],
            'under cap with large salary succeeds' => [5000, 1000, 5, true],
            'zero salary succeeds' => [6000, 0, 5, true],
        ];
    }
}
