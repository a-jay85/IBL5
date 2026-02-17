<?php

declare(strict_types=1);

namespace StrengthOfSchedule;

/**
 * StrengthOfScheduleCalculator - Pure calculation class for SOS metrics
 *
 * Computes opponent win percentage averages and assigns difficulty tiers
 * based on power rankings. No database access — all data passed in.
 */
class StrengthOfScheduleCalculator
{
    /**
     * Power ranking tier cutoffs (fixed thresholds based on 0-100 ranking scale)
     */
    private const TIER_ELITE_MIN = 70.0;
    private const TIER_STRONG_MIN = 55.0;
    private const TIER_AVERAGE_MIN = 45.0;
    private const TIER_WEAK_MIN = 30.0;

    /**
     * Calculate average opponent win percentage from a list of games
     *
     * @param list<array{Visitor: int, Home: int}> $games Games played or remaining
     * @param int $teamId The team to calculate SOS for
     * @param array<int, float> $teamWinPcts Map of team ID → win percentage (0.0-1.0)
     * @return float Average opponent win percentage (0.0-1.0)
     */
    public static function calculateAverageOpponentWinPct(array $games, int $teamId, array $teamWinPcts): float
    {
        if ($games === []) {
            return 0.0;
        }

        $totalOpponentWinPct = 0.0;
        $gameCount = count($games);

        foreach ($games as $game) {
            $opponentId = ($game['Visitor'] === $teamId) ? $game['Home'] : $game['Visitor'];
            $totalOpponentWinPct += $teamWinPcts[$opponentId] ?? 0.0;
        }

        return round($totalOpponentWinPct / $gameCount, 3);
    }

    /**
     * Assign a difficulty tier based on power ranking score
     *
     * Tiers (using ibl_power.ranking on 0-100 scale):
     * - Elite (>= 70): Top-tier opponent
     * - Strong (55-69): Above-average opponent
     * - Average (45-54): Mid-tier opponent
     * - Weak (30-44): Below-average opponent
     * - Bottom (< 30): Easiest opponent
     *
     * @param float $powerRanking Power ranking score (0.0-100.0)
     * @return string Tier name: 'elite'|'strong'|'average'|'weak'|'bottom'
     */
    public static function assignTier(float $powerRanking): string
    {
        if ($powerRanking >= self::TIER_ELITE_MIN) {
            return 'elite';
        }

        if ($powerRanking >= self::TIER_STRONG_MIN) {
            return 'strong';
        }

        if ($powerRanking >= self::TIER_AVERAGE_MIN) {
            return 'average';
        }

        if ($powerRanking >= self::TIER_WEAK_MIN) {
            return 'weak';
        }

        return 'bottom';
    }

    /**
     * Rank teams by SOS (1 = hardest schedule)
     *
     * @param array<int, float> $sosValues Map of team ID → SOS value
     * @return array<int, int> Map of team ID → rank (1 = hardest)
     */
    public static function rankTeams(array $sosValues): array
    {
        // Sort descending by SOS value (higher opponent win% = harder schedule)
        arsort($sosValues);

        /** @var array<int, int> $ranks */
        $ranks = [];
        $rank = 1;

        foreach ($sosValues as $teamId => $sos) {
            $ranks[$teamId] = $rank;
            $rank++;
        }

        return $ranks;
    }
}
