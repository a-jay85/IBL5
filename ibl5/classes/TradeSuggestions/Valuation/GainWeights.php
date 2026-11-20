<?php

declare(strict_types=1);

namespace TradeSuggestions\Valuation;

use League\League;
use TradeSuggestions\TeamMode;

/**
 * The F8 weights. Data only: it holds no `$db`, takes no player, and never
 * returns a per-player number. Gain is measured in weighted rank places.
 *
 * `TeamMode` has no backing strings, so the table keys come from `modeKey()`.
 * `TeamMode::Middle` reads the `middle` row F8 froze alongside the other two.
 */
final class GainWeights
{
    /** @var array<string, list<float>> */
    public const array SEASON_WEIGHTS = [
        'win_now' => [0.55, 0.25, 0.12, 0.08],
        'middle' => [0.30, 0.30, 0.22, 0.18],
        'rebuild' => [0.10, 0.20, 0.30, 0.40],
    ];

    public const float STARTER_WEIGHT = 3.0;
    public const float BENCH_WEIGHT = 1.0;
    public const float RENTAL_LATE_SEASON_SHARE = 0.25;

    /**
     * The F15 `STRONG_FOR_WEAK_WITHOUT_NET_GAIN` threshold. Two bottom-tier
     * places (`RankUtility::WEIGHT_BOTTOM` 1.0 each) or about seven top-tier
     * places (0.3 each) is the smallest move a GM would call clearly better.
     * It is not fitted to any fixture.
     */
    public const float CLEAR_NET_GAIN = 2.0;

    public const float LOPSIDED_RATIO = 3.0;
    public const int EXPOSURE_K = 8;

    public static function modeKey(TeamMode $mode): string
    {
        return match ($mode) {
            TeamMode::WinNow => 'win_now',
            TeamMode::Rebuild => 'rebuild',
            TeamMode::Middle => 'middle',
        };
    }

    /**
     * @return list<float> one weight per `Horizon::OFFSETS` entry
     */
    public static function seasonWeights(TeamMode $mode): array
    {
        return self::SEASON_WEIGHTS[self::modeKey($mode)];
    }

    /**
     * Scales a WinNow side's offset-0 rental component (F8). The scale is the
     * games-left fraction of the late-season window. `gamesLeft / scheduleGames`
     * was rejected because it drops from 1.0 to 0.25 at the window's edge, and
     * one game should not quarter a rental's worth.
     */
    public static function rentalScale(int $gamesLeft, int $scheduleGames = League::REGULAR_SEASON_GAMES): float
    {
        if ($scheduleGames <= 0) {
            return 0.0;
        }

        $window = self::RENTAL_LATE_SEASON_SHARE * $scheduleGames;

        return max(0.0, min(1.0, $gamesLeft / $window));
    }

    /**
     * @return array<string, float|int|array<string, list<float>>>
     */
    public static function toArray(): array
    {
        return [
            'season_weights' => self::SEASON_WEIGHTS,
            'starter_weight' => self::STARTER_WEIGHT,
            'bench_weight' => self::BENCH_WEIGHT,
            'rental_late_season_share' => self::RENTAL_LATE_SEASON_SHARE,
            'clear_net_gain' => self::CLEAR_NET_GAIN,
            'lopsided_ratio' => self::LOPSIDED_RATIO,
            'exposure_k' => self::EXPOSURE_K,
        ];
    }
}
