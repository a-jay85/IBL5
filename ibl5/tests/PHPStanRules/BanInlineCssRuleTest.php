<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanInlineCssRule;

/**
 * @extends RuleTestCase<BanInlineCssRule>
 */
final class BanInlineCssRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanInlineCssRule();
    }

    public function testFlagsInlineStyleBlockInClasses(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/InlineStyleBlock.php'],
            [
                [
                    'Inline `<style>` blocks are banned in PHP. Move CSS to '
                    . 'ibl5/design/components/ and reference the stylesheet instead.',
                    5,
                ],
            ],
        );
    }

    public function testFlagsInlineStyleAttributeInClasses(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/InlineStyleAttribute.php'],
            [
                [
                    'Inline `style="..."` attributes are banned in PHP. Move CSS to '
                    . 'ibl5/design/components/. Exception: `style="--foo: ..."` CSS '
                    . 'custom properties are allowed.',
                    5,
                ],
            ],
        );
    }

    public function testFlagsSingleQuotedStyleAttribute(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/InlineStyleSingleQuote.php'],
            [
                [
                    'Inline `style="..."` attributes are banned in PHP. Move CSS to '
                    . 'ibl5/design/components/. Exception: `style="--foo: ..."` CSS '
                    . 'custom properties are allowed.',
                    5,
                ],
            ],
        );
    }

    public function testAllowsCssCustomPropertyStyleAttribute(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/CssCustomProperty.php'],
            [],
        );
    }

    public function testAllowsInlineStyleOutsideClasses(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/InlineStyleOutsideClasses.php'],
            [],
        );
    }
}
