<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Bootstrap\ConfigBootstrap;
use PHPUnit\Framework\TestCase;

final class ConfigBootstrapExtractRequestTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalRequest;
    /** @var array<string, mixed> */
    private array $originalGlobals;

    protected function setUp(): void
    {
        $this->originalRequest = $_REQUEST;
        $this->originalGlobals = $GLOBALS;
    }

    protected function tearDown(): void
    {
        $_REQUEST = $this->originalRequest;
        foreach (array_diff_key($GLOBALS, $this->originalGlobals) as $key => $_) {
            unset($GLOBALS[$key]);
        }
    }

    private function runExtract(): void
    {
        $bootstrap = new ConfigBootstrap(__DIR__ . '/../../');
        $method = new \ReflectionMethod($bootstrap, 'extractRequestToGlobals');
        $method->invoke($bootstrap);
    }

    public function testProtectedGlobalsAreNotOverwrittenFromRequest(): void
    {
        $GLOBALS['dbpass'] = 'real-password';
        $_REQUEST = ['dbpass' => 'attacker'];
        $this->runExtract();
        self::assertSame('real-password', $GLOBALS['dbpass']);
    }

    public function testNewlangSanitizeRuleStillApplies(): void
    {
        $_REQUEST = ['newlang' => 'en'];
        $this->runExtract();
        self::assertSame('en', $GLOBALS['newlang']);
    }

    public function testRedirectSanitizeRuleStillApplies(): void
    {
        $_REQUEST = ['redirect' => 'abc123'];
        $this->runExtract();
        self::assertSame('abc123', $GLOBALS['redirect']);
    }

    public function testAllowableHtmlCannotBeOverwrittenFromRequest(): void
    {
        $GLOBALS['AllowableHTML'] = ['b' => 1];
        $_REQUEST = ['AllowableHTML' => 'attacker-value'];
        $this->runExtract();
        self::assertSame(['b' => 1], $GLOBALS['AllowableHTML']);
    }

    public function testNewlangRejectsPathTraversal(): void
    {
        $_REQUEST = ['newlang' => '../../etc/passwd'];
        $this->runExtract();
        self::assertArrayNotHasKey('newlang', $GLOBALS);
    }

    public function testNewlangRejectsThreeLetterCode(): void
    {
        $_REQUEST = ['newlang' => 'eng'];
        $this->runExtract();
        self::assertArrayNotHasKey('newlang', $GLOBALS);
    }

    public function testArbitraryRequestKeyIsBlocked(): void
    {
        $_REQUEST = ['name' => 'Standings'];
        $this->runExtract();
        self::assertArrayNotHasKey('name', $GLOBALS);
    }
}
