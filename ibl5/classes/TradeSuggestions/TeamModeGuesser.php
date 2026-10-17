<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Pure win-now/rebuild guess from a team's record and roster age. No I/O.
 */
final class TeamModeGuesser
{
    public const MIN_GAMES = 10;
    public const WIN_NOW_PCT = 0.550;
    public const REBUILD_PCT = 0.400;
    public const VETERAN_AGE = 28.0;

    public function guess(int $wins, int $losses, float $avgAge): TeamMode
    {
        $games = $wins + $losses;

        if ($games < self::MIN_GAMES) {
            return $this->guessByAge($avgAge);
        }

        $pct = $wins / $games;

        if ($pct >= self::WIN_NOW_PCT) {
            return TeamMode::WinNow;
        }

        if ($pct < self::REBUILD_PCT) {
            return TeamMode::Rebuild;
        }

        return $this->guessByAge($avgAge);
    }

    private function guessByAge(float $avgAge): TeamMode
    {
        return $avgAge >= self::VETERAN_AGE ? TeamMode::WinNow : TeamMode::Rebuild;
    }
}
