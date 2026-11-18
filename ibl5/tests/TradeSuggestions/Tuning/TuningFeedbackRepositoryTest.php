<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Tuning;

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
     * Security test: every tid is a bound placeholder, never SQL text.
     */
    public function testPlaceholderCountMatchesTeamCount(): void
    {
        $db = $this->mockDb();
        $db->onQuery('ibl_trade_suggestion_feedback', []);

        (new TuningFeedbackRepository($db))->forTeams([1, 2, 8]);

        $prepared = implode("\n", $db->getPreparedQueries());
        self::assertStringContainsString('IN (?, ?, ?)', $prepared);
        self::assertStringNotContainsString('8', $prepared);
        self::assertSame('iii', $db->getLastBoundTypes());
        self::assertSame([1, 2, 8], $db->getLastBoundParams());
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
