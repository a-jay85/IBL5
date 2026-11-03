<?php

declare(strict_types=1);

namespace Tests\OnThisDay;

use OnThisDay\RecordGameTracker;
use PHPUnit\Framework\TestCase;

final class RecordGameTrackerTest extends TestCase
{
    public function testFirstObservationNeverRecords(): void
    {
        $tracker = new RecordGameTracker(2000);

        self::assertSame([], $tracker->observe(2001, ['points' => 70]));
    }

    public function testStrictlyGreaterValueIsRecord(): void
    {
        $tracker = new RecordGameTracker(2000);
        $tracker->observe(2001, ['points' => 40]);

        self::assertSame(['points'], $tracker->observe(2001, ['points' => 41]));
    }

    public function testTieWithPriorMaxIsNotRecord(): void
    {
        $tracker = new RecordGameTracker(2000);
        $tracker->observe(2001, ['points' => 40]);

        self::assertSame([], $tracker->observe(2002, ['points' => 40]));
        self::assertSame(['points'], $tracker->observe(2002, ['points' => 41]));
    }

    public function testFirstSeasonSetsMaxButNeverRecords(): void
    {
        $tracker = new RecordGameTracker(2000);

        self::assertSame([], $tracker->observe(2000, ['points' => 30]));
        self::assertSame([], $tracker->observe(2000, ['points' => 60]));
        self::assertSame([], $tracker->observe(2001, ['points' => 55]));
        self::assertSame(['points'], $tracker->observe(2001, ['points' => 61]));
    }

    public function testSameNightSecondLineMustBeatFirst(): void
    {
        $tracker = new RecordGameTracker(2000);
        $tracker->observe(2001, ['rebounds' => 20]);

        self::assertSame(['rebounds'], $tracker->observe(2002, ['rebounds' => 25]));
        self::assertSame([], $tracker->observe(2002, ['rebounds' => 25]));
        self::assertSame([], $tracker->observe(2002, ['rebounds' => 24]));
        self::assertSame(['rebounds'], $tracker->observe(2002, ['rebounds' => 26]));
    }

    public function testEachStatTrackedIndependently(): void
    {
        $tracker = new RecordGameTracker(2000);
        $tracker->observe(2001, ['points' => 40, 'assists' => 10, 'blocks' => 5]);

        self::assertSame(
            ['assists', 'blocks'],
            $tracker->observe(2002, ['points' => 39, 'assists' => 11, 'blocks' => 6]),
        );
        self::assertSame(['points'], $tracker->observe(2002, ['points' => 41, 'assists' => 11, 'blocks' => 6]));
    }
}
