<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\RequireMeaningfulAssertionsRule;

/**
 * @extends RuleTestCase<RequireMeaningfulAssertionsRule>
 */
final class RequireMeaningfulAssertionsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new RequireMeaningfulAssertionsRule();
    }

    public function testFlagsEmptyTestMethodBody(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/tests/EmptyTestBodyFixture.php'],
            [
                [
                    'Test method `testDoesNothing()` has an empty body. '
                    . 'Add meaningful assertions or delete the test.',
                    7,
                ],
            ],
        );
    }

    public function testFlagsAssertTrueTrue(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/tests/AssertTrueTrueFixture.php'],
            [
                [
                    'Trivial assertion `assertTrue(true)` always passes and does not '
                    . 'test anything. Delete the call or replace it with an assertion '
                    . 'against actual behavior.',
                    9,
                ],
            ],
        );
    }

    public function testFlagsAssertEqualsIdenticalLiterals(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/tests/AssertEqualsIdenticalFixture.php'],
            [
                [
                    'Equality assertion `assertEquals()` is called with two identical '
                    . 'literal arguments. This assertion is trivially true and does '
                    . 'not test anything. Compare against actual behavior instead.',
                    9,
                ],
            ],
        );
    }

    public function testAllowsMeaningfulAssertion(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/tests/MeaningfulAssertionFixture.php'],
            [],
        );
    }

    public function testAllowsEmptyNonTestMethod(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/tests/EmptyNonTestMethodFixture.php'],
            [],
        );
    }
}
