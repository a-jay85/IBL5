<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanRedeclaredMockDbQueryHelperRule;

/**
 * @extends RuleTestCase<BanRedeclaredMockDbQueryHelperRule>
 */
final class BanRedeclaredMockDbQueryHelperRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanRedeclaredMockDbQueryHelperRule();
    }

    public function testFlagsRedeclaredAssertQueryExecuted(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/RedeclaresAssertQueryExecuted.php'],
            [
                [
                    'Test re-declares `assertQueryExecuted()`, which Tests\WideUnit\WideUnitTestCase already provides. '
                    . 'Extend Tests\WideUnit\WideUnitTestCase instead of re-implementing its query-assertion helper.',
                    9,
                ],
            ],
        );
    }

    public function testFlagsRedeclaredAssertQueryNotExecuted(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/RedeclaresAssertQueryNotExecuted.php'],
            [
                [
                    'Test re-declares `assertQueryNotExecuted()`, which Tests\WideUnit\WideUnitTestCase already provides. '
                    . 'Extend Tests\WideUnit\WideUnitTestCase instead of re-implementing its query-assertion helper.',
                    9,
                ],
            ],
        );
    }

    public function testDoesNotFlagWideUnitTestCaseDefiner(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/WideUnitTestCase.php'],
            [],
        );
    }

    public function testDoesNotFlagCleanTestClass(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/CleanRepositoryTest.php'],
            [],
        );
    }
}
