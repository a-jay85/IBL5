<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\PageLayoutHeaderBeforeCookieRule;

/**
 * @extends RuleTestCase<PageLayoutHeaderBeforeCookieRule>
 */
final class PageLayoutHeaderBeforeCookieRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new PageLayoutHeaderBeforeCookieRule();
    }

    /**
     * @return list<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/Fixtures/phpstan-fixtures.neon'];
    }

    public function testFlagsCookieReadBeforeHeader(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/CookieBeforeHeader.php'],
            [
                [
                    '$cookie[...] is read before PageLayout::header() in the same '
                    . 'method. PageLayout::header() populates $cookie with auth and '
                    . 'CSRF state — call it first, otherwise you will read stale or '
                    . 'missing values (see CsrfGuard MAX_TOKENS incident).',
                    10,
                ],
            ],
        );
    }

    public function testAllowsCookieReadAfterHeader(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/CookieAfterHeader.php'],
            [],
        );
    }

    public function testAllowsCookieReadWithoutHeaderCallInDifferentMethod(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/CookieWithoutHeader.php'],
            [
                [
                    '$cookie[...] is read before PageLayout::header() in the same '
                    . 'method. PageLayout::header() populates $cookie with auth and '
                    . 'CSRF state — call it first, otherwise you will read stale or '
                    . 'missing values (see CsrfGuard MAX_TOKENS incident).',
                    10,
                ],
            ],
        );
    }
}
