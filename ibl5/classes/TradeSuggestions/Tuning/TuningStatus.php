<?php

declare(strict_types=1);

namespace TradeSuggestions\Tuning;

/**
 * Which deals the tuning page lists. The values are the lowercase `status`
 * GET words; matching is case-sensitive, so `HIDDEN` is unknown.
 */
enum TuningStatus: string
{
    case All = 'all';
    case Shown = 'shown';
    case Vetoed = 'vetoed';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All',
            // A GM sees the ranker's top 3 only before the first refresh.
            self::Shown => 'Top 3 (first window)',
            self::Vetoed => 'Vetoed',
            self::Hidden => 'Hidden by the GM',
        };
    }
}
