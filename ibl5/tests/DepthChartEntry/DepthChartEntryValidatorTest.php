<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use PHPUnit\Framework\TestCase;
use DepthChartEntry\DepthChartEntryValidator;

class DepthChartEntryValidatorTest extends TestCase
{
    private DepthChartEntryValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DepthChartEntryValidator();
    }

    public function testValidatesSuccessfullyWithValidRegularSeasonData(): void
    {
        $depthChartData = [
            'playerData' => [],
            'activePlayers' => 12,
            'pos_1' => 3,
            'pos_2' => 3,
            'pos_3' => 3,
            'pos_4' => 3,
            'pos_5' => 3,
            'hasStarterAtMultiplePositions' => false,
            'nameOfProblemStarter' => ''
        ];

        $result = $this->validator->validate($depthChartData, 'Regular Season');

        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testValidatesSuccessfullyWithValidPlayoffsData(): void
    {
        $depthChartData = [
            'playerData' => [],
            'activePlayers' => 10,
            'pos_1' => 2,
            'pos_2' => 2,
            'pos_3' => 2,
            'pos_4' => 2,
            'pos_5' => 2,
            'hasStarterAtMultiplePositions' => false,
            'nameOfProblemStarter' => ''
        ];

        $result = $this->validator->validate($depthChartData, 'Playoffs');

        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testFailsValidationWithTooFewActivePlayers(): void
    {
        $depthChartData = [
            'playerData' => [],
            'activePlayers' => 10,
            'pos_1' => 3,
            'pos_2' => 3,
            'pos_3' => 3,
            'pos_4' => 3,
            'pos_5' => 3,
            'hasStarterAtMultiplePositions' => false,
            'nameOfProblemStarter' => ''
        ];

        $result = $this->validator->validate($depthChartData, 'Regular Season');

        $this->assertFalse($result);
        $this->assertNotEmpty($this->validator->getErrors());
        $this->assertSame('active_players_min', $this->validator->getErrors()[0]['type']);
    }

    public function testFailsValidationWithTooManyActivePlayers(): void
    {
        $depthChartData = [
            'playerData' => [],
            'activePlayers' => 13,
            'pos_1' => 3,
            'pos_2' => 3,
            'pos_3' => 3,
            'pos_4' => 3,
            'pos_5' => 3,
            'hasStarterAtMultiplePositions' => false,
            'nameOfProblemStarter' => ''
        ];

        $result = $this->validator->validate($depthChartData, 'Regular Season');

        $this->assertFalse($result);
        $this->assertNotEmpty($this->validator->getErrors());
        $this->assertSame('active_players_max', $this->validator->getErrors()[0]['type']);
    }

    public function testReturnsFormattedErrorMessages(): void
    {
        $depthChartData = [
            'playerData' => [],
            'activePlayers' => 10,
            'pos_1' => 3,
            'pos_2' => 3,
            'pos_3' => 3,
            'pos_4' => 3,
            'pos_5' => 3,
            'hasStarterAtMultiplePositions' => false,
            'nameOfProblemStarter' => ''
        ];

        $this->validator->validate($depthChartData, 'Regular Season');
        $errorHtml = $this->validator->getErrorMessagesHtml();

        $this->assertStringContainsString('text-red-500', $errorHtml);
        $this->assertStringContainsString('at least 12 active players', $errorHtml);
    }

    public function testValidatesActivePlayerCountPositionDepthAndMultiStarter(): void
    {
        $depthChartData = [
            'playerData' => [],
            'activePlayers' => 8,  // Too few for Regular Season (min 12)
            'pos_1' => 1,         // Too few PG (need 3)
            'pos_2' => 1,         // Too few SG (need 3)
            'pos_3' => 3,
            'pos_4' => 3,
            'pos_5' => 3,
            'hasStarterAtMultiplePositions' => true,
            'nameOfProblemStarter' => 'John Doe'
        ];

        $result = $this->validator->validate($depthChartData, 'Regular Season');

        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        // Should have: active_players_min + 2 position_depth + multiple_starting_positions
        $this->assertCount(4, $errors);
        $this->assertSame('active_players_min', $errors[0]['type']);
        $this->assertSame('position_depth', $errors[1]['type']);
        $this->assertSame('position_depth', $errors[2]['type']);
        $this->assertSame('multiple_starting_positions', $errors[3]['type']);
    }

    public function testEdgeCaseExactlyAtMinimumRequirements(): void
    {
        $depthChartData = [
            'playerData' => [],
            'activePlayers' => 12,  // Exactly minimum
            'pos_1' => 3,  // Exactly minimum
            'pos_2' => 3,
            'pos_3' => 3,
            'pos_4' => 3,
            'pos_5' => 3,
            'hasStarterAtMultiplePositions' => false,
            'nameOfProblemStarter' => ''
        ];

        $result = $this->validator->validate($depthChartData, 'Regular Season');

        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testEdgeCaseExactlyAtMaximumActivePlayers(): void
    {
        $depthChartData = [
            'playerData' => [],
            'activePlayers' => 12,  // Exactly maximum
            'pos_1' => 3,
            'pos_2' => 3,
            'pos_3' => 3,
            'pos_4' => 3,
            'pos_5' => 3,
            'hasStarterAtMultiplePositions' => false,
            'nameOfProblemStarter' => ''
        ];

        $result = $this->validator->validate($depthChartData, 'Regular Season');

        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testPlayoffsAllowsFewerActivePlayers(): void
    {
        $depthChartData = [
            'playerData' => [],
            'activePlayers' => 11,  // Valid for playoffs
            'pos_1' => 2,
            'pos_2' => 2,
            'pos_3' => 2,
            'pos_4' => 2,
            'pos_5' => 2,
            'hasStarterAtMultiplePositions' => false,
            'nameOfProblemStarter' => ''
        ];

        $result = $this->validator->validate($depthChartData, 'Playoffs');

        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testFailsValidationWithInsufficientPositionDepth(): void
    {
        $depthChartData = [
            'playerData' => [],
            'activePlayers' => 12,
            'pos_1' => 3,
            'pos_2' => 3,
            'pos_3' => 1,  // Below minimum of 3 for regular season
            'pos_4' => 3,
            'pos_5' => 3,
            'hasStarterAtMultiplePositions' => false,
            'nameOfProblemStarter' => ''
        ];

        $result = $this->validator->validate($depthChartData, 'Regular Season');

        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(1, $errors);
        $this->assertSame('position_depth', $errors[0]['type']);
        $this->assertStringContainsString('SF', $errors[0]['message']);
    }

    public function testFailsValidationWithMultipleStartingPositions(): void
    {
        $depthChartData = [
            'playerData' => [],
            'activePlayers' => 12,
            'pos_1' => 3,
            'pos_2' => 3,
            'pos_3' => 3,
            'pos_4' => 3,
            'pos_5' => 3,
            'hasStarterAtMultiplePositions' => true,
            'nameOfProblemStarter' => 'John Doe'
        ];

        $result = $this->validator->validate($depthChartData, 'Regular Season');

        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(1, $errors);
        $this->assertSame('multiple_starting_positions', $errors[0]['type']);
        $this->assertStringContainsString('John Doe', $errors[0]['message']);
    }

    public function testValidateRosterAcceptsExactMatchInAnyOrder(): void
    {
        $validator = new DepthChartEntryValidator();
        $result = $validator->validateRoster([3, 1, 2], [1, 2, 3]);
        $this->assertTrue($result);
        $this->assertSame([], $validator->getErrors());
    }

    public function testValidateRosterRejectsForeignPid(): void
    {
        $validator = new DepthChartEntryValidator();
        $result = $validator->validateRoster([1, 2, 999], [1, 2, 3]);
        $this->assertFalse($result);
        $types = array_column($validator->getErrors(), 'type');
        $this->assertContains('roster_foreign_pid', $types);
        $this->assertContains('roster_missing_pid', $types);
        $foreignError = array_values(array_filter($validator->getErrors(), static fn (array $e): bool => $e['type'] === 'roster_foreign_pid'))[0];
        $this->assertStringContainsString('999', $foreignError['message']);
        $missingError = array_values(array_filter($validator->getErrors(), static fn (array $e): bool => $e['type'] === 'roster_missing_pid'))[0];
        $this->assertStringContainsString('3', $missingError['message']);
    }

    public function testValidateRosterRejectsDuplicatePid(): void
    {
        $validator = new DepthChartEntryValidator();
        $result = $validator->validateRoster([1, 1, 2], [1, 2, 3]);
        $this->assertFalse($result);
        $types = array_column($validator->getErrors(), 'type');
        $this->assertContains('roster_duplicate_pid', $types);
        $dupError = array_values(array_filter($validator->getErrors(), static fn (array $e): bool => $e['type'] === 'roster_duplicate_pid'))[0];
        $this->assertStringEndsWith('(pid: 1).', $dupError['message']);
    }

    public function testValidateRosterRejectsOmittedRosterPid(): void
    {
        $validator = new DepthChartEntryValidator();
        $result = $validator->validateRoster([1, 2], [1, 2, 3, 4]);
        $this->assertFalse($result);
        $types = array_column($validator->getErrors(), 'type');
        $this->assertContains('roster_missing_pid', $types);
        $this->assertNotContains('roster_foreign_pid', $types);
        $missingError = array_values(array_filter($validator->getErrors(), static fn (array $e): bool => $e['type'] === 'roster_missing_pid'))[0];
        $this->assertStringContainsString('3, 4', $missingError['message']);
    }

    public function testValidateRosterRejectsPidZeroAsForeign(): void
    {
        $validator = new DepthChartEntryValidator();
        $result = $validator->validateRoster([0, 2, 3], [1, 2, 3]);
        $this->assertFalse($result);
        $types = array_column($validator->getErrors(), 'type');
        $this->assertContains('roster_foreign_pid', $types);
        $foreignError = array_values(array_filter($validator->getErrors(), static fn (array $e): bool => $e['type'] === 'roster_foreign_pid'))[0];
        $this->assertStringContainsString('0', $foreignError['message']);
    }

    public function testValidateRosterErrorsRenderThroughHtml(): void
    {
        $validator = new DepthChartEntryValidator();
        $validator->validateRoster([1, 2, 999], [1, 2, 3]);
        $html = $validator->getErrorMessagesHtml();
        $this->assertStringContainsString('not on your roster', $html);
        $this->assertStringContainsString('<strong>', $html);
    }
}
