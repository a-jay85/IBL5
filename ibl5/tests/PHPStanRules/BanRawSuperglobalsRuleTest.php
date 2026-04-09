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
                    'Direct $_GET access is banned outside Controllers, ApiHandlers, '
                    . 'and Utilities\CsrfGuard. Accept typed inputs as parameters '
                    . 'from a Controller/ApiHandler instead.',
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
                    'Direct $_POST access is banned outside Controllers, ApiHandlers, '
                    . 'and Utilities\CsrfGuard. Accept typed inputs as parameters '
                    . 'from a Controller/ApiHandler instead.',
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

    public function testAllowsSuperglobalAccessOutsideClassesDirectory(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/OutsideClassesSuperglobal.php'],
            [],
        );
    }
}
