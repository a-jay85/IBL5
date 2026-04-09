<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\RequireEscapedOutputRule;

/**
 * @extends RuleTestCase<RequireEscapedOutputRule>
 */
final class RequireEscapedOutputRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new RequireEscapedOutputRule();
    }

    public function testFlagsUnescapedVariableEchoInView(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/UnsafeEchoView.php'],
            [
                [
                    'Unescaped output in View. Wrap the expression in '
                    . 'HtmlSanitizer::e() (or another whitelisted safe helper), '
                    . 'or cast it to (int)/(float)/(bool) if it is numeric.',
                    10,
                ],
            ],
        );
    }

    public function testAllowsHtmlSanitizerEscapedEchoInView(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/SafeEchoView.php'],
            [],
        );
    }

    public function testAllowsIntegerCastEchoInView(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/IntCastEchoView.php'],
            [],
        );
    }

    public function testAllowsTernaryWithStringLiteralsInView(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/TernaryEchoView.php'],
            [],
        );
    }

    public function testFlagsInterpolatedStringEchoInView(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/InterpolatedEchoView.php'],
            [
                [
                    'Unescaped output in View. Wrap the expression in '
                    . 'HtmlSanitizer::e() (or another whitelisted safe helper), '
                    . 'or cast it to (int)/(float)/(bool) if it is numeric.',
                    10,
                ],
            ],
        );
    }

    public function testAllowsUnescapedEchoOutsideViewFiles(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/RegularClass.php'],
            [],
        );
    }

    public function testAllowsEchoInViewFileOutsideClassesDirectory(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/SomethingView.php'],
            [],
        );
    }
}
