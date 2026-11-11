<?php

declare(strict_types=1);

namespace TradeSuggestions\Profile;

/**
 * The value of moving a team's rank on one dimension. A place gained near
 * the bottom is worth more than one gained near the top.
 */
final class RankUtility
{
    public const float WEIGHT_TOP = 0.3;
    public const float WEIGHT_MIDDLE = 0.6;
    public const float WEIGHT_BOTTOM = 1.0;
    public const int TOP_MAX_RANK = 9;
    public const int MIDDLE_MAX_RANK = 19;
    public const int MAX_RANK = 28;

    /**
     * The worth of leaving `$fromPlace` for the next better place.
     */
    public static function marginal(int $fromPlace): float
    {
        self::assertRank($fromPlace);
        if ($fromPlace <= self::TOP_MAX_RANK) {
            return self::WEIGHT_TOP;
        }
        if ($fromPlace <= self::MIDDLE_MAX_RANK) {
            return self::WEIGHT_MIDDLE;
        }

        return self::WEIGHT_BOTTOM;
    }

    /**
     * Signed sum of `marginal()` over each place crossed. Moving 22 to 18
     * sums marginal(22..19). A worse move is the negative of its mirror.
     */
    public static function gain(int $before, int $after): float
    {
        self::assertRank($before);
        self::assertRank($after);
        if ($before === $after) {
            return 0.0;
        }
        if ($after > $before) {
            return -self::gain($after, $before);
        }

        $sum = 0.0;
        for ($place = $before; $place > $after; $place--) {
            $sum += self::marginal($place);
        }

        return $sum;
    }

    private static function assertRank(int $rank): void
    {
        if ($rank < 1 || $rank > self::MAX_RANK) {
            throw new \InvalidArgumentException("Rank {$rank} is outside 1.." . self::MAX_RANK);
        }
    }
}
