<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanRawHtmlEscapeFunctionsRule;

/**
 * @extends RuleTestCase<BanRawHtmlEscapeFunctionsRule>
 */
final class BanRawHtmlEscapeFunctionsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanRawHtmlEscapeFunctionsRule();
    }

    public function testFlagsRawHtmlspecialcharsInService(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/RawHtmlEscapeInService.php'],
            [
                [
                    'htmlspecialchars() is banned. Use HtmlSanitizer::e() instead of raw '
                    . 'htmlspecialchars/htmlentities (canonical flags + charset).',
                    5,
                ],
            ],
        );
    }

    public function testAllowsHtmlspecialcharsInHtmlSanitizer(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/Allowed/HtmlSanitizer.php'],
            [],
        );
    }

    public function testAllowsHtmlspecialcharsInDebugOutput(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/Allowed/DebugOutput.php'],
            [],
        );
    }
}
