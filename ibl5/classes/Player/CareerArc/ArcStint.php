<?php

declare(strict_types=1);

namespace Player\CareerArc;

/**
 * A run of consecutive season rows on one team.
 */
final readonly class ArcStint
{
    public function __construct(
        public int $teamid,
        public string $teamName,
        public int $fromYear,
        public int $toYear,
    ) {
    }
}
