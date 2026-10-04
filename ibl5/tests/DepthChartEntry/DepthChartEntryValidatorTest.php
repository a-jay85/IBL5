<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use PHPUnit\Framework\TestCase;
use DepthChartEntry\DepthChartEntryErrorHtmlRenderer;
use DepthChartEntry\DepthChartEntryValidator;
use Validation\ValidationError;

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

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->getErrors());
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

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->getErrors());
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

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
        $this->assertSame('active_players_min', $result->getErrors()[0]->type);
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

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
        $this->assertSame('active_players_max', $result->getErrors()[0]->type);
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

        $result = $this->validator->validate($depthChartData, 'Regular Season');
        $errorHtml = DepthChartEntryErrorHtmlRenderer::render($result->getErrors());

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

        $this->assertFalse($result->isValid());
        $errors = $result->getErrors();
        // Should have: active_players_min + 2 position_depth + multiple_starting_positions
        $this->assertCount(4, $errors);
        $this->assertSame('active_players_min', $errors[0]->type);
        $this->assertSame('position_depth', $errors[1]->type);
        $this->assertSame('position_depth', $errors[2]->type);
        $this->assertSame('multiple_starting_positions', $errors[3]->type);
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

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->getErrors());
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

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->getErrors());
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

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->getErrors());
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

        $this->assertFalse($result->isValid());
        $errors = $result->getErrors();
        $this->assertCount(1, $errors);
        $this->assertSame('position_depth', $errors[0]->type);
        $this->assertStringContainsString('SF', $errors[0]->message);
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

        $this->assertFalse($result->isValid());
        $errors = $result->getErrors();
        $this->assertCount(1, $errors);
        $this->assertSame('multiple_starting_positions', $errors[0]->type);
        $this->assertStringContainsString('John Doe', $errors[0]->message);
    }

    public function testValidateRosterAcceptsExactMatchInAnyOrder(): void
    {
        $validator = new DepthChartEntryValidator();
        $result = $validator->validateRoster([3, 1, 2], [1, 2, 3]);
        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->getErrors());
    }

    public function testValidateRosterRejectsForeignPid(): void
    {
        $validator = new DepthChartEntryValidator();
        $result = $validator->validateRoster([1, 2, 999], [1, 2, 3]);
        $this->assertFalse($result->isValid());
        $types = array_map(static fn (ValidationError $e): string => $e->type, $result->getErrors());
        $this->assertContains('roster_foreign_pid', $types);
        $this->assertContains('roster_missing_pid', $types);
        $foreignError = array_values(array_filter($result->getErrors(), static fn (ValidationError $e): bool => $e->type === 'roster_foreign_pid'))[0];
        $this->assertStringContainsString('999', $foreignError->message);
        $missingError = array_values(array_filter($result->getErrors(), static fn (ValidationError $e): bool => $e->type === 'roster_missing_pid'))[0];
        $this->assertStringContainsString('3', $missingError->message);
    }

    public function testValidateRosterRejectsDuplicatePid(): void
    {
        $validator = new DepthChartEntryValidator();
        $result = $validator->validateRoster([1, 1, 2], [1, 2, 3]);
        $this->assertFalse($result->isValid());
        $types = array_map(static fn (ValidationError $e): string => $e->type, $result->getErrors());
        $this->assertContains('roster_duplicate_pid', $types);
        $dupError = array_values(array_filter($result->getErrors(), static fn (ValidationError $e): bool => $e->type === 'roster_duplicate_pid'))[0];
        $this->assertStringEndsWith('(pid: 1).', $dupError->message);
    }

    public function testValidateRosterRejectsOmittedRosterPid(): void
    {
        $validator = new DepthChartEntryValidator();
        $result = $validator->validateRoster([1, 2], [1, 2, 3, 4]);
        $this->assertFalse($result->isValid());
        $types = array_map(static fn (ValidationError $e): string => $e->type, $result->getErrors());
        $this->assertContains('roster_missing_pid', $types);
        $this->assertNotContains('roster_foreign_pid', $types);
        $missingError = array_values(array_filter($result->getErrors(), static fn (ValidationError $e): bool => $e->type === 'roster_missing_pid'))[0];
        $this->assertStringContainsString('3, 4', $missingError->message);
    }

    public function testValidateRosterRejectsPidZeroAsForeign(): void
    {
        $validator = new DepthChartEntryValidator();
        $result = $validator->validateRoster([0, 2, 3], [1, 2, 3]);
        $this->assertFalse($result->isValid());
        $types = array_map(static fn (ValidationError $e): string => $e->type, $result->getErrors());
        $this->assertContains('roster_foreign_pid', $types);
        $foreignError = array_values(array_filter($result->getErrors(), static fn (ValidationError $e): bool => $e->type === 'roster_foreign_pid'))[0];
        $this->assertStringContainsString('0', $foreignError->message);
    }

    public function testValidateRosterErrorsRenderThroughHtml(): void
    {
        $validator = new DepthChartEntryValidator();
        $result = $validator->validateRoster([1, 2, 999], [1, 2, 3]);
        $html = DepthChartEntryErrorHtmlRenderer::render($result->getErrors());
        $this->assertStringContainsString('not on your roster', $html);
        $this->assertStringContainsString('<strong>', $html);
    }

    public function testErrorMessagesHtmlMarkupIsByteExactAndEscaped(): void
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
            'nameOfProblemStarter' => '<b>Bad</b> & "Co"',
        ];

        $result = $this->validator->validate($depthChartData, 'Regular Season');
        $html = DepthChartEntryErrorHtmlRenderer::render($result->getErrors());

        $this->assertSame(
            '<div class="text-center"><span class="text-red-500"><strong>'
            . \Security\HtmlSanitizer::safeHtmlOutput('<b>Bad</b> & "Co" is set as starter (1st) at multiple positions.')
            . '</strong></span><p>Set this player as 1st at only one position and resubmit.</p></div>',
            $html
        );
        $this->assertStringNotContainsString('<b>Bad</b>', $html);
    }

    public function testErrorMessagesHtmlConcatenatesErrorsInCollectionOrder(): void
    {
        $validator = new DepthChartEntryValidator();
        $result = $validator->validateRoster([1, 2, 2, 999], [1, 2, 3]);

        $block = static fn (string $message, string $detail): string => '<div class="text-center"><span class="text-red-500"><strong>'
            . \Security\HtmlSanitizer::safeHtmlOutput($message)
            . '</strong></span><p>'
            . \Security\HtmlSanitizer::safeHtmlOutput($detail)
            . '</p></div>';

        $expected = $block(
            'Your submission includes a player who is not on your roster (pid: 999).',
            'Reload the depth chart form so it lists only your current roster, then resubmit.'
        ) . $block(
            'A player appears more than once in your submission (pid: 2).',
            'Each roster player may appear only once. Reload the form and resubmit.'
        ) . $block(
            'Your submission is missing a roster player (pid: 3).',
            'Every player on your roster must be included, even when inactive. Reload the form and resubmit.'
        );

        $this->assertSame($expected, DepthChartEntryErrorHtmlRenderer::render($result->getErrors()));
    }

    public function testValidateIsStatelessAcrossCalls(): void
    {
        $invalid = [
            'playerData' => [],
            'activePlayers' => 5,
            'pos_1' => 3,
            'pos_2' => 3,
            'pos_3' => 3,
            'pos_4' => 3,
            'pos_5' => 3,
            'hasStarterAtMultiplePositions' => false,
            'nameOfProblemStarter' => '',
        ];
        $valid = $invalid;
        $valid['activePlayers'] = 12;

        $first = $this->validator->validate($invalid, 'Regular Season');
        $second = $this->validator->validate($valid, 'Regular Season');

        $this->assertTrue($second->isValid());
        $this->assertFalse($first->isValid());
        $this->assertGreaterThanOrEqual(1, count($first->getErrors()));
    }

    public function testValidateRosterErrorOrderIsForeignDuplicateMissing(): void
    {
        $result = $this->validator->validateRoster([1, 2, 2, 999], [1, 2, 3]);

        $this->assertSame(
            ['roster_foreign_pid', 'roster_duplicate_pid', 'roster_missing_pid'],
            array_map(static fn (ValidationError $e): string => $e->type, $result->getErrors())
        );
    }
}
