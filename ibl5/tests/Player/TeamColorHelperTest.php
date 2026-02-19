<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\TestCase;
use Player\Views\TeamColorHelper;

/**
 * Tests for TeamColorHelper color contrast and accessibility
 * 
 * Verifies that all team color schemes meet WCAG AA accessibility standards
 * for text contrast (4.5:1 minimum for normal text, 3:1 for large text).
 * 
 * @see TeamColorHelper
 */
final class TeamColorHelperTest extends TestCase
{
    /**
     * Test that Orlando Magic colors meet WCAG AA standards
     * 
     * Orlando Magic (blue #0077c0 + black #000000) previously had poor
     * contrast with gray text. This test verifies the fix.
     */
    public function testOrlandoMagicColorContrastMeetsWCAGAA(): void
    {
        $colorScheme = TeamColorHelper::generateColorScheme('0077c0', '000000');
        
        $borderContrast = TeamColorHelper::getContrastRatio($colorScheme['border'], $colorScheme['primary']);
        $accentContrast = TeamColorHelper::getContrastRatio($colorScheme['accent'], $colorScheme['primary']);
        $textContrast = TeamColorHelper::getContrastRatio($colorScheme['text'], $colorScheme['primary']);
        $mutedContrast = TeamColorHelper::getContrastRatio($colorScheme['text_muted'], $colorScheme['primary']);
        
        // WCAG AA requires 4.5:1 for normal text
        $this->assertGreaterThanOrEqual(4.5, $borderContrast, 'Border contrast should meet WCAG AA');
        $this->assertGreaterThanOrEqual(4.5, $accentContrast, 'Accent contrast should meet WCAG AA');
        $this->assertGreaterThanOrEqual(4.5, $textContrast, 'Text contrast should meet WCAG AA');
        
        // WCAG AA requires 3:1 for large text
        $this->assertGreaterThanOrEqual(3.0, $mutedContrast, 'Muted text contrast should meet WCAG AA for large text');
    }
    
    /**
     * Test multiple team color combinations
     */
    public function testTeamColorsContrastMeetsAccessibilityStandards(): void
    {
        $teams = [
            ['Orlando Magic', '0077c0', '000000'],
            ['LA Lakers', 'FDB927', '552583'],
            ['Boston Celtics', '007A33', 'BA9653'],
            ['Chicago Bulls', 'CE1141', '000000'],
            ['Miami Heat', '98002E', 'F9A01B'],
            ['Golden State Warriors', '006BB6', 'FDB927'],
        ];
        
        foreach ($teams as [$teamName, $color1, $color2]) {
            $colorScheme = TeamColorHelper::generateColorScheme($color1, $color2);
            
            $borderContrast = TeamColorHelper::getContrastRatio($colorScheme['border'], $colorScheme['primary']);
            $accentContrast = TeamColorHelper::getContrastRatio($colorScheme['accent'], $colorScheme['primary']);
            $textContrast = TeamColorHelper::getContrastRatio($colorScheme['text'], $colorScheme['primary']);
            $mutedContrast = TeamColorHelper::getContrastRatio($colorScheme['text_muted'], $colorScheme['primary']);
            
            // All teams should meet minimum standards
            $this->assertGreaterThanOrEqual(4.5, $borderContrast, "$teamName border contrast fails WCAG AA");
            $this->assertGreaterThanOrEqual(4.5, $accentContrast, "$teamName accent contrast fails WCAG AA");
            $this->assertGreaterThanOrEqual(4.5, $textContrast, "$teamName text contrast fails WCAG AA");
            $this->assertGreaterThanOrEqual(3.0, $mutedContrast, "$teamName muted text contrast fails WCAG AA for large text");
        }
    }
    
