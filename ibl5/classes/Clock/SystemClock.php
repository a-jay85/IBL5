<?php

declare(strict_types=1);

namespace Clock;

class SystemClock implements ClockInterface
{
    public function now(): int
    {
        return time();
    }
}
