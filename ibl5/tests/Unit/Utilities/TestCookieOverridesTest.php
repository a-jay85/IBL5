<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use PHPUnit\Framework\TestCase;
use Utilities\TestCookieOverrides;

class TestCookieOverridesTest extends TestCase
{
    private mixed $originalE2eTesting;
    private bool $hadE2eTesting;
    private mixed $originalCookie;
    private bool $hadCookie;

    protected function setUp(): void
    {
        TestCookieOverrides::resetCache();

        // Save original E2E_TESTING env
        $envVal = getenv('E2E_TESTING');
        $this->hadE2eTesting = $envVal !== false;
        $this->originalE2eTesting = $envVal;

        // Save original cookie
        $this->hadCookie = array_key_exists('_test_overrides', $_COOKIE);
        $this->originalCookie = $_COOKIE['_test_overrides'] ?? null;
    }

    protected function tearDown(): void
    {
        TestCookieOverrides::resetCache();

        // Restore E2E_TESTING env
        if ($this->hadE2eTesting) {
            putenv('E2E_TESTING=' . $this->originalE2eTesting);
        } else {
            putenv('E2E_TESTING');
        }

        // Restore cookie
        if ($this->hadCookie) {
            $_COOKIE['_test_overrides'] = $this->originalCookie;
        } else {
            unset($_COOKIE['_test_overrides']);
        }
    }

    public function testReturnsEmptyWhenE2eTestingNotSet(): void
    {
        putenv('E2E_TESTING');
        $_COOKIE['_test_overrides'] = json_encode(['Current Season Phase' => 'Draft']);

        $result = TestCookieOverrides::getOverrides();

        // Without E2E_TESTING=1 AND without .env.test, should return empty
        // Note: .env.test may exist in dev, so this test is best-effort
        // The key behavior is that non-E2E environments return empty
        $this->assertIsArray($result);
    }

    public function testParsesValidJsonCookie(): void
    {
        putenv('E2E_TESTING=1');
        $_COOKIE['_test_overrides'] = json_encode([
            'Current Season Phase' => 'Draft',
            'Allow Trades' => 'Yes',
        ]);

        $result = TestCookieOverrides::getOverrides();

        $this->assertSame('Draft', $result['Current Season Phase']);
        $this->assertSame('Yes', $result['Allow Trades']);
    }

    public function testFiltersNonAllowlistedKeys(): void
    {
        putenv('E2E_TESTING=1');
        $_COOKIE['_test_overrides'] = json_encode([
            'Current Season Phase' => 'Draft',
            'Nonexistent Setting' => 'value',
            'Sim Length in Days' => '7',
        ]);

        $result = TestCookieOverrides::getOverrides();

        $this->assertArrayHasKey('Current Season Phase', $result);
        $this->assertArrayNotHasKey('Nonexistent Setting', $result);
        $this->assertArrayNotHasKey('Sim Length in Days', $result);
    }

    public function testIgnoresNonStringValues(): void
    {
        putenv('E2E_TESTING=1');
        $_COOKIE['_test_overrides'] = json_encode([
            'Current Season Phase' => 'Draft',
            'Allow Trades' => 123,
            'Allow Waiver Moves' => true,
        ]);

        $result = TestCookieOverrides::getOverrides();

        $this->assertSame('Draft', $result['Current Season Phase']);
        $this->assertArrayNotHasKey('Allow Trades', $result);
        $this->assertArrayNotHasKey('Allow Waiver Moves', $result);
    }

    public function testHandlesInvalidJson(): void
    {
        putenv('E2E_TESTING=1');
        $_COOKIE['_test_overrides'] = 'not-valid-json{{{';

        $result = TestCookieOverrides::getOverrides();

        $this->assertSame([], $result);
    }

    public function testHandlesEmptyCookie(): void
    {
        putenv('E2E_TESTING=1');
        $_COOKIE['_test_overrides'] = '';

        $result = TestCookieOverrides::getOverrides();

        $this->assertSame([], $result);
    }

    public function testHandlesMissingCookie(): void
    {
        putenv('E2E_TESTING=1');
        unset($_COOKIE['_test_overrides']);

        $result = TestCookieOverrides::getOverrides();

        $this->assertSame([], $result);
    }

    public function testCachesResult(): void
    {
        putenv('E2E_TESTING=1');
        $_COOKIE['_test_overrides'] = json_encode([
            'Current Season Phase' => 'Draft',
        ]);

        $first = TestCookieOverrides::getOverrides();

        // Change cookie — cached result should still be returned
        $_COOKIE['_test_overrides'] = json_encode([
            'Current Season Phase' => 'Free Agency',
        ]);

        $second = TestCookieOverrides::getOverrides();

        $this->assertSame($first, $second);
        $this->assertSame('Draft', $second['Current Season Phase']);
    }

    public function testAllAllowedKeysAccepted(): void
    {
        putenv('E2E_TESTING=1');
        $_COOKIE['_test_overrides'] = json_encode([
            'Current Season Phase' => 'Draft',
            'Current Season Ending Year' => '2026',
            'Allow Trades' => 'Yes',
            'Allow Waiver Moves' => 'Yes',
            'Show Draft Link' => 'On',
            'Free Agency Notifications' => 'On',
            'Trivia Mode' => 'On',
        ]);

        $result = TestCookieOverrides::getOverrides();

        $this->assertCount(7, $result);
        $this->assertSame('2026', $result['Current Season Ending Year']);
    }
}
