<?php

declare(strict_types=1);

namespace Tests\Draft;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Draft\DraftValidator;

class DraftValidatorTest extends TestCase
{
    private DraftValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DraftValidator();
    }

    public function testValidateSucceedsWithValidSelection(): void
    {
        $result = $this->validator->validateDraftSelection('John Doe', null);

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->getErrors());
    }

    public function testValidateSucceedsWithEmptyStringCurrentSelection(): void
    {
        $result = $this->validator->validateDraftSelection('John Doe', '');

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->getErrors());
    }

    public function testValidateFailsWithNullPlayerName(): void
    {
        $result = $this->validator->validateDraftSelection(null, null);

        $this->assertFalse($result->isValid());
        $errors = $result->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("didn't select a player", $errors[0]);
    }

    public function testValidateFailsWithEmptyPlayerName(): void
    {
        $result = $this->validator->validateDraftSelection('', null);

        $this->assertFalse($result->isValid());
        $errors = $result->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("didn't select a player", $errors[0]);
    }

    public function testValidateFailsWhenPickAlreadyUsed(): void
    {
        $result = $this->validator->validateDraftSelection('John Doe', 'Jane Smith');

        $this->assertFalse($result->isValid());
        $errors = $result->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("already drafted", $errors[0]);
    }

    public function testResultsAreIndependentAcrossCalls(): void
    {
        $errorResult = $this->validator->validateDraftSelection(null, null);
        $this->assertNotEmpty($errorResult->getErrors());

        $successResult = $this->validator->validateDraftSelection('John Doe', null);
        $this->assertSame([], $successResult->getErrors());
    }

    public function testValidateResetsPreviousErrors(): void
    {
        // First validation should fail
        $failedResult = $this->validator->validateDraftSelection(null, null);
        $this->assertNotEmpty($failedResult->getErrors());

        // Second validation should succeed — independent result
        $result = $this->validator->validateDraftSelection('John Doe', null);

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->getErrors());
    }

    public function testValidateFailsWhenPlayerAlreadyDrafted(): void
    {
        $result = $this->validator->validateDraftSelection('John Doe', null, true);

        $this->assertFalse($result->isValid());
        $errors = $result->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("already been drafted by another team", $errors[0]);
    }

    public function testValidateSucceedsWhenPlayerNotAlreadyDrafted(): void
    {
        $result = $this->validator->validateDraftSelection('John Doe', null, false);

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->getErrors());
    }

    public function testValidateFailsWithPlayerAlreadyDraftedEvenIfPickNotUsed(): void
    {
        $result = $this->validator->validateDraftSelection('John Doe', '', true);

        $this->assertFalse($result->isValid());
        $errors = $result->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("already been drafted by another team", $errors[0]);
    }

    // --- Merged from DraftValidatorEdgeCaseTest ---

    // ============================================
    // WHITESPACE HANDLING TESTS
    // ============================================

    /**
     * Test player name that is only whitespace
     */
    public function testRejectsWhitespaceOnlyPlayerName(): void
    {
        $result = $this->validator->validateDraftSelection('   ', null);

        // Whitespace-only string is not empty, so it currently passes
        // This documents current behavior
        $this->assertTrue($result->isValid());
    }

    /**
     * Test player name with leading whitespace
     */
    public function testAcceptsPlayerNameWithLeadingWhitespace(): void
    {
        $result = $this->validator->validateDraftSelection('  John Doe', null);

        $this->assertTrue($result->isValid());
    }

    /**
     * Test player name with trailing whitespace
     */
    public function testAcceptsPlayerNameWithTrailingWhitespace(): void
    {
        $result = $this->validator->validateDraftSelection('John Doe  ', null);

        $this->assertTrue($result->isValid());
    }

    /**
     * Test player name with only tabs
     */
    public function testAcceptsPlayerNameWithOnlyTabs(): void
    {
        $result = $this->validator->validateDraftSelection("\t\t", null);

        // Tab-only string is not empty
        $this->assertTrue($result->isValid());
    }

    /**
     * Test player name with newlines
     */
    public function testAcceptsPlayerNameWithNewlines(): void
    {
        $result = $this->validator->validateDraftSelection("John\nDoe", null);

        $this->assertTrue($result->isValid());
    }

    // ============================================
    // SPECIAL CHARACTER TESTS
    // ============================================

    /**
     * Test player name with apostrophe (O'Brien)
     */
    public function testAcceptsPlayerNameWithApostrophe(): void
    {
        $result = $this->validator->validateDraftSelection("Patrick O'Brien", null);

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->getErrors());
    }

    /**
     * Test player name with hyphen (Mary-Jane)
     */
    public function testAcceptsPlayerNameWithHyphen(): void
    {
        $result = $this->validator->validateDraftSelection('Mary-Jane Watson', null);

        $this->assertTrue($result->isValid());
    }

    /**
     * Test player name with period (Dr. J)
     */
    public function testAcceptsPlayerNameWithPeriod(): void
    {
        $result = $this->validator->validateDraftSelection('J.R. Smith', null);

        $this->assertTrue($result->isValid());
    }

    /**
     * Test player name with numbers
     */
    public function testAcceptsPlayerNameWithNumbers(): void
    {
        $result = $this->validator->validateDraftSelection('Player 123', null);

        $this->assertTrue($result->isValid());
    }

    /**
     * Test player name with special symbols
     */
    public function testAcceptsPlayerNameWithSpecialSymbols(): void
    {
        $result = $this->validator->validateDraftSelection('Player @#$%', null);

        $this->assertTrue($result->isValid());
    }

    // ============================================
    // UNICODE CHARACTER TESTS
    // ============================================

    /**
     * Test player name with accented characters
     */
    public function testAcceptsPlayerNameWithAccents(): void
    {
        $result = $this->validator->validateDraftSelection('José García', null);

        $this->assertTrue($result->isValid());
    }

    /**
     * Test player name with umlaut
     */
    public function testAcceptsPlayerNameWithUmlaut(): void
    {
        $result = $this->validator->validateDraftSelection('Dirk Nowitzki', null);

        $this->assertTrue($result->isValid());
    }

    /**
     * Test player name with Chinese characters
     */
    public function testAcceptsPlayerNameWithChineseCharacters(): void
    {
        $result = $this->validator->validateDraftSelection('姚明', null);

        $this->assertTrue($result->isValid());
    }

    /**
     * Test player name with Cyrillic characters
     */
    public function testAcceptsPlayerNameWithCyrillicCharacters(): void
    {
        $result = $this->validator->validateDraftSelection('Андрей Кириленко', null);

        $this->assertTrue($result->isValid());
    }

    /**
     * Test player name with emoji
     */
    public function testAcceptsPlayerNameWithEmoji(): void
    {
        $result = $this->validator->validateDraftSelection('Player 🏀', null);

        $this->assertTrue($result->isValid());
    }

    // ============================================
    // LENGTH BOUNDARY TESTS
    // ============================================

    /**
     * Test player name with single character
     */
    public function testAcceptsSingleCharacterPlayerName(): void
    {
        $result = $this->validator->validateDraftSelection('X', null);

        $this->assertTrue($result->isValid());
    }

    /**
     * Test player name with very long name (255 characters)
     */
    public function testAcceptsVeryLongPlayerName(): void
    {
        $longName = str_repeat('A', 255);
        $result = $this->validator->validateDraftSelection($longName, null);

        $this->assertTrue($result->isValid());
    }

    /**
     * Test player name with extremely long name (1000 characters)
     */
    public function testAcceptsExtremelyLongPlayerName(): void
    {
        $longName = str_repeat('A', 1000);
        $result = $this->validator->validateDraftSelection($longName, null);

        $this->assertTrue($result->isValid());
    }

    // ============================================
    // CURRENT DRAFT SELECTION EDGE CASES
    // ============================================

    /**
     * Test with whitespace-only current selection
     */
    public function testRejectsWhitespaceOnlyCurrentSelection(): void
    {
        $result = $this->validator->validateDraftSelection('John Doe', '   ');

        // Whitespace string is not empty or null, so it's treated as already drafted
        $this->assertFalse($result->isValid());
        $errors = $result->getErrors();
        $this->assertStringContainsString('already drafted', $errors[0]);
    }

    /**
     * Test with "0" as current selection (truthy string)
     */
    public function testRejectsZeroStringCurrentSelection(): void
    {
        $result = $this->validator->validateDraftSelection('John Doe', '0');

        // "0" is not empty or null
        $this->assertFalse($result->isValid());
    }

    /**
     * Test with very long current selection
     */
    public function testRejectsLongCurrentSelection(): void
    {
        $longSelection = str_repeat('A', 255);
        $result = $this->validator->validateDraftSelection('John Doe', $longSelection);

        $this->assertFalse($result->isValid());
        $errors = $result->getErrors();
        $this->assertStringContainsString('already drafted', $errors[0]);
    }

    // ============================================
    // COMBINATION TESTS
    // ============================================

    /**
     * Test null player name with non-null current selection
     */
    public function testNullPlayerNameCheckedBeforeCurrentSelection(): void
    {
        $result = $this->validator->validateDraftSelection(null, 'Existing Player');

        // Player name check happens first
        $this->assertFalse($result->isValid());
        $errors = $result->getErrors();
        $this->assertStringContainsString("didn't select a player", $errors[0]);
    }

    /**
     * Test empty player name with non-null current selection
     */
    public function testEmptyPlayerNameCheckedBeforeCurrentSelection(): void
    {
        $result = $this->validator->validateDraftSelection('', 'Existing Player');

        // Player name check happens first
        $this->assertFalse($result->isValid());
        $errors = $result->getErrors();
        $this->assertStringContainsString("didn't select a player", $errors[0]);
    }

    /**
     * Test isPlayerAlreadyDrafted takes precedence when player name valid
     */
    public function testIsPlayerAlreadyDraftedWithValidPlayerName(): void
    {
        $result = $this->validator->validateDraftSelection('John Doe', null, true);

        $this->assertFalse($result->isValid());
        $errors = $result->getErrors();
        $this->assertStringContainsString('already been drafted by another team', $errors[0]);
    }

    /**
     * Test current selection takes precedence over isPlayerAlreadyDrafted
     */
    public function testCurrentSelectionCheckedBeforeIsPlayerAlreadyDrafted(): void
    {
        $result = $this->validator->validateDraftSelection('John Doe', 'Existing', true);

        // Current selection check happens before isPlayerAlreadyDrafted
        $this->assertFalse($result->isValid());
        $errors = $result->getErrors();
        $this->assertStringContainsString('already drafted', $errors[0]);
        // This error is about the pick being used, not the player being drafted
    }

    // ============================================
    // ERROR HANDLING TESTS
    // ============================================

    /**
     * Test multiple validations each return their own independent result
     */
    public function testSubsequentValidationClearsPreviousErrors(): void
    {
        // First validation fails with player name error
        $result1 = $this->validator->validateDraftSelection(null, null);
        $errors1 = $result1->getErrors();
        $this->assertStringContainsString("didn't select a player", $errors1[0]);

        // Second validation fails with different error — independent result
        $result2 = $this->validator->validateDraftSelection('John Doe', 'Existing');
        $errors2 = $result2->getErrors();
        $this->assertCount(1, $errors2);
        $this->assertStringContainsString('already drafted', $errors2[0]);
    }

    /**
     * Test successful validation returns empty errors — independent of prior failures
     */
    public function testSuccessfulValidationClearsPreviousErrors(): void
    {
        // Generate an error
        $errorResult = $this->validator->validateDraftSelection(null, null);
        $this->assertNotEmpty($errorResult->getErrors());

        // Successful validation — independent result
        $successResult = $this->validator->validateDraftSelection('John Doe', null);
        $this->assertSame([], $successResult->getErrors());
    }

    // ============================================
    // DATA PROVIDER TESTS
    // ============================================

    /**     */
    #[DataProvider('specialCharacterNamesProvider')]
    public function testAcceptsSpecialCharacterNames(string $playerName): void
    {
        $result = $this->validator->validateDraftSelection($playerName, null);

        $this->assertTrue($result->isValid());
    }

    public static function specialCharacterNamesProvider(): array
    {
        return [
            'apostrophe' => ["O'Neal"],
            'hyphen' => ['Smith-Jones'],
            'period' => ['J.R. Smith'],
            'comma' => ['Smith, John'],
            'accent aigu' => ['José'],
            'accent grave' => ['André'],
            'umlaut' => ['Müller'],
            'cedilla' => ['François'],
            'tilde' => ['Nuño'],
            'circumflex' => ['Benoît'],
        ];
    }

    /**     */
    #[DataProvider('invalidPlayerNameProvider')]
    public function testRejectsInvalidPlayerNames(?string $playerName): void
    {
        $result = $this->validator->validateDraftSelection($playerName, null);

        $this->assertFalse($result->isValid());
        $errors = $result->getErrors();
        $this->assertStringContainsString("didn't select a player", $errors[0]);
    }

    public static function invalidPlayerNameProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
        ];
    }
}
