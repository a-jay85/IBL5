<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PlayerSearch\PlayerSearchValidator;

/**
 * Tests for PlayerSearchValidator
 * 
 * Covers input validation, sanitization, and security-related edge cases.
 */
final class PlayerSearchValidatorTest extends TestCase
{
    private PlayerSearchValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PlayerSearchValidator();
    }

    // ========== Position Validation Tests ==========

    public function testValidatePositionAcceptsValidPositions(): void
    {
        $this->assertEquals('PG', $this->validator->validatePosition('PG'));
        $this->assertEquals('SG', $this->validator->validatePosition('SG'));
        $this->assertEquals('SF', $this->validator->validatePosition('SF'));
        $this->assertEquals('PF', $this->validator->validatePosition('PF'));
        $this->assertEquals('C', $this->validator->validatePosition('C'));
    }

    public function testValidatePositionNormalizesCase(): void
    {
        $this->assertEquals('PG', $this->validator->validatePosition('pg'));
        $this->assertEquals('SF', $this->validator->validatePosition('sf'));
        $this->assertEquals('C', $this->validator->validatePosition('c'));
    }

    public function testValidatePositionRejectsInvalidPositions(): void
    {
        $this->assertNull($this->validator->validatePosition('INVALID'));
        $this->assertNull($this->validator->validatePosition('XX'));
        $this->assertNull($this->validator->validatePosition('12'));
    }

    public function testValidatePositionReturnsNullForEmpty(): void
    {
        $this->assertNull($this->validator->validatePosition(null));
        $this->assertNull($this->validator->validatePosition(''));
    }

    // ========== Integer Parameter Validation Tests ==========

    public function testValidateIntegerParamAcceptsValidIntegers(): void
    {
        $this->assertEquals(0, $this->validator->validateIntegerParam(0));
        $this->assertEquals(5, $this->validator->validateIntegerParam(5));
        $this->assertEquals(99, $this->validator->validateIntegerParam(99));
        $this->assertEquals(25, $this->validator->validateIntegerParam('25'));
    }

    public function testValidateIntegerParamRejectsNegativeNumbers(): void
    {
        $this->assertNull($this->validator->validateIntegerParam(-1));
        $this->assertNull($this->validator->validateIntegerParam('-5'));
    }

    public function testValidateIntegerParamRejectsNonNumeric(): void
    {
        $this->assertNull($this->validator->validateIntegerParam('abc'));
        $this->assertNull($this->validator->validateIntegerParam('12abc'));
    }

    public function testValidateIntegerParamReturnsNullForEmpty(): void
    {
        $this->assertNull($this->validator->validateIntegerParam(null));
        $this->assertNull($this->validator->validateIntegerParam(''));
    }

    // ========== String Parameter Validation Tests ==========

    public function testValidateStringParamAcceptsValidStrings(): void
    {
        $this->assertEquals('Jordan', $this->validator->validateStringParam('Jordan'));
        $this->assertEquals('UCLA', $this->validator->validateStringParam('UCLA'));
    }

    public function testValidateStringParamTrimsWhitespace(): void
    {
        $this->assertEquals('Test', $this->validator->validateStringParam('  Test  '));
        $this->assertEquals('Name', $this->validator->validateStringParam('Name '));
    }

    public function testValidateStringParamLimitsLength(): void
    {
        $longString = str_repeat('a', 100);
        $result = $this->validator->validateStringParam($longString);
        
        $this->assertEquals(64, mb_strlen($result));
    }

    public function testValidateStringParamReturnsNullForEmpty(): void
    {
        $this->assertNull($this->validator->validateStringParam(null));
        $this->assertNull($this->validator->validateStringParam(''));
        $this->assertNull($this->validator->validateStringParam('   ')); // Whitespace only
    }

    // ========== Boolean Parameter Validation Tests ==========

    public function testValidateBooleanParamAcceptsValidValues(): void
    {
        $this->assertEquals(0, $this->validator->validateBooleanParam(0));
        $this->assertEquals(1, $this->validator->validateBooleanParam(1));
        $this->assertEquals(0, $this->validator->validateBooleanParam('0'));
        $this->assertEquals(1, $this->validator->validateBooleanParam('1'));
    }

    public function testValidateBooleanParamRejectsInvalidValues(): void
    {
        $this->assertNull($this->validator->validateBooleanParam(2));
        $this->assertNull($this->validator->validateBooleanParam('yes'));
        $this->assertNull($this->validator->validateBooleanParam('true'));
    }

    public function testValidateBooleanParamReturnsNullForEmpty(): void
    {
        $this->assertNull($this->validator->validateBooleanParam(null));
        $this->assertNull($this->validator->validateBooleanParam(''));
    }

    // ========== Full Search Params Validation Tests ==========

    public function testValidateSearchParamsReturnsAllKeys(): void
    {
        $params = $this->validator->validateSearchParams([]);
        
        // Check that all expected keys exist
        $this->assertArrayHasKey('pos', $params);
        $this->assertArrayHasKey('age', $params);
        $this->assertArrayHasKey('search_name', $params);
        $this->assertArrayHasKey('college', $params);
        $this->assertArrayHasKey('exp', $params);
        $this->assertArrayHasKey('r_fga', $params);
        $this->assertArrayHasKey('oo', $params);
        $this->assertArrayHasKey('active', $params);
    }

    public function testValidateSearchParamsValidatesEachField(): void
    {
        $params = $this->validator->validateSearchParams([
            'pos' => 'PG',
            'age' => '25',
            'search_name' => 'Jordan',
            'oo' => '80'
        ]);

        $this->assertEquals('PG', $params['pos']);
        $this->assertEquals(25, $params['age']);
        $this->assertEquals('Jordan', $params['search_name']);
        $this->assertEquals(80, $params['oo']);
    }



    // ========== SQL Injection Prevention Tests ==========

    public function testValidateStringParamDoesNotBreakOnSqlInjectionAttempt(): void
    {
        // The validator should simply return the string, which will be safely
        // parameterized by the repository
        $malicious = "'; DROP TABLE ibl_plr; --";
        $result = $this->validator->validateStringParam($malicious);
        
        // Should be truncated but otherwise unmodified (escaping happens at DB level)
        $this->assertIsString($result);
        $this->assertLessThanOrEqual(64, mb_strlen($result));
    }

    public function testValidateIntegerParamRejectsSqlInjection(): void
    {
        // Attempt to inject SQL through integer field
        $this->assertNull($this->validator->validateIntegerParam("1; DROP TABLE"));
        $this->assertNull($this->validator->validateIntegerParam("1 OR 1=1"));
    }

}
