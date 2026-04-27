<?php

declare(strict_types=1);

namespace Tests\Mail;

use PHPUnit\Framework\TestCase;
use Mail\EmailSanitizer;

/**
 * @covers \Mail\EmailSanitizer
 */
class EmailSanitizerTest extends TestCase
{
    // --- sanitizeHeader() ---

    public function testSanitizeHeaderRemovesLineFeed(): void
    {
        $this->assertSame('foobar', EmailSanitizer::sanitizeHeader("foo\nbar"));
    }

    public function testSanitizeHeaderRemovesCarriageReturn(): void
    {
        $this->assertSame('foobar', EmailSanitizer::sanitizeHeader("foo\rbar"));
    }

    public function testSanitizeHeaderRemovesCRLF(): void
    {
        $this->assertSame('foobar', EmailSanitizer::sanitizeHeader("foo\r\nbar"));
    }

    public function testSanitizeHeaderRemovesNullByte(): void
    {
        $this->assertSame('foobar', EmailSanitizer::sanitizeHeader("foo\0bar"));
    }

    public function testSanitizeHeaderRemovesOtherControlCharacters(): void
    {
        // ASCII 1 (SOH), 8 (BS), 14 (SO), 31 (US)
        $input = "a\x01b\x08c\x0Ed\x1Fe";
        $this->assertSame('abcde', EmailSanitizer::sanitizeHeader($input));
    }

    public function testSanitizeHeaderPreservesTab(): void
    {
        $this->assertSame("foo\tbar", EmailSanitizer::sanitizeHeader("foo\tbar"));
    }

    public function testSanitizeHeaderRemovesUrlEncodedLineFeed(): void
    {
        $this->assertSame('foobar', EmailSanitizer::sanitizeHeader('foo%0Abar'));
    }

    public function testSanitizeHeaderRemovesUrlEncodedCarriageReturn(): void
    {
        $this->assertSame('foobar', EmailSanitizer::sanitizeHeader('foo%0Dbar'));
    }

    public function testSanitizeHeaderRemovesLowercaseUrlEncodedNewlines(): void
    {
        $this->assertSame('foobar', EmailSanitizer::sanitizeHeader('foo%0a%0dbar'));
    }

    public function testSanitizeHeaderPassthroughCleanValue(): void
    {
        $this->assertSame('Hello World 123!', EmailSanitizer::sanitizeHeader('Hello World 123!'));
    }

    public function testSanitizeHeaderRemovesInjectionAttempt(): void
    {
        $input = "Subject\r\nBCC: attacker@evil.com";
        $result = EmailSanitizer::sanitizeHeader($input);

        $this->assertStringNotContainsString("\r", $result);
        $this->assertStringNotContainsString("\n", $result);
        $this->assertSame('SubjectBCC: attacker@evil.com', $result);
    }

    // --- sanitizeSubject() ---

    public function testSanitizeSubjectStripsHtmlTags(): void
    {
        $this->assertSame('Hello World', EmailSanitizer::sanitizeSubject('<b>Hello</b> <i>World</i>'));
    }

    public function testSanitizeSubjectAppliesHeaderSanitization(): void
    {
        $this->assertSame('foobar', EmailSanitizer::sanitizeSubject("foo\nbar"));
    }

    public function testSanitizeSubjectTruncatesAtDefaultMaxLength(): void
    {
        $longString = str_repeat('a', 300);
        $result = EmailSanitizer::sanitizeSubject($longString);

        $this->assertSame(255, mb_strlen($result));
    }

    public function testSanitizeSubjectTruncatesAtCustomMaxLength(): void
    {
        $result = EmailSanitizer::sanitizeSubject('Hello World', 5);

        $this->assertSame('Hello', $result);
    }

    public function testSanitizeSubjectExactLengthIsNotTruncated(): void
    {
        $exactString = str_repeat('x', 255);
        $result = EmailSanitizer::sanitizeSubject($exactString);

        $this->assertSame(255, mb_strlen($result));
        $this->assertSame($exactString, $result);
    }

    // --- isValidEmail() ---

    public function testIsValidEmailAcceptsValidEmail(): void
    {
        $this->assertTrue(EmailSanitizer::isValidEmail('user@example.com'));
    }

    public function testIsValidEmailRejectsEmailWithoutAtSign(): void
    {
        $this->assertFalse(EmailSanitizer::isValidEmail('notanemail'));
    }

    public function testIsValidEmailRejectsEmptyString(): void
    {
        $this->assertFalse(EmailSanitizer::isValidEmail(''));
    }
}
