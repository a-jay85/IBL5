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

    public function testFlagsAssertNotNullOnStaticallyNonNullValue(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/tests/AssertNotNullOnNonNullFixture.php'],
            [
                [
                    '`assertNotNull()` is called on a value PHPStan already knows is '
                    . 'non-null (type `string`); the assertion always passes. Assert '
                    . 'against actual behavior instead.',
                    9,
                ],
            ],
        );
    }

    /**
     * The fixture holds every shape at once, so one expectation proves all four claims:
     * a comment-only broad catch is flagged (a comment parses as a `Nop`, not as an
     * empty body), a bare broad catch is flagged, and neither a narrow empty catch nor
     * a broad catch that actually asserts is.
     */
    public function testFlagsBroadEmptyCatchButAllowsNarrowOrAssertingCatch(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/tests/BroadEmptyCatchFixture.php'],
            [
                [
                    'Empty `catch (\Throwable)` in test method `testSwallowsEverything()` '
                    . 'swallows every failure the code under test can produce — including a '
                    . 'fatal on its first line — so the test cannot fail. Narrow the caught '
                    . 'type to the specific exception the test expects, use '
                    . '`expectException()`, or assert on post-state inside the catch.',
                    11,
                ],
                [
                    'Empty `catch (\Exception)` in test method '
                    . '`testSwallowsEverythingWithoutEvenAComment()` swallows every failure '
                    . 'the code under test can produce — including a fatal on its first line '
                    . '— so the test cannot fail. Narrow the caught type to the specific '
                    . 'exception the test expects, use `expectException()`, or assert on '
                    . 'post-state inside the catch.',
                    41,
                ],
            ],
        );
    }

    public function testAllowsAssertNotNullOnNullableOrMixedValue(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/tests/AssertNotNullNullableFixture.php'],
            [],
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
