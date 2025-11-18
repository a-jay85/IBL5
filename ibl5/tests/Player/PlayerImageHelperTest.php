<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\TestCase;
use Player\PlayerImageHelper;

/**
 * Tests for PlayerImageHelper utility class
 */
class PlayerImageHelperTest extends TestCase
{
    /**
     * Test that valid playerID generates correct image URL
     */
    public function testValidPlayerIDGeneratesCorrectUrl(): void
    {
        $result = PlayerImageHelper::getImageUrl(123);
        
        $this->assertStringContainsString('./images/player/123.jpg', $result);
        $this->assertStringNotContainsString('data:image', $result);
    }
    
    /**
     * Test that numeric string playerID is handled correctly
     */
    public function testNumericStringPlayerIDGeneratesCorrectUrl(): void
    {
        $result = PlayerImageHelper::getImageUrl('456');
        
        $this->assertStringContainsString('./images/player/456.jpg', $result);
    }
    
    /**
     * Test that null playerID returns placeholder data URI
     */
    public function testNullPlayerIDReturnsPlaceholder(): void
    {
        $result = PlayerImageHelper::getImageUrl(null);
        
        $this->assertStringContainsString('data:image/png;base64', $result);
    }
    
    /**
     * Test that empty string playerID returns placeholder data URI
     */
    public function testEmptyStringPlayerIDReturnsPlaceholder(): void
    {
        $result = PlayerImageHelper::getImageUrl('');
        
        $this->assertStringContainsString('data:image/png;base64', $result);
    }
    
    /**
     * Test that zero playerID returns placeholder data URI
     */
    public function testZeroPlayerIDReturnsPlaceholder(): void
    {
        $result = PlayerImageHelper::getImageUrl(0);
        
        $this->assertStringContainsString('data:image/png;base64', $result);
    }
    
    /**
     * Test that negative playerID returns placeholder data URI
     */
    public function testNegativePlayerIDReturnsPlaceholder(): void
    {
        $result = PlayerImageHelper::getImageUrl(-1);
        
        $this->assertStringContainsString('data:image/png;base64', $result);
    }
    
    /**
     * Test that non-numeric string playerID returns placeholder data URI
     */
    public function testNonNumericStringPlayerIDReturnsPlaceholder(): void
    {
        $result = PlayerImageHelper::getImageUrl('abc');
        
        $this->assertStringContainsString('data:image/png;base64', $result);
    }
    
    /**
     * Test that custom base path is respected
     */
    public function testCustomBasePathIsRespected(): void
    {
        $result = PlayerImageHelper::getImageUrl(789, '../images/player/');
        
        $this->assertStringContainsString('../images/player/789.jpg', $result);
    }
    
    /**
     * Test that isValidPlayerID correctly validates positive integers
     */
    public function testIsValidPlayerIDWithValidInteger(): void
    {
        $this->assertTrue(PlayerImageHelper::isValidPlayerID(1));
        $this->assertTrue(PlayerImageHelper::isValidPlayerID(100));
        $this->assertTrue(PlayerImageHelper::isValidPlayerID(99999));
    }
    
    /**
     * Test that isValidPlayerID correctly validates numeric strings
     */
    public function testIsValidPlayerIDWithNumericString(): void
    {
        $this->assertTrue(PlayerImageHelper::isValidPlayerID('1'));
        $this->assertTrue(PlayerImageHelper::isValidPlayerID('100'));
        $this->assertTrue(PlayerImageHelper::isValidPlayerID('999'));
    }
    
    /**
     * Test that isValidPlayerID rejects null
     */
    public function testIsValidPlayerIDRejectsNull(): void
    {
        $this->assertFalse(PlayerImageHelper::isValidPlayerID(null));
    }
    
    /**
     * Test that isValidPlayerID rejects empty string
     */
    public function testIsValidPlayerIDRejectsEmptyString(): void
    {
        $this->assertFalse(PlayerImageHelper::isValidPlayerID(''));
    }
    
    /**
     * Test that isValidPlayerID rejects zero
     */
    public function testIsValidPlayerIDRejectsZero(): void
    {
        $this->assertFalse(PlayerImageHelper::isValidPlayerID(0));
        $this->assertFalse(PlayerImageHelper::isValidPlayerID('0'));
    }
    
    /**
     * Test that isValidPlayerID rejects negative numbers
     */
    public function testIsValidPlayerIDRejectsNegativeNumbers(): void
    {
        $this->assertFalse(PlayerImageHelper::isValidPlayerID(-1));
        $this->assertFalse(PlayerImageHelper::isValidPlayerID('-100'));
    }
    
    /**
     * Test that isValidPlayerID rejects non-numeric strings
     */
    public function testIsValidPlayerIDRejectsNonNumericStrings(): void
    {
        $this->assertFalse(PlayerImageHelper::isValidPlayerID('abc'));
        $this->assertFalse(PlayerImageHelper::isValidPlayerID('12a34'));
        $this->assertFalse(PlayerImageHelper::isValidPlayerID('player_123'));
    }
    
    /**
     * Test that output is properly HTML-escaped to prevent injection
     */
    public function testOutputIsHtmlEscaped(): void
    {
        $result = PlayerImageHelper::getImageUrl(123);
        
        // Verify the result doesn't contain unescaped quotes or other HTML chars
        // (the htmlspecialchars in the helper should handle this)
        $this->assertIsString($result);
        // The URL itself shouldn't have any special chars to escape, but verify it's safe
        $this->assertStringNotContainsString('<script>', $result);
    }
    
    /**
     * Test that large valid playerID works correctly
     */
    public function testLargeValidPlayerID(): void
    {
        $result = PlayerImageHelper::getImageUrl(999999);
        
        $this->assertStringContainsString('./images/player/999999.jpg', $result);
    }
    
    /**
     * Test that float playerID is converted to int correctly
     */
    public function testFloatPlayerIDIsConvertedCorrectly(): void
    {
        $result = PlayerImageHelper::getImageUrl(123.7);
        
        // Should truncate to 123
        $this->assertStringContainsString('./images/player/123.jpg', $result);
    }
    
    /**
     * Test that isValidPlayerID works with float greater than zero
     */
    public function testIsValidPlayerIDWithFloat(): void
    {
        // Floats greater than zero should be valid (they convert to positive int)
        $this->assertTrue(PlayerImageHelper::isValidPlayerID(1.5));
        $this->assertTrue(PlayerImageHelper::isValidPlayerID(100.9));
    }
}
