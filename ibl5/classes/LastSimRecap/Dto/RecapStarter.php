<?php

declare(strict_types=1);

namespace LastSimRecap\Dto;

final class RecapStarter
{
    public function __construct(
        public readonly string $pos,
        public readonly int $youPid,
        public readonly string $youName,
        public readonly int $youPts,
        public readonly bool $youHurt,
        public readonly int $oppPid,
        public readonly string $oppName,
        public readonly int $oppPts,
    ) {}
}
