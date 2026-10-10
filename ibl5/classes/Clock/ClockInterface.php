<?php

declare(strict_types=1);

namespace Clock;

interface ClockInterface
{
    public function now(): int;

    /**
     * Current Unix time in seconds with sub-second precision.
     * Use for durations (page-render timing); use now() for wall-clock values.
     */
    public function microtime(): float;
}
