<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures;

/**
 * Basename-match exemption fixture: a class declaring the query-assertion helper
 * in a file named WideUnitTestCase.php is the sole legitimate definer and must
 * NOT be flagged.
 */
abstract class WideUnitTestCase
{
    protected function assertQueryExecuted(string $substring): void
    {
    }
}
