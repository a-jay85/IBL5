<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\OrderByRequiresUniqueTiebreakerRule;

/**
 * @extends RuleTestCase<OrderByRequiresUniqueTiebreakerRule>
 */
final class OrderByRequiresUniqueTiebreakerRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new OrderByRequiresUniqueTiebreakerRule();
    }

    public function testFlagged(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/OrderByMissingTiebreaker.php'],
            [
                [
                    'ORDER BY on rendered/LIMIT-cut output must end in a unique tiebreaker column '
                    . '(e.g. append ", pid ASC"); final sort term "pct" is not a '
                    . 'recognized-unique column. See ADR-0083. Acknowledge a genuine exception with '
                    . '// @phpstan-ignore ibl.orderByMissingTiebreaker.',
                    11,
                ],
                [
                    'ORDER BY on rendered/LIMIT-cut output must end in a unique tiebreaker column '
                    . '(e.g. append ", pid ASC"); final sort term "wins" is not a '
                    . 'recognized-unique column. See ADR-0083. Acknowledge a genuine exception with '
                    . '// @phpstan-ignore ibl.orderByMissingTiebreaker.',
                    16,
                ],
            ],
        );
    }

    public function testAllowed(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/OrderByWithTiebreaker.php'],
            [],
        );
    }
}
