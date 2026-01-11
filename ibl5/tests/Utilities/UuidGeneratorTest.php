<?php

declare(strict_types=1);

namespace Tests\Utilities;

use PHPUnit\Framework\TestCase;
use Utilities\UuidGenerator;

/**
 * UuidGeneratorTest - Tests for UuidGenerator utility
 *
 * Covers UUID v4 generation including:
 * - Format validation (8-4-4-4-12 pattern)
 * - Version 4 indicators
 * - Uniqueness verification
 */
class UuidGeneratorTest extends TestCase
{
    // Format Validation

    public function testGenerateUuidReturnsString(): void
    {
        $uuid = UuidGenerator::generateUuid();
        
        $this->assertIsString($uuid);
    }

    public function testGenerateUuidReturnsCorrectLength(): void
    {
        $uuid = UuidGenerator::generateUuid();
        
        // UUID format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx = 36 characters
        $this->assertEquals(36, strlen($uuid));
    }

    public function testGenerateUuidMatchesPattern(): void
    {
        $uuid = UuidGenerator::generateUuid();
        
        // UUID v4 pattern: 8-4-4-4-12 hex characters with dashes
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
        $this->assertMatchesRegularExpression($pattern, $uuid);
    }

    public function testGenerateUuidContainsFourDashes(): void
    {
        $uuid = UuidGenerator::generateUuid();
        
        $dashCount = substr_count($uuid, '-');
        $this->assertEquals(4, $dashCount);
    }

    // UUID Version 4 Indicators

    public function testGenerateUuidHasVersion4Indicator(): void
    {
        $uuid = UuidGenerator::generateUuid();
        
        // The 13th character (after 2 dashes) should be '4' for version 4
        $parts = explode('-', $uuid);
        $this->assertStringStartsWith('4', $parts[2]);
    }

    public function testGenerateUuidHasVariantIndicator(): void
    {
        $uuid = UuidGenerator::generateUuid();
        
        // The first character of the 4th group should be 8, 9, a, or b
        $parts = explode('-', $uuid);
        $firstChar = $parts[3][0];
        $this->assertMatchesRegularExpression('/[89ab]/', $firstChar);
    }

    // Uniqueness

    public function testGenerateUuidProducesUniqueValues(): void
    {
        $uuids = [];
        for ($i = 0; $i < 100; $i++) {
            $uuids[] = UuidGenerator::generateUuid();
        }
        
        $uniqueUuids = array_unique($uuids);
        $this->assertCount(100, $uniqueUuids);
    }

    public function testGenerateUuidNeverReturnsSameValue(): void
    {
        $uuid1 = UuidGenerator::generateUuid();
        $uuid2 = UuidGenerator::generateUuid();
        
        $this->assertNotEquals($uuid1, $uuid2);
    }

    // Case Sensitivity

    public function testGenerateUuidUsesLowercaseHex(): void
    {
        $uuid = UuidGenerator::generateUuid();
        
        $this->assertEquals(strtolower($uuid), $uuid);
    }

    // Character Validation

    public function testGenerateUuidOnlyContainsHexAndDashes(): void
    {
        $uuid = UuidGenerator::generateUuid();
        
        $cleanedUuid = str_replace('-', '', $uuid);
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $cleanedUuid);
    }

    public function testGenerateUuidHas32HexCharacters(): void
    {
        $uuid = UuidGenerator::generateUuid();
        
        $cleanedUuid = str_replace('-', '', $uuid);
        $this->assertEquals(32, strlen($cleanedUuid));
    }
}
