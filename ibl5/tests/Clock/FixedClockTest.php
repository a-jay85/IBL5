<?php

declare(strict_types=1);

namespace Tests\Clock;

use PHPUnit\Framework\TestCase;

final class FixedClockTest extends TestCase
{
    public function testMicrotimeDefaultsToNowAsFloat(): void
    {
        $clock = new FixedClock(1791549296);

        self::assertSame(1791549296.0, $clock->microtime());
    }

    public function testMicrotimeReturnsExplicitValue(): void
    {
        $clock = new FixedClock(1791549296, 1791549296.25);

        self::assertSame(1791549296.25, $clock->microtime());
        self::assertSame(1791549296, $clock->now());
    }

    public function testDefaultMicrotimeFollowsAdvance(): void
    {
        $clock = new FixedClock(1791549296);
        $clock->advance(5);

        self::assertSame(1791549301.0, $clock->microtime());
    }
}
