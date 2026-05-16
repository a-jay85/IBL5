<?php

declare(strict_types=1);

namespace LastSimRecap\Dto;

final class RecapInjury
{
    public function __construct(
        public readonly int $pid,
        public readonly string $name,
        public readonly string $pos,
        public readonly string $description,
        public readonly int $gamesMissed,
        public readonly int $daysRemaining,
        public readonly bool $isNew,
    ) {}
}
