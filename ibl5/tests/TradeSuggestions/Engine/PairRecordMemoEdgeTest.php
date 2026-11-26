<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Engine\PairRecordMemo;
use TradeSuggestions\Projection\RatingSet;

/**
 * Key-packing and pid-range edges of `PairRecordMemo`. A "strong" set beats the
 * zero set on `td` and `dd` (both in every slot's relevance list), so strong vs
 * zero is +2. Zero vs a set with only `td` at 90 is -1.
 */
final class PairRecordMemoEdgeTest extends TestCase
{
    /** The CI seed's synthetic pids sit far above the key's 20-bit fields. */
    private const int SEED_PID = 200000030;

    public function testPidAboveTwentyBitsOnTheSecondSideIsAccepted(): void
    {
        self::assertSame(2, (new PairRecordMemo())->diff(0, 'PG', 1, self::strong(), self::SEED_PID, self::zero()));
    }

    public function testPidAboveTwentyBitsOnTheFirstSideIsAccepted(): void
    {
        self::assertSame(-2, (new PairRecordMemo())->diff(0, 'PG', self::SEED_PID, self::zero(), 1, self::strong()));
    }

    public function testPidsAboveTwentyBitsShareOneEntryWhenSwapped(): void
    {
        $memo = new PairRecordMemo();

        self::assertSame(2, $memo->diff(0, 'PG', self::SEED_PID, self::strong(), self::SEED_PID + 1, self::zero()));
        self::assertSame(-2, $memo->diff(0, 'PG', self::SEED_PID + 1, self::zero(), self::SEED_PID, self::strong()));
        self::assertSame(1, $memo->size());
    }

    public function testHighPidsThatShareLowBitsKeepSeparateEntries(): void
    {
        $memo = new PairRecordMemo();

        self::assertSame(2, $memo->diff(0, 'PG', 1, self::strong(), 2, self::zero()));
        self::assertSame(-1, $memo->diff(0, 'PG', 1 + (1 << 20), self::zero(), 2 + (1 << 20), self::tdOnly()));
        self::assertSame(2, $memo->size());
    }

    public function testSlotAndPidBitsDoNotCollideAcrossSlots(): void
    {
        $memo = new PairRecordMemo();

        self::assertSame(2, $memo->diff(0, 'SG', 1, self::strong(), 600000, self::zero()));
        self::assertSame(-1, $memo->diff(0, 'PG', 524289, self::zero(), 600000, self::tdOnly()));
        self::assertSame(2, $memo->size());
    }

    public function testLowPidBitsDoNotCollideWithHighPidBits(): void
    {
        $memo = new PairRecordMemo();

        self::assertSame(2, $memo->diff(0, 'PG', 3, self::strong(), 5, self::zero()));
        self::assertSame(-1, $memo->diff(0, 'PG', 2, self::zero(), 524293, self::tdOnly()));
        self::assertSame(2, $memo->size());
    }

    public function testOffsetsTwoToTheTwentyApartKeepSeparateEntries(): void
    {
        $memo = new PairRecordMemo();

        self::assertSame(2, $memo->diff(0, 'PG', 1, self::strong(), 2, self::zero()));
        self::assertSame(-1, $memo->diff(1048576, 'PG', 1, self::zero(), 2, self::tdOnly()));
        self::assertSame(2, $memo->size());
    }

    public function testLargeOffsetAndNextSlotDoNotCollideWithOffsetZero(): void
    {
        $memo = new PairRecordMemo();

        self::assertSame(2, $memo->diff(0, 'PG', 1, self::strong(), 2, self::zero()));
        self::assertSame(-1, $memo->diff(1864135, 'SG', 1, self::zero(), 2, self::tdOnly()));
        self::assertSame(2, $memo->size());
    }

    public function testSecondLargeOffsetAndNextSlotDoNotCollideWithOffsetZero(): void
    {
        $memo = new PairRecordMemo();

        self::assertSame(2, $memo->diff(0, 'PG', 1, self::strong(), 2, self::zero()));
        self::assertSame(-1, $memo->diff(2396745, 'SG', 1, self::zero(), 2, self::tdOnly()));
        self::assertSame(2, $memo->size());
    }

    private static function zero(): RatingSet
    {
        return RatingSet::fromRow([]);
    }

    private static function strong(): RatingSet
    {
        return RatingSet::fromRow(['td' => 90, 'dd' => 90]);
    }

    private static function tdOnly(): RatingSet
    {
        return RatingSet::fromRow(['td' => 90]);
    }
}
