<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanRawSuperglobalsRule;

/**
 * @extends RuleTestCase<BanRawSuperglobalsRule>
 */
final class BanRawSuperglobalsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanRawSuperglobalsRule();
    }

    public function testFlagsGetSuperglobalAccessInService(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/SuperglobalInService.php'],
            [
                [
                    'Direct $_GET access is banned outside the HTTP '
                    . 'boundary layer (Controllers, ApiHandlers, Bootstraps, Authenticators). '
                    . 'Accept typed inputs as parameters instead.',
                    5,
                ],
            ],
        );
    }

    public function testFlagsPostSuperglobalAccessInService(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/PostSuperglobalInService.php'],
            [
                [
                    'Direct $_POST access is banned outside the HTTP '
                    . 'boundary layer (Controllers, ApiHandlers, Bootstraps, Authenticators). '
                    . 'Accept typed inputs as parameters instead.',
                    5,
                ],
            ],
        );
    }

    public function testAllowsGetSuperglobalAccessInController(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/SuperglobalInController.php'],
            [],
        );
    }

    public function testAllowsGetSuperglobalAccessInApiHandler(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/SuperglobalInApiHandler.php'],
            [],
        );
    }

    public function testAllowsSuperglobalAccessInCsrfGuard(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/CsrfGuard.php'],
            [],
        );
    }

    public function testAllowsSuperglobalAccessInBootstrap(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/FooBootstrap.php'],
            [],
        );
    }

    public function testAllowsSuperglobalAccessInAuthenticator(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/FooAuthenticator.php'],
            [],
        );
    }

    public function testAllowsSuperglobalAccessInLeagueContext(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/LeagueContext.php'],
            [],
        );
    }

    public function testAllowsSuperglobalAccessInTestCookieOverrides(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/TestCookieOverrides.php'],
            [],
        );
    }

    public function testAllowsSuperglobalAccessOutsideClassesDirectory(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/OutsideClassesSuperglobal.php'],
            [],
        );
    }
}
