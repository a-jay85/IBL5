<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

/**
 * Generation budget (F14). The generator draws at most six players per side
 * from each partner pair, puts at most two assets on a side, counts at most
 * 4,000 raw candidates before the legality pre-filter, and offers only round
 * 1 and round 2 picks.
 */
final class CandidateBudget
{
    public const int PLAYERS_PER_PARTNER = 6;
    public const int MAX_ASSETS_PER_SIDE = 2;
    public const int MAX_RAW_CANDIDATES = 4000;
    public const int PICK_ROUNDS_MAX = 2;

    /**
     * @return array{players_per_partner: int, max_assets_per_side: int, max_raw_candidates: int, pick_rounds_max: int}
     */
    public static function toArray(): array
    {
        return [
            'players_per_partner' => self::PLAYERS_PER_PARTNER,
            'max_assets_per_side' => self::MAX_ASSETS_PER_SIDE,
            'max_raw_candidates' => self::MAX_RAW_CANDIDATES,
            'pick_rounds_max' => self::PICK_ROUNDS_MAX,
        ];
    }
}
