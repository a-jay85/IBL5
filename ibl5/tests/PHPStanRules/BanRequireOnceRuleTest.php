<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanRequireOnceRule;

/**
 * @extends RuleTestCase<BanRequireOnceRule>
 */
final class BanRequireOnceRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanRequireOnceRule();
    }

    public function testFlagsRequireOnceInClasses(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/RequireOnceStatement.php'],
            [
                [
                    'Direct `require_once` is banned in classes/. All classes under '
                    . 'ibl5/classes/ autoload via Composer PSR-4. Remove the statement '
                    . 'and rely on autoload instead.',
                    5,
                ],
            ],
        );
    }

    public function testFlagsRequireInClasses(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/RequireStatement.php'],
            [
                [
                    'Direct `require` is banned in classes/. All classes under '
                    . 'ibl5/classes/ autoload via Composer PSR-4. Remove the statement '
                    . 'and rely on autoload instead.',
                    5,
                ],
            ],
        );
    }

    public function testFlagsIncludeInClasses(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/IncludeStatement.php'],
            [
                [
                    'Direct `include` is banned in classes/. All classes under '
                    . 'ibl5/classes/ autoload via Composer PSR-4. Remove the statement '
                    . 'and rely on autoload instead.',
                    5,
                ],
            ],
        );
    }

    public function testAllowsRequireOnceOutsideClasses(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/RequireOnceOutsideClasses.php'],
            [],
        );
    }
}
