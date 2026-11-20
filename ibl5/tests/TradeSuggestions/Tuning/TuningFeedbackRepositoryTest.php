<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Tuning;

use Database\SqlPlaceholders;
use Tests\WideUnit\WideUnitTestCase;
use TradeSuggestions\Tuning\TuningFeedbackRepository;

final class TuningFeedbackRepositoryTest extends WideUnitTestCase
{
    public function testEmptyTeamListRunsNoQuery(): void
    {
        $repo = new TuningFeedbackRepository($this->mockDb());

        self::assertSame([], $repo->forTeams([]));
        $this->assertQueryNotExecuted('ibl_trade_suggestion_feedback');
    }

    /**
     * Security test: every tid is a bound placeholder, never SQL text. The
     * list is the fixed-width `SqlPlaceholders::PID_LIST`, padded with -1.
     */
    public function testPlaceholderCountMatchesTeamCount(): void
    {
        $db = $this->mockDb();
        $db->onQuery('ibl_trade_suggestion_feedback', []);

        (new TuningFeedbackRepository($db))->forTeams([1, 2, 8]);

        $prepared = implode("\n", $db->getPreparedQueries());
        self::assertStringContainsString('IN (' . SqlPlaceholders::PID_LIST . ')', $prepared);
        self::assertStringNotContainsString('8', $prepared);
        self::assertSame(SqlPlaceholders::PID_LIST_TYPES, $db->getLastBoundTypes());
        $params = $db->getLastBoundParams();
        self::assertCount(SqlPlaceholders::PID_LIST_WIDTH, $params);
        self::assertSame([1, 2, 8], array_slice($params, 0, 3));
        self::assertSame([-1], array_values(array_unique(array_slice($params, 3))));
    }

    public function testFreeTextAndReasonCodesPassThroughRaw(): void
    {
        $db = $this->mockDb();
        $db->onQuery('ibl_trade_suggestion_feedback', [self::row('TOO_OLD,OTHER', '<script>x</script>')]);

        $rows = (new TuningFeedbackRepository($db))->forTeams([4]);

        self::assertCount(1, $rows);
        self::assertSame('<script>x</script>', $rows[0]->freeText);
        self::assertSame(['TOO_OLD', 'OTHER'], $rows[0]->reasonCodes);
        self::assertSame(4, $rows[0]->tid);
        self::assertSame(9, $rows[0]->partnerTid);
    }

    public function testEmptyReasonStringGivesEmptyList(): void
    {
        $db = $this->mockDb();
        $db->onQuery('ibl_trade_suggestion_feedback', [self::row('', null)]);

        $rows = (new TuningFeedbackRepository($db))->forTeams([4]);

        self::assertSame([], $rows[0]->reasonCodes);
        self::assertNull($rows[0]->freeText);
    }

    public function testStringColumnsPassThrough(): void
    {
        $db = $this->mockDb();
        $db->onQuery('ibl_trade_suggestion_feedback', [self::row('', null)]);

        $rows = (new TuningFeedbackRepository($db))->forTeams([4]);

        self::assertSame(str_repeat('a', 40), $rows[0]->dealKey);
        self::assertSame(str_repeat('b', 40), $rows[0]->coreKey);
        self::assertSame('{}', $rows[0]->dealJson);
        self::assertSame('2026-11-18 10:00:00', $rows[0]->createdAt);
    }

    public function testMistypedColumnsFallBackToZeroAndEmpty(): void
    {
        $db = $this->mockDb();
        $db->onQuery('ibl_trade_suggestion_feedback', [[
            'tid' => '4',
            'partner_tid' => null,
            'deal_key' => 7,
            'core_key' => null,
            'deal_json' => null,
            'reason_codes' => null,
            'free_text' => 5,
            'created_at' => null,
        ]]);

        $rows = (new TuningFeedbackRepository($db))->forTeams([4]);

        self::assertSame(0, $rows[0]->tid);
        self::assertSame(0, $rows[0]->partnerTid);
        self::assertSame('', $rows[0]->dealKey);
        self::assertSame('', $rows[0]->coreKey);
        self::assertSame('', $rows[0]->dealJson);
        self::assertSame([], $rows[0]->reasonCodes);
        self::assertNull($rows[0]->freeText);
        self::assertSame('', $rows[0]->createdAt);
    }

    private function mockDb(): \Tests\WideUnit\Mocks\MockDatabase
    {
        $db = $this->mockDb;
        self::assertNotNull($db);

        return $db;
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(string $codes, ?string $freeText): array
    {
        return [
            'tid' => 4,
            'partner_tid' => 9,
            'deal_key' => str_repeat('a', 40),
            'core_key' => str_repeat('b', 40),
            'deal_json' => '{}',
            'reason_codes' => $codes,
            'free_text' => $freeText,
            'created_at' => '2026-11-18 10:00:00',
        ];
    }
}
