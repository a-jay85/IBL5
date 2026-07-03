<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\RequireTrustedAnnotationRule;

/**
 * @extends RuleTestCase<RequireTrustedAnnotationRule>
 */
final class RequireTrustedAnnotationRuleTest extends RuleTestCase
{
    private const MESSAGE = 'HtmlSanitizer::trusted() received an argument that is not a '
        . 'string/numeric literal, an (int)/(float)/(bool) cast, or a $this->...() render '
        . 'call. trusted() bypasses XSS escaping (ADR-0002), so the argument must be '
        . 'provably safe HTML. If it is genuinely pre-sanitized, suppress with '
        . '// @phpstan-ignore ibl.trustedVariable and a comment justifying why.';

    protected function getRule(): Rule
    {
        return new RequireTrustedAnnotationRule();
    }

    public function testFiresOnUnsafeArguments(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/TrustedUnsafe.php'],
            [
                [self::MESSAGE, 7],
                [self::MESSAGE, 8],
                [self::MESSAGE, 9],
            ],
        );
    }

    public function testDoesNotFireOnSafeArguments(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/TrustedSafe.php'],
            [],
        );
    }

    public function testInlineIgnoreSuppressesTheError(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/TrustedSuppressed.php'],
            [],
        );
    }
}
