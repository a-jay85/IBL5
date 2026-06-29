<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanSqlStringConcatenationRule;

/**
 * @extends RuleTestCase<BanSqlStringConcatenationRule>
 */
final class BanSqlStringConcatenationRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanSqlStringConcatenationRule();
    }

    public function testFlagsNonConstantConcatenationIntoSql(): void
    {
        $message = 'SQL string concatenates a non-constant value. Concatenating a runtime '
            . 'value into query text risks SQL injection. Use bound parameters (?) for values, '
            . 'and a validated allowlist/match() for identifiers (table/column names).';

        $this->analyse(
            [__DIR__ . '/Fixtures/classes/SqlStringConcatenation.php'],
            [
                [$message, 11],
                [$message, 17],
            ],
        );
    }
}
