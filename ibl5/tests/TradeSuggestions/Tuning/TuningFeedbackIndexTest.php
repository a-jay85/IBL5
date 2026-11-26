<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Tuning;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Tuning\TuningFeedback;
use TradeSuggestions\Tuning\TuningFeedbackIndex;

final class TuningFeedbackIndexTest extends TestCase
{
    public function testLookupByDealKey(): void
    {
        $row = self::fb(1, 'deal-a', 'core-a');
        $index = new TuningFeedbackIndex([$row]);

        self::assertSame($row, $index->lookup(1, 'deal-a', 'core-other'));
    }

    public function testLookupFallsBackToCoreKey(): void
    {
        $row = self::fb(1, 'deal-a', 'core-a');
        $index = new TuningFeedbackIndex([$row]);

        self::assertSame($row, $index->lookup(1, 'deal-variant', 'core-a'));
    }

    public function testLookupIsScopedToTid(): void
    {
        $index = new TuningFeedbackIndex([self::fb(2, 'deal-a', 'core-a')]);

        self::assertNull($index->lookup(1, 'deal-a', 'core-a'));
    }

    public function testHiddenKeysAreScopedToTid(): void
    {
        $index = new TuningFeedbackIndex([
            self::fb(1, 'deal-one', 'core-one'),
            self::fb(2, 'deal-two', 'core-two'),
        ]);

        self::assertSame(['deal' => ['deal-one'], 'core' => ['core-one']], $index->hiddenKeys(1));
    }

    public function testUnknownKeysGiveNull(): void
    {
        $index = new TuningFeedbackIndex([self::fb(1, 'deal-a', 'core-a')]);

        self::assertNull($index->lookup(1, 'deal-x', 'core-x'));
        self::assertSame(['deal' => [], 'core' => []], $index->hiddenKeys(3));
    }

    public function testNewestCoreMatchWins(): void
    {
        $newest = self::fb(1, 'deal-new', 'core-a', '2026-11-18 12:00:00');
        $older = self::fb(1, 'deal-old', 'core-a', '2026-11-17 12:00:00');
        $index = new TuningFeedbackIndex([$newest, $older]);

        self::assertSame($newest, $index->lookup(1, 'deal-variant', 'core-a'));
    }

    public function testReasonLabelsKeepUnknownCodeRaw(): void
    {
        $row = new TuningFeedback(1, 2, 'd', 'c', '{}', ['TOO_OLD', 'GONE'], null, '2026-11-18 00:00:00');

        self::assertSame(['Too old', 'GONE'], $row->reasonLabels());
    }

    private static function fb(int $tid, string $deal, string $core, string $createdAt = '2026-11-18 00:00:00'): TuningFeedback
    {
        return new TuningFeedback($tid, 5, $deal, $core, '{}', ['OTHER'], 'note', $createdAt);
    }
}
