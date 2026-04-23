<?php

declare(strict_types=1);

namespace TrainingCampRatingsDiff;

/**
 * Value object representing a single rating field's before/after/delta.
 *
 * When the player has no snapshot (new player), `before` and `delta` are null.
 */
final class RatingDelta
{
    public function __construct(
        public readonly string $field,
        public readonly ?int $before,
        public readonly int $after,
        public readonly ?int $delta,
    ) {
    }
}
