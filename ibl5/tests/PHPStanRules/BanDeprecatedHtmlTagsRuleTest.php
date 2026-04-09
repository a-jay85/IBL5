<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanDeprecatedHtmlTagsRule;

/**
 * @extends RuleTestCase<BanDeprecatedHtmlTagsRule>
 */
final class BanDeprecatedHtmlTagsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanDeprecatedHtmlTagsRule();
    }

    public function testFlagsBoldTag(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/BoldTag.php'],
            [
                [
                    'Deprecated HTML tag `<b>` found in string literal. Use <strong> instead.',
                    5,
                ],
            ],
        );
    }

    public function testFlagsCenterTag(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/CenterTag.php'],
            [
                [
                    'Deprecated HTML tag `<center>` found in string literal. Use CSS `text-align: center` instead.',
                    5,
                ],
            ],
        );
    }

    public function testAllowsBrTagWhichSharesPrefixWithB(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/BrTag.php'],
            [],
        );
    }

    public function testAllowsUlTagWhichSharesPrefixWithU(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/UlTag.php'],
            [],
        );
    }

    public function testAllowsDeprecatedTagOutsideClasses(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/BoldTagOutsideClasses.php'],
            [],
        );
    }
}
