<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanReservedWordColumnsRule;

/**
 * @extends RuleTestCase<BanReservedWordColumnsRule>
 */
final class BanReservedWordColumnsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanReservedWordColumnsRule();
    }

    public function testFlagsBannedBacktickColumns(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/BannedBacktickColumn.php'],
            [
                [
                    'Banned backtick-quoted column reference `to` in SQL string. '
                    . 'Rename to `r_trans_off` (transition offense rating); the bare `to` column was a SQL reserved word.',
                    5,
                ],
                [
                    'Banned backtick-quoted column reference `do` in SQL string. '
                    . 'Rename to `r_drive_off` (drive offense rating); the bare `do` column was a SQL reserved word.',
                    6,
                ],
                [
                    'Banned backtick-quoted column reference `r_to` in SQL string. '
                    . 'Rename to `r_tvr` (live/snapshot turnover rating) or `r_trans_off` (hist transition offense rating) '
                    . '— `r_to` used to flip meaning across layers.',
                    7,
                ],
                [
                    'Banned backtick-quoted column reference `Start Date` in SQL string. '
                    . 'Rename to `start_date` — space-containing identifier banned.',
                    8,
                ],
                [
                    'Banned backtick-quoted column reference `End Date` in SQL string. '
                    . 'Rename to `end_date` — space-containing identifier banned.',
                    9,
                ],
                [
                    'Banned backtick-quoted column reference `key` in SQL string. '
                    . 'Rename to `cache_key` on the `cache` / `cache_locks` tables; '
                    . 'the bare `key` column is a SQL reserved word (migration 116).',
                    10,
                ],
            ],
        );
    }

    public function testAllowsPostMigrationNames(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/AllowedNewColumnNames.php'],
            [],
        );
    }
}
