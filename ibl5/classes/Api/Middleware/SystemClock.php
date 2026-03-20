<?php

declare(strict_types=1);

namespace Api\Middleware;

use Api\Middleware\Contracts\ClockInterface;

class SystemClock implements ClockInterface
{
    public function now(): int
    {
        return time();
    }
}
