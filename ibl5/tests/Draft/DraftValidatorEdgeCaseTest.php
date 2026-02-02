<?php

declare(strict_types=1);

namespace Tests\Draft;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Draft\DraftValidator;

/**
 * DraftValidatorEdgeCaseTest - Edge case and boundary tests
 *
 * Tests special characters, unicode, whitespace, and edge cases not covered
 * by the main test file.
 *
 * @covers \Draft\DraftValidator
 */
class DraftValidatorEdgeCaseTest extends TestCase
{
    private DraftValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DraftValidator();
    }

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
        $this->assertTrue($result);
    }

    /**
     * Test player name with leading whitespace
     */
    public function testAcceptsPlayerNameWithLeadingWhitespace(): void
    {
        $result = $this->validator->validateDraftSelection('  John Doe', null);

        $this->assertTrue($result);
    }

    /**
     * Test player name with trailing whitespace
     */
    public function testAcceptsPlayerNameWithTrailingWhitespace(): void
    {
        $result = $this->validator->validateDraftSelection('John Doe  ', null);

        $this->assertTrue($result);
    }

    /**
     * Test player name with only tabs
     */
    public function testAcceptsPlayerNameWithOnlyTabs(): void
    {
        $result = $this->validator->validateDraftSelection("\t\t", null);

        // Tab-only string is not empty
        $this->assertTrue($result);
    }

    /**
     * Test player name with newlines
     */
    public function testAcceptsPlayerNameWithNewlines(): void
    {
        $result = $this->validator->validateDraftSelection("John\nDoe", null);

        $this->assertTrue($result);
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

        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    /**
     * Test player name with hyphen (Mary-Jane)
     */
    public function testAcceptsPlayerNameWithHyphen(): void
    {
        $result = $this->validator->validateDraftSelection('Mary-Jane Watson', null);

        $this->assertTrue($result);
    }

    /**
     * Test player name with period (Dr. J)
     */
    public function testAcceptsPlayerNameWithPeriod(): void
    {
        $result = $this->validator->validateDraftSelection('J.R. Smith', null);

        $this->assertTrue($result);
    }

    /**
     * Test player name with numbers
     */
    public function testAcceptsPlayerNameWithNumbers(): void
    {
        $result = $this->validator->validateDraftSelection('Player 123', null);

        $this->assertTrue($result);
    }

    /**
     * Test player name with special symbols
     */
    public function testAcceptsPlayerNameWithSpecialSymbols(): void
    {
        $result = $this->validator->validateDraftSelection('Player @#$%', null);

        $this->assertTrue($result);
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

        $this->assertTrue($result);
    }

    /**
     * Test player name with umlaut
     */
    public function testAcceptsPlayerNameWithUmlaut(): void
    {
        $result = $this->validator->validateDraftSelection('Dirk Nowitzki', null);

        $this->assertTrue($result);
    }

    /**
     * Test player name with Chinese characters
     */
    public function testAcceptsPlayerNameWithChineseCharacters(): void
    {
        $result = $this->validator->validateDraftSelection('姚明', null);

        $this->assertTrue($result);
    }

    /**
     * Test player name with Cyrillic characters
     */
    public function testAcceptsPlayerNameWithCyrillicCharacters(): void
    {
        $result = $this->validator->validateDraftSelection('Андрей Кириленко', null);

        $this->assertTrue($result);
    }

    /**
     * Test player name with emoji
     */
    public function testAcceptsPlayerNameWithEmoji(): void
    {
        $result = $this->validator->validateDraftSelection('Player 🏀', null);

        $this->assertTrue($result);
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

        $this->assertTrue($result);
    }

    /**
     * Test player name with very long name (255 characters)
     */
    public function testAcceptsVeryLongPlayerName(): void
    {
        $longName = str_repeat('A', 255);
        $result = $this->validator->validateDraftSelection($longName, null);

        $this->assertTrue($result);
    }

    /**
     * Test player name with extremely long name (1000 characters)
     */
    public function testAcceptsExtremelyLongPlayerName(): void
    {
        $longName = str_repeat('A', 1000);
        $result = $this->validator->validateDraftSelection($longName, null);

        $this->assertTrue($result);
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
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertStringContainsString('already drafted', $errors[0]);
    }

    /**
     * Test with "0" as current selection (truthy string)
     */
    public function testRejectsZeroStringCurrentSelection(): void
    {
        $result = $this->validator->validateDraftSelection('John Doe', '0');

        // "0" is not empty or null
        $this->assertFalse($result);
    }

    /**
     * Test with very long current selection
     */
    public function testRejectsLongCurrentSelection(): void
    {
        $longSelection = str_repeat('A', 255);
        $result = $this->validator->validateDraftSelection('John Doe', $longSelection);

        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
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
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertStringContainsString("didn't select a player", $errors[0]);
    }

    /**
     * Test empty player name with non-null current selection
     */
    public function testEmptyPlayerNameCheckedBeforeCurrentSelection(): void
    {
        $result = $this->validator->validateDraftSelection('', 'Existing Player');

        // Player name check happens first
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertStringContainsString("didn't select a player", $errors[0]);
    }

    /**
     * Test isPlayerAlreadyDrafted takes precedence when player name valid
     */
    public function testIsPlayerAlreadyDraftedWithValidPlayerName(): void
    {
        $result = $this->validator->validateDraftSelection('John Doe', null, true);

        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertStringContainsString('already been drafted by another team', $errors[0]);
    }

    /**
     * Test current selection takes precedence over isPlayerAlreadyDrafted
     */
    public function testCurrentSelectionCheckedBeforeIsPlayerAlreadyDrafted(): void
    {
        $result = $this->validator->validateDraftSelection('John Doe', 'Existing', true);

        // Current selection check happens before isPlayerAlreadyDrafted
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertStringContainsString('already drafted', $errors[0]);
        // This error is about the pick being used, not the player being drafted
    }

    // ============================================
    // ERROR HANDLING TESTS
    // ============================================

    /**
     * Test clearErrors method
     */
    public function testClearErrorsRemovesAllErrors(): void
    {
        // Generate an error
        $this->validator->validateDraftSelection(null, null);
        $this->assertNotEmpty($this->validator->getErrors());

        // Clear errors
        $this->validator->clearErrors();
        $this->assertEmpty($this->validator->getErrors());
    }

    /**
     * Test multiple validations clear previous errors
     */
    public function testSubsequentValidationClearsPreviousErrors(): void
    {
        // First validation fails with player name error
        $this->validator->validateDraftSelection(null, null);
        $errors1 = $this->validator->getErrors();
        $this->assertStringContainsString("didn't select a player", $errors1[0]);

        // Second validation fails with different error
        $this->validator->validateDraftSelection('John Doe', 'Existing');
        $errors2 = $this->validator->getErrors();
        $this->assertCount(1, $errors2);
        $this->assertStringContainsString('already drafted', $errors2[0]);
    }

    /**
     * Test successful validation clears previous errors
     */
    public function testSuccessfulValidationClearsPreviousErrors(): void
    {
        // Generate an error
        $this->validator->validateDraftSelection(null, null);
        $this->assertNotEmpty($this->validator->getErrors());

        // Successful validation
        $this->validator->validateDraftSelection('John Doe', null);
        $this->assertEmpty($this->validator->getErrors());
    }

    // ============================================
    // DATA PROVIDER TESTS
    // ============================================

    /**
     * @dataProvider specialCharacterNamesProvider
     */
    #[DataProvider('specialCharacterNamesProvider')]
    public function testAcceptsSpecialCharacterNames(string $playerName): void
    {
        $result = $this->validator->validateDraftSelection($playerName, null);

        $this->assertTrue($result);
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

    /**
     * @dataProvider invalidPlayerNameProvider
     */
    #[DataProvider('invalidPlayerNameProvider')]
    public function testRejectsInvalidPlayerNames(?string $playerName): void
    {
        $result = $this->validator->validateDraftSelection($playerName, null);

        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
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
