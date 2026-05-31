<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanSqlStringInterpolationRule;

/**
 * @extends RuleTestCase<BanSqlStringInterpolationRule>
 */
final class BanSqlStringInterpolationRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanSqlStringInterpolationRule();
    }

    public function testFlagsInterpolatedSqlStrings(): void
    {
        $message = 'SQL string uses variable interpolation. Splicing a variable into query '
            . 'text risks SQL injection. Use bound parameters (?) for values, and a '
            . 'validated allowlist/match() for identifiers (table/column names).';

        $this->analyse(
            [__DIR__ . '/Fixtures/classes/SqlStringInterpolation.php'],
            [
                [$message, 7],
                [$message, 8],
                [$message, 9],
            ],
        );
    }

    public function testAllowsConcatenationParameterizedAndProse(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/SafeSqlString.php'],
            [],
        );
    }
}
