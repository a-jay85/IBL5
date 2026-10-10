<?php

declare(strict_types=1);

namespace Tests\Clock;

use Clock\SystemClock;
use PHPUnit\Framework\TestCase;

class SystemClockTest extends TestCase
{
    private SystemClock $clock;

    protected function setUp(): void
    {
        $this->clock = new SystemClock();
    }

    public function testNowReturnsAnInteger(): void
    {
        self::assertIsInt($this->clock->now());
    }

    public function testNowApproximatesCurrentTime(): void
    {
        $before = time();
        $result = $this->clock->now();
        $after = time();

        self::assertGreaterThanOrEqual($before, $result);
        self::assertLessThanOrEqual($after, $result);
    }

    public function testMicrotimeApproximatesCurrentTime(): void
    {
        $before = microtime(true);
        $result = $this->clock->microtime();
        $after = microtime(true);

        self::assertGreaterThanOrEqual($before, $result);
        self::assertLessThanOrEqual($after, $result);
    }
}
