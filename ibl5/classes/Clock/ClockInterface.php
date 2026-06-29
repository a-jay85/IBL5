<?php

declare(strict_types=1);

namespace Clock;

interface ClockInterface
{
    public function now(): int;
}
