<?php

declare(strict_types=1);

namespace Player\CareerArc;

/**
 * One season point on the career arc (one ibl_hist row).
 */
final readonly class ArcSeason
{
    public function __construct(
        public int $year,
        public int $teamid,
        public string $teamName,
        public ?float $ppg,
        public ?int $rating,
        public int $age,
    ) {
    }
}
