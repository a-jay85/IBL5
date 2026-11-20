<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

/**
 * What happened to one scored deal in `DealRanker::select`, for the tuning
 * page. Each deal gets exactly one outcome: the first gate it failed, or its
 * place among the passing deals.
 */
enum RankOutcome: string
{
    /** Passed every gate and is among the ranker's limit. */
    case Selected = 'selected';
    /** Passed every gate and is kept in the top 12, past the deals shown first. */
    case Eligible = 'eligible';
    case NotBothPositive = 'not_both_positive';
    case Lopsided = 'lopsided';
    case Exposure = 'exposure';
    case Vetoed = 'vetoed';
    case Validator = 'validator';
    case NoMotive = 'no_motive';
    /** A sorted survivor the walk never checked, because a cap was hit first. */
    case NotReached = 'not_reached';
    /** Dropped before the ranker because the GM hid its deal or core key. The ranker never records it. */
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Selected => 'Shown (top 3)',
            self::Eligible => 'Eligible (later refresh)',
            self::NotBothPositive => 'One side does not gain',
            self::Lopsided => 'Lopsided',
            self::Exposure => 'Partner not open to this team',
            self::Vetoed => 'Vetoed',
            self::Validator => 'Refused by trade rules',
            self::NoMotive => 'No reason for one side',
            self::NotReached => 'Not reached (cap hit)',
            self::Hidden => 'Hidden by the GM',
        };
    }
}