    /**
     * Test that hex to RGB conversion works correctly
     */
    public function testHexToRgbConversion(): void
    {
        // Test 6-digit hex
        $rgb = TeamColorHelper::hexToRgb('0077c0');
        $this->assertSame(0, $rgb['r']);
        $this->assertSame(119, $rgb['g']);
        $this->assertSame(192, $rgb['b']);
        
        // Test 3-digit hex
        $rgb = TeamColorHelper::hexToRgb('fff');
        $this->assertSame(255, $rgb['r']);
        $this->assertSame(255, $rgb['g']);
        $this->assertSame(255, $rgb['b']);
        
        // Test with hash prefix
        $rgb = TeamColorHelper::hexToRgb('#000000');
        $this->assertSame(0, $rgb['r']);
        $this->assertSame(0, $rgb['g']);
        $this->assertSame(0, $rgb['b']);
    }
    
    /**
     * Test luminance calculation
     */
    public function testGetLuminance(): void
    {
        // White should have luminance of 1
        $whiteLum = TeamColorHelper::getLuminance(['r' => 255, 'g' => 255, 'b' => 255]);
        $this->assertEqualsWithDelta(1.0, $whiteLum, 0.01);
        
        // Black should have luminance of 0
        $blackLum = TeamColorHelper::getLuminance(['r' => 0, 'g' => 0, 'b' => 0]);
        $this->assertEqualsWithDelta(0.0, $blackLum, 0.01);
    }
    
    /**
     * Test contrast ratio calculation
     */
    public function testGetContrastRatio(): void
    {
        // White on black should have maximum contrast (21:1)
        $contrast = TeamColorHelper::getContrastRatio('ffffff', '000000');
        $this->assertEqualsWithDelta(21.0, $contrast, 0.1);
        
        // Same colors should have minimum contrast (1:1)
        $contrast = TeamColorHelper::getContrastRatio('ffffff', 'ffffff');
        $this->assertEqualsWithDelta(1.0, $contrast, 0.1);
    }
    
    /**
     * Test isDark helper method
     */
    public function testIsDark(): void
    {
        $this->assertTrue(TeamColorHelper::isDark('000000'), 'Black should be dark');
        $this->assertTrue(TeamColorHelper::isDark('0077c0'), 'Dark blue should be dark');
        $this->assertFalse(TeamColorHelper::isDark('ffffff'), 'White should not be dark');
        $this->assertFalse(TeamColorHelper::isDark('FDB927'), 'Yellow should not be dark');
    }
    
    /**
     * Test getTextColor helper method
     */
    public function testGetTextColor(): void
    {
        // Dark backgrounds should get white text
        $this->assertSame('ffffff', TeamColorHelper::getTextColor('000000'));
        $this->assertSame('ffffff', TeamColorHelper::getTextColor('0077c0'));
        
        // Light backgrounds should get black text
        $this->assertSame('000000', TeamColorHelper::getTextColor('ffffff'));
        $this->assertSame('000000', TeamColorHelper::getTextColor('FDB927'));
    }
    
    /**
     * Test lighten color manipulation
     */
    public function testLighten(): void
    {
        $lightened = TeamColorHelper::lighten('000000', 50);
        $rgb = TeamColorHelper::hexToRgb($lightened);
        
        // Should be gray (halfway between black and white)
        $this->assertGreaterThan(100, $rgb['r']);
        $this->assertGreaterThan(100, $rgb['g']);
        $this->assertGreaterThan(100, $rgb['b']);
        $this->assertLessThan(155, $rgb['r']);
        $this->assertLessThan(155, $rgb['g']);
        $this->assertLessThan(155, $rgb['b']);
    }
    
    /**
     * Test darken color manipulation
     */
    public function testDarken(): void
    {
        $darkened = TeamColorHelper::darken('ffffff', 50);
        $rgb = TeamColorHelper::hexToRgb($darkened);
        
        // Should be gray (halfway between white and black)
        $this->assertGreaterThan(100, $rgb['r']);
        $this->assertGreaterThan(100, $rgb['g']);
        $this->assertGreaterThan(100, $rgb['b']);
        $this->assertLessThan(155, $rgb['r']);
        $this->assertLessThan(155, $rgb['g']);
        $this->assertLessThan(155, $rgb['b']);
    }
}
