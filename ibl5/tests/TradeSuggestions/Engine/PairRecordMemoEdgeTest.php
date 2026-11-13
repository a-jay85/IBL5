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
    private const int PID_LIMIT = 1048576;

    public function testPidAtTheLimitOnTheSecondSideThrows(): void
    {
        $this->expectException(\OverflowException::class);

        (new PairRecordMemo())->diff(0, 'PG', 1, self::zero(), self::PID_LIMIT, self::zero());
    }

    public function testPidAtTheLimitOnTheFirstSideThrows(): void
    {
        $this->expectException(\OverflowException::class);

        (new PairRecordMemo())->diff(0, 'PG', self::PID_LIMIT, self::zero(), 1, self::zero());
    }

    public function testPidJustBelowTheLimitIsAccepted(): void
    {
        $memo = new PairRecordMemo();

        self::assertSame(2, $memo->diff(0, 'PG', 1, self::strong(), self::PID_LIMIT - 1, self::zero()));
        self::assertSame(-2, $memo->diff(0, 'PG', self::PID_LIMIT - 1, self::zero(), 1, self::strong()));
        self::assertSame(1, $memo->size());
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
