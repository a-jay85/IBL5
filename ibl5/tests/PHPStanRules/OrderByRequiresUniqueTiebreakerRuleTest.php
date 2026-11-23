<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\OrderByRequiresUniqueTiebreakerRule;
use PHPStanRules\SchemaKeyMap;

/**
 * @extends RuleTestCase<OrderByRequiresUniqueTiebreakerRule>
 */
final class OrderByRequiresUniqueTiebreakerRuleTest extends RuleTestCase
{
    private const FIXTURE_DIR = __DIR__ . '/Fixtures/classes/OrderByTiebreaker';

    protected function getRule(): Rule
    {
        return new OrderByRequiresUniqueTiebreakerRule(self::FIXTURE_DIR . '/fixture-schema.sql');
    }

    public function testAcceptsKeyCoveredOrderings(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/KeyCoveredQueries.php'], []);
    }

    public function testRejectsOrderingsNoKeyCovers(): void
    {
        $this->analyse(
            [self::FIXTURE_DIR . '/UnprovenQueries.php'],
            [
                [self::msg('sid'), 11],
                [self::msg('award'), 16],
                [self::msg('note'), 21],
                [self::msg('award'), 26],
                [self::msg('award'), 31],
                [self::msg('award'), 36],
                [self::msg('name'), 41],
                [self::msg('award'), 46],
                [self::msg('name'), 51],
                [self::msg('name'), 56],
            ],
        );
    }

    public function testRejectsUnresolvableQueryShapes(): void
    {
        $this->analyse(
            [self::FIXTURE_DIR . '/UnresolvableQueries.php'],
            [
                [self::msg('table_id'), 11],
                [self::msg('table_id'), 16],
                [self::msg('name'), 21],
                [self::msg('name'), 26],
                [self::msg('table_id'), 31],
                [self::msg('table_id'), 36],
            ],
        );
    }

    public function testKeepsCarveOutsAndLegacyAllowlist(): void
    {
        $this->analyse([self::FIXTURE_DIR . '/CarveOutQueries.php'], []);
    }

    public function testLoadsKeysFromLiveSchemaDump(): void
    {
        $map = SchemaKeyMap::fromSchemaDump(dirname(__DIR__, 2) . '/docs/schema/current-schema.sql');

        self::assertSame([['sid']], $map->keysFor('nuke_stories'));
        self::assertContains(['pickid'], $map->keysFor('ibl_draft_picks') ?? []);
        self::assertNull($map->keysFor('vw_series_records'));
    }

    public function testMissingDumpYieldsEmptyKeyMap(): void
    {
        $map = SchemaKeyMap::fromSchemaDump(__DIR__ . '/Fixtures/no-such-dump.sql');

        self::assertNull($map->keysFor('fx_awards'));
        self::assertNull($map->keysFor('nuke_stories'));
    }

    private static function msg(string $col): string
    {
        return 'ORDER BY on rendered/LIMIT-cut output must end in a unique tiebreaker column '
            . '(e.g. append ", pid ASC"); final sort term "' . $col . '" is not a '
            . 'recognized-unique column. See ADR-0083. Acknowledge a genuine exception with '
            . '// @phpstan-ignore ibl.orderByMissingTiebreaker.';
    }
}
