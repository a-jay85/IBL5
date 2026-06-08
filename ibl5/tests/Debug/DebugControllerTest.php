<?php

declare(strict_types=1);

namespace Tests\Debug;

use Debug\DebugController;
use PHPUnit\Framework\TestCase;

class DebugControllerTest extends TestCase
{
    public function testSanitizeRedirectPassesValidSameOriginPath(): void
    {
        $this->assertSame('/ibl5/team.php', DebugController::sanitizeRedirect('/ibl5/team.php'));
    }

    public function testSanitizeRedirectPassesQueryStringPath(): void
    {
        $this->assertSame('/path?host=x', DebugController::sanitizeRedirect('/path?host=x'));
    }

    public function testSanitizeRedirectRejectsEmptyString(): void
    {
        $this->assertSame('/ibl5/', DebugController::sanitizeRedirect(''));
    }

    public function testSanitizeRedirectRejectsNoLeadingSlash(): void
    {
        $this->assertSame('/ibl5/', DebugController::sanitizeRedirect('ibl5/team.php'));
    }

    public function testSanitizeRedirectRejectsProtocolRelativeUrl(): void
    {
        $this->assertSame('/ibl5/', DebugController::sanitizeRedirect('//evil.com/x'));
    }

    public function testSanitizeRedirectRejectsSchemeUrl(): void
    {
        $this->assertSame('/ibl5/', DebugController::sanitizeRedirect('https://evil.com'));
    }
}
