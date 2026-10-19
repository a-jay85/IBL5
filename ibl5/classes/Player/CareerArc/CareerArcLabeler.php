<?php

declare(strict_types=1);

namespace Player\CareerArc;

/**
 * Deterministic, rule-based career labels. No DB access, no randomness.
 */
final class CareerArcLabeler
{
    public const JOURNEYMAN_MIN_TEAMS = 4;
    public const ONE_TEAM_MIN_SEASONS = 5;
    public const LATE_BLOOMER_MIN_PPG_SEASONS = 4;
    public const LATE_BLOOMER_MIN_PEAK_AGE = 29;

    /**
     * @param array<int, ArcSeason> $seasonsByYear
     * @return list<string>
     */
    public function labelsFor(array $seasonsByYear, ?int $bestPpgYear): array
    {
        $labels = [];

        $teams = [];
        foreach ($seasonsByYear as $season) {
            if ($season->teamid !== 0) {
                $teams[$season->teamid] = true;
            }
        }
        $teamCount = count($teams);

        if ($teamCount >= self::JOURNEYMAN_MIN_TEAMS) {
            $labels[] = 'Journeyman (' . $teamCount . ' teams)';
        }

        $seasonCount = count($seasonsByYear);
        if ($seasonCount >= self::ONE_TEAM_MIN_SEASONS && $teamCount === 1) {
            $labels[] = 'One-team career (' . $seasonCount . ' seasons)';
        }

        $ppgSeasonCount = count(array_filter(
            $seasonsByYear,
            static fn (ArcSeason $season): bool => $season->ppg !== null,
        ));
        if (
            $ppgSeasonCount >= self::LATE_BLOOMER_MIN_PPG_SEASONS
            && $bestPpgYear !== null
            && isset($seasonsByYear[$bestPpgYear])
            && $seasonsByYear[$bestPpgYear]->age >= self::LATE_BLOOMER_MIN_PEAK_AGE
        ) {
            $labels[] = 'Late bloomer';
        }

        return $labels;
    }
}
