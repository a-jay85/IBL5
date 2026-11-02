<?php

declare(strict_types=1);

namespace TradeSuggestions\Projection;

/**
 * The outcome of a rating-by-rating vote between two players at one slot (F3).
 */
enum Dominance: string
{
    case Better = 'better';
    case Worse = 'worse';
    case Mixed = 'mixed';

    public function mirror(): self
    {
        return match ($this) {
            self::Better => self::Worse,
            self::Worse => self::Better,
            self::Mixed => self::Mixed,
        };
    }
}
