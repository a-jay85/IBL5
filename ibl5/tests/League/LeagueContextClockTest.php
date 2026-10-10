<?php

declare(strict_types=1);

namespace Tests\League;

use League\LeagueContext;
use PHPUnit\Framework\TestCase;
use Tests\Clock\FixedClock;

/**
 * Pins the injected clock for the 30-day league cookie expiry.
 */
class LeagueContextClockTest extends TestCase
{
    public function testCookieExpiryIsThirtyDaysFromInjectedClock(): void
    {
        $clock = new FixedClock(1_700_000_000);
        $context = new LeagueContext($clock);

        self::assertSame(1_700_000_000 + 2_592_000, $context->getCookieExpiry());

        // One second before the previous expiry: the cookie window slides with the clock.
        $clock->advance(2_592_000 - 1);
        self::assertSame(1_700_000_000 + 2_592_000 - 1 + 2_592_000, $context->getCookieExpiry());
    }
}
