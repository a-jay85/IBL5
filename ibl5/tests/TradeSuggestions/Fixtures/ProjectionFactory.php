<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Fixtures;

use TradeSuggestions\Projection\Rating;
use TradeSuggestions\Projection\RatingSet;

/**
 * Builders for the v3 projection and profile tests.
 */
final class ProjectionFactory
{
    /**
     * Every `Rating::ALL` key at `$base`, then the overrides applied.
     *
     * @param array<string, float|int> $overrides
     */
    public static function ratingSet(array $overrides = [], float $base = 50.0): RatingSet
    {
        $row = [];
        foreach (Rating::ALL as $key) {
            $row[$key] = $base;
        }
        foreach ($overrides as $key => $value) {
            $row[$key] = (float) $value;
        }

        return RatingSet::fromRow($row);
    }
}
