<?php

declare(strict_types=1);

namespace TradeSuggestions\Profile;

use TradeSuggestions\Projection\RatingSet;

/**
 * One team's profile at one horizon offset (F6): a minutes-weighted mean per
 * dimension and the team's league rank (1..28) per dimension.
 */
final readonly class TeamProfile
{
    public const int SURPLUS_MAX_RANK = 9;
    public const int DEFICIT_MIN_RANK = 20;

    /**
     * @param array<string, float> $values dimension => minutes-weighted mean
     * @param array<string, int> $ranks dimension (plus offense_mean and defense_mean) => rank
     */
    public function __construct(
        public int $tid,
        public int $offset,
        public array $values,
        public array $ranks,
    ) {
    }

    /**
     * Rating dimensions average every rostered player. Stat and rate
     * dimensions skip players with no production sample. A dimension whose
     * total weight is 0 is 0.0.
     *
     * @param array<int, RatingSet> $ratingsByPid projected ratings at the lineup's offset
     * @param array<int, array<string, float>> $per36ByPid projected per-36 lines at the lineup's offset
     * @return array<string, float>
     */
    public static function valuesFor(SeasonLineup $lineup, array $ratingsByPid, array $per36ByPid): array
    {
        $values = [];
        foreach (ProfileDimension::KEYS as $key) {
            $isStat = in_array($key, ProfileDimension::STATS, true);
            $isRate = in_array($key, ProfileDimension::RATES, true);
            $sum = 0.0;
            $weight = 0.0;
            foreach ($lineup->players as $pid => $player) {
                $w = $lineup->minutesWeight[$pid] ?? 0.0;
                if ($isStat) {
                    $line = $per36ByPid[$pid] ?? [];
                    if ($line === []) {
                        continue;
                    }
                    $value = $line[$key] ?? 0.0;
                } elseif ($isRate) {
                    $production = $player->production;
                    if ($production === null) {
                        continue;
                    }
                    $value = match ($key) {
                        'fg_pct' => self::ratio($production->fgm, $production->fga),
                        'tp_pct' => self::ratio($production->tgm, $production->tga),
                        'ft_pct' => self::ratio($production->ftm, $production->fta),
                        default => self::ratio($production->tga, $production->fga),
                    };
                } else {
                    $value = ($ratingsByPid[$pid] ?? $player->ratings)->get($key);
                }
                $sum += $w * $value;
                $weight += $w;
            }
            $values[$key] = $weight > 0.0 ? $sum / $weight : 0.0;
        }

        return $values;
    }

    public function isSurplus(string $key): bool
    {
        return $this->rankOf($key) <= self::SURPLUS_MAX_RANK;
    }

    public function isDeficit(string $key): bool
    {
        return $this->rankOf($key) >= self::DEFICIT_MIN_RANK;
    }

    /**
     * Offense rank minus defense rank. Positive means the team leans defensive.
     */
    public function balance(): int
    {
        return $this->rankOf(ProfileDimension::OFFENSE_MEAN) - $this->rankOf(ProfileDimension::DEFENSE_MEAN);
    }

    private function rankOf(string $key): int
    {
        return $this->ranks[$key] ?? throw new \InvalidArgumentException("No rank for dimension {$key}");
    }

    private static function ratio(int $numerator, int $denominator): float
    {
        return $denominator === 0 ? 0.0 : $numerator / $denominator;
    }
}
