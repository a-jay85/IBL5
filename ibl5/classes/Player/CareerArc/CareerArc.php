<?php

declare(strict_types=1);

namespace Player\CareerArc;

/**
 * Immutable career arc: the season axis, series points, team stints, event markers and labels.
 */
final readonly class CareerArc
{
    /**
     * @param array<int, ArcSeason> $seasonsByYear only years with an ibl_hist row
     * @param list<ArcStint> $stints
     * @param list<ArcEvent> $events
     * @param list<string> $labels
     */
    public function __construct(
        public int $axisStart,
        public int $axisEnd,
        public array $seasonsByYear,
        public array $stints,
        public array $events,
        public ?int $bestPpgYear,
        public ?int $bestRatingYear,
        public bool $awardsHidden,
        public array $labels,
    ) {
    }
}
