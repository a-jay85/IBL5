<?php

declare(strict_types=1);

namespace Tests\Clock;

use Clock\ClockInterface;

final class FixedClock implements ClockInterface
{
    /**
     * @param ?float $micro Explicit sub-second time. When null, microtime() returns (float) now(),
     *                      so it follows setNow() and advance().
     */
    public function __construct(private int $now, private ?float $micro = null) {}

    public function now(): int
    {
        return $this->now;
    }

    public function setNow(int $now): void
    {
        $this->now = $now;
    }

    public function advance(int $seconds): void
    {
        $this->now += $seconds;
    }

    public function microtime(): float
    {
        return $this->micro ?? (float) $this->now;
    }
}
