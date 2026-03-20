<?php

declare(strict_types=1);

namespace Api\Middleware\Contracts;

interface ClockInterface
{
    public function now(): int;
}
