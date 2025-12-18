<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Utilities\HtmlSanitizer;

class HtmlSanitizerTest extends TestCase
{
    /**
     * Test basic HTML escaping with apostrophe
     */
    public function testSafeHtmlOutputWithApostrophe(): void
    {
        // Simulating data stored in database with addslashes()
        $dbValue = "Jermaine O\\'Neal";
        
        $result = HtmlSanitizer::safeHtmlOutput($dbValue);
        
        // Should remove backslash and properly encode for HTML
        // Note: ENT_HTML5 encodes apostrophes as &apos;
        $this->assertEquals("Jermaine O&apos;Neal", $result);
    }

    /**
     * Test quote handling with double quotes
     */
    public function testSafeHtmlOutputWithDoubleQuote(): void
    {
        // Simulating data stored in database with addslashes()
        $dbValue = 'John \\"The Rock\\" Johnson';
        
        $result = HtmlSanitizer::safeHtmlOutput($dbValue);
        
        // Should remove backslash and properly encode for HTML
        $this->assertEquals("John &quot;The Rock&quot; Johnson", $result);
    }

    /**
     * Test special characters handling
     */
    public function testSafeHtmlOutputWithMultipleSpecialChars(): void
    {
        // Simulating data stored in database with addslashes()
        $dbValue = "O\\'Brien & D\\'Angelo";
        
        $result = HtmlSanitizer::safeHtmlOutput($dbValue);
        
        // Should handle both apostrophes and ampersands
        $this->assertEquals("O&apos;Brien &amp; D&apos;Angelo", $result);
    }

    /**
     * Test HTML tag escaping (XSS prevention)
     */
    public function testSafeHtmlOutputWithHTMLTags(): void
    {
        // Simulating malicious input stored in database
        $dbValue = "<script>alert(\\'xss\\')</script>";
        
        $result = HtmlSanitizer::safeHtmlOutput($dbValue);
        
        // Should encode HTML tags and handle escaped quotes
        $this->assertEquals("&lt;script&gt;alert(&apos;xss&apos;)&lt;/script&gt;", $result);
    }

    /**
     * Test safe use in HTML attributes
     */
    public function testSafeHtmlOutputForHtmlAttributeWithApostrophe(): void
    {
        // Simulating data stored in database with addslashes()
        $dbValue = "Jermaine O\\'Neal";
        
        $result = HtmlSanitizer::safeHtmlOutput($dbValue);
        
        // Should be safe for use in HTML attributes
        $this->assertEquals("Jermaine O&apos;Neal", $result);
        
        // Verify it's safe in an HTML attribute context
        $html = "<input type='hidden' value='$result'>";
        $this->assertStringNotContainsString("\\'", $html);
        $this->assertStringNotContainsString("'Neal'", $html);
    }

    /**
     * Test plain text without special characters
     */
    public function testSafeHtmlOutputWithNoEscaping(): void
    {
        // Plain text without special characters
        $dbValue = "John Smith";
        
        $result = HtmlSanitizer::safeHtmlOutput($dbValue);
        
        $this->assertEquals("John Smith", $result);
    }

    /**
     * Test backslash removal (stripslashes behavior)
     */
    public function testSafeHtmlOutputWithBackslashBeforeN(): void
    {
        // Edge case: backslash before 'n' (not a newline escape)
        $dbValue = "Player\\nName";
        
        $result = HtmlSanitizer::safeHtmlOutput($dbValue);
        
        // stripslashes will remove single backslash
        $this->assertEquals("PlayernName", $result);
    }

    /**
     * Test unicode character preservation
     */
    public function testSafeHtmlOutputPreservesUnicode(): void
    {
        // UTF-8 characters should be preserved
        $dbValue = "José García";
        
        $result = HtmlSanitizer::safeHtmlOutput($dbValue);
        
        $this->assertEquals("José García", $result);
    }

    /**
     * Test real-world HTML usage scenario
     */
    public function testSafeHtmlOutputInActualHTML(): void
    {
        // Real-world scenario: name in hidden input
        $dbValue = "Shaquille O\\'Neal";
        $safeName = HtmlSanitizer::safeHtmlOutput($dbValue);
        
        // Construct HTML like DepthChartView does
        $html = "<input type=\"hidden\" name=\"Name1\" value=\"$safeName\">";
        
        // Verify the HTML is well-formed
        $this->assertStringContainsString('value="Shaquille O&apos;Neal"', $html);
        $this->assertStringNotContainsString("\\'", $html);
    }

    /**
     * Test custom flags parameter
     */
    public function testSafeHtmlOutputWithCustomFlags(): void
    {
        // Test with ENT_NOQUOTES flag
        $dbValue = "Test 'with' \"quotes\"";
        
        $result = HtmlSanitizer::safeHtmlOutput($dbValue, ENT_NOQUOTES | ENT_HTML5);
        
        // With ENT_NOQUOTES, quotes should not be encoded
        $this->assertEquals("Test 'with' \"quotes\"", $result);
    }

    /**
     * Test less-than and greater-than symbols
     */
    public function testSafeHtmlOutputWithLessThanGreaterThan(): void
    {
        $dbValue = "Score: 5 < 10 && 10 > 5";
        
        $result = HtmlSanitizer::safeHtmlOutput($dbValue);
        
        // Should encode < and > symbols
        $this->assertEquals("Score: 5 &lt; 10 &amp;&amp; 10 &gt; 5", $result);
    }

    /**
     * Test empty string
     */
    public function testSafeHtmlOutputWithEmptyString(): void
    {
        $result = HtmlSanitizer::safeHtmlOutput("");
        
        $this->assertEquals("", $result);
    }
}
