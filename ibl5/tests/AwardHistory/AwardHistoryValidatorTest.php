<?php

declare(strict_types=1);


namespace Tests\AwardHistory;
use PHPUnit\Framework\TestCase;
use AwardHistory\AwardHistoryValidator;

/**
 * Tests for AwardHistoryValidator
 * 
 * Verifies input validation and sanitization for player awards search.
 */
final class AwardHistoryValidatorTest extends TestCase
{
    private AwardHistoryValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new AwardHistoryValidator();
    }

    // ==================== validateSearchParams Tests ====================

    public function testValidateSearchParamsReturnsAllKeys(): void
    {
        $result = $this->validator->validateSearchParams([]);
        
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('award', $result);
        $this->assertArrayHasKey('year', $result);
        $this->assertArrayHasKey('sortby', $result);
    }

    public function testValidateSearchParamsWithValidData(): void
    {
        $params = [
            'aw_name' => 'Johnson',
            'aw_Award' => 'MVP',
            'aw_year' => '2025',
            'aw_sortby' => '1',
        ];
        
        $result = $this->validator->validateSearchParams($params);
        
        $this->assertEquals('Johnson', $result['name']);
        $this->assertEquals('MVP', $result['award']);
        $this->assertEquals(2025, $result['year']);
        $this->assertEquals(1, $result['sortby']);
    }

    public function testValidateSearchParamsWithEmptyData(): void
    {
        $result = $this->validator->validateSearchParams([]);
        
        $this->assertNull($result['name']);
        $this->assertNull($result['award']);
        $this->assertNull($result['year']);
        $this->assertEquals(3, $result['sortby']); // Default to year
    }

    public function testValidateSearchParamsStripsHtmlTags(): void
    {
        $params = [
            'aw_name' => '<script>alert("XSS")</script>Johnson',
            'aw_Award' => '<b>MVP</b>',
        ];
        
        $result = $this->validator->validateSearchParams($params);
        
        $this->assertEquals('alert("XSS")Johnson', $result['name']);
        $this->assertEquals('MVP', $result['award']);
    }

    // ==================== validateStringParam Tests ====================

    public function testValidateStringParamWithValidString(): void
    {
        $result = $this->validator->validateStringParam('Johnson');
        $this->assertEquals('Johnson', $result);
    }

    public function testValidateStringParamTrimsWhitespace(): void
    {
        $result = $this->validator->validateStringParam('  Johnson  ');
        $this->assertEquals('Johnson', $result);
    }

    public function testValidateStringParamWithNull(): void
    {
        $result = $this->validator->validateStringParam(null);
        $this->assertNull($result);
    }

    public function testValidateStringParamWithEmptyString(): void
    {
        $result = $this->validator->validateStringParam('');
        $this->assertNull($result);
    }

    public function testValidateStringParamWithWhitespaceOnly(): void
    {
        $result = $this->validator->validateStringParam('   ');
        $this->assertNull($result);
    }

    public function testValidateStringParamRemovesHtmlTags(): void
    {
        $result = $this->validator->validateStringParam('<script>evil</script>');
        $this->assertEquals('evil', $result);
    }

    // ==================== validateYearParam Tests ====================

    public function testValidateYearParamWithValidYear(): void
    {
        $result = $this->validator->validateYearParam('2025');
        $this->assertEquals(2025, $result);
    }

    public function testValidateYearParamWithInteger(): void
    {
        $result = $this->validator->validateYearParam(2024);
        $this->assertEquals(2024, $result);
    }

    public function testValidateYearParamWithNull(): void
    {
        $result = $this->validator->validateYearParam(null);
        $this->assertNull($result);
    }

    public function testValidateYearParamWithEmptyString(): void
    {
        $result = $this->validator->validateYearParam('');
        $this->assertNull($result);
    }

    public function testValidateYearParamWithNonNumeric(): void
    {
        $result = $this->validator->validateYearParam('abc');
        $this->assertNull($result);
    }

    public function testValidateYearParamWithZero(): void
    {
        $result = $this->validator->validateYearParam('0');
        $this->assertNull($result);
    }

    public function testValidateYearParamWithNegative(): void
    {
        $result = $this->validator->validateYearParam('-2025');
        $this->assertNull($result);
    }

    // ==================== validateSortParam Tests ====================

    public function testValidateSortParamWithValidOption1(): void
    {
        $result = $this->validator->validateSortParam('1');
        $this->assertEquals(1, $result);
    }

    public function testValidateSortParamWithValidOption2(): void
    {
        $result = $this->validator->validateSortParam('2');
        $this->assertEquals(2, $result);
    }

    public function testValidateSortParamWithValidOption3(): void
    {
        $result = $this->validator->validateSortParam('3');
        $this->assertEquals(3, $result);
    }

    public function testValidateSortParamWithNull(): void
    {
        $result = $this->validator->validateSortParam(null);
        $this->assertEquals(3, $result); // Default to year
    }

    public function testValidateSortParamWithEmptyString(): void
    {
        $result = $this->validator->validateSortParam('');
        $this->assertEquals(3, $result);
    }

    public function testValidateSortParamWithInvalidNumber(): void
    {
        $result = $this->validator->validateSortParam('5');
        $this->assertEquals(3, $result);
    }

    public function testValidateSortParamWithNonNumeric(): void
    {
        $result = $this->validator->validateSortParam('abc');
        $this->assertEquals(3, $result);
    }

    public function testValidateSortParamWithZero(): void
    {
        $result = $this->validator->validateSortParam('0');
        $this->assertEquals(3, $result);
    }
}
