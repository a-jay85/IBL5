<?php

declare(strict_types=1);

namespace Tests\Auth;

use Auth\DemoLoginGate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DemoLoginGateTest extends TestCase
{
    /** @var string|false */
    private string|false $originalEnvVar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalEnvVar = getenv('DEMO_LOGIN_TOKEN');
        putenv('DEMO_LOGIN_TOKEN');
    }

    protected function tearDown(): void
    {
        if ($this->originalEnvVar !== false) {
            putenv('DEMO_LOGIN_TOKEN=' . $this->originalEnvVar);
        } else {
            putenv('DEMO_LOGIN_TOKEN');
        }
        parent::tearDown();
    }

    // --- isEnabled: fail-closed on empty / weak ---

    public function testIsDisabledWhenTokenEmpty(): void
    {
        self::assertFalse(DemoLoginGate::isEnabled(''));
    }

    public function testIsDisabledWhenTokenIsWeakDefault(): void
    {
        self::assertFalse(DemoLoginGate::isEnabled('demo'));
        self::assertFalse(DemoLoginGate::isEnabled(DemoLoginGate::WEAK_TOKEN));
    }

    #[DataProvider('strongTokenProvider')]
    public function testIsEnabledWhenTokenIsStrong(string $token): void
    {
        self::assertTrue(DemoLoginGate::isEnabled($token));
    }

    /**
     * @return array<string, list<string>>
     */
    public static function strongTokenProvider(): array
    {
        return [
            'random 32-char' => ['9f3b1c7e4a2d6058f1e8c0b7a4d92e6f'],
            'weak-as-substring is still strong' => ['demo-but-much-longer-and-random-xyz'],
            'differs only in case from weak' => ['DEMO'],
        ];
    }

    // --- isAuthorized: positive path (matrix #5) + negatives (matrix #4) ---

    public function testIsAuthorizedWithValidConfiguredTokenSucceeds(): void
    {
        $configured = '9f3b1c7e4a2d6058f1e8c0b7a4d92e6f';
        self::assertTrue(DemoLoginGate::isAuthorized($configured, $configured));
    }

    public function testIsNotAuthorizedWhenSuppliedTokenWrong(): void
    {
        self::assertFalse(
            DemoLoginGate::isAuthorized('9f3b1c7e4a2d6058f1e8c0b7a4d92e6f', 'wrong-token')
        );
    }

    public function testIsNotAuthorizedWhenConfiguredTokenIsWeak(): void
    {
        // Even supplying the matching weak token must fail — demo login is disabled.
        self::assertFalse(DemoLoginGate::isAuthorized('demo', 'demo'));
    }

    public function testIsNotAuthorizedWhenConfiguredTokenEmpty(): void
    {
        self::assertFalse(DemoLoginGate::isAuthorized('', ''));
        self::assertFalse(DemoLoginGate::isAuthorized('', 'anything'));
    }

    // --- resolveExpectedToken: env precedence + empty-env fallthrough ---

    public function testEnvTokenTakesPrecedenceOverConstant(): void
    {
        putenv('DEMO_LOGIN_TOKEN=env-supplied-strong-token');
        self::assertSame('env-supplied-strong-token', DemoLoginGate::resolveExpectedToken());
    }

    public function testEmptyEnvFallsThroughToConstant(): void
    {
        // An empty env var must NOT win; resolution falls through to the constant
        // (or '' when no constant is defined). Either way it must match the
        // constant-fallback value exactly.
        putenv('DEMO_LOGIN_TOKEN=');
        $expectedFallback = defined('DEMO_LOGIN_TOKEN') && is_string(constant('DEMO_LOGIN_TOKEN'))
            ? (string) constant('DEMO_LOGIN_TOKEN')
            : '';
        self::assertSame($expectedFallback, DemoLoginGate::resolveExpectedToken());
    }
}
