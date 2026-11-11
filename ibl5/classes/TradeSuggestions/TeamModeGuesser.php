<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Pure win-now/rebuild guess. No I/O.
 *
 * score = 0.70 * record + 0.15 * rotation age + 0.15 * contract horizon, each
 * term scaled to -1..1. score >= 0 is Win now.
 *
 * - record: this season's win% blended with last season's, which counts as
 *   PRIOR_GAMES games, so a 3-1 start does not outweigh an 82-game season.
 * - age: older rotations lean Win now.
 * - contract horizon: the share of rotation depth-chart minutes held by
 *   players signed beyond this season. A locked core leans Win now.
 *
 * The rotation is the top ROTATION_SIZE players by depth-chart minutes.
 */
final class TeamModeGuesser
{
    public const int ROTATION_SIZE = 8;
    public const int PRIOR_GAMES = 20;
    public const float RECORD_SPAN = 0.2;
    public const float AGE_PIVOT = 26.5;
    public const float AGE_SPAN = 3.5;
    public const float W_RECORD = 0.70;
    public const float W_AGE = 0.15;
    public const float W_CONTRACTS = 0.15;

    /**
     * This season's win% blended with last season's (PRIOR_GAMES games of it).
     */
    public static function recordPct(ModeInputs $in): float
    {
        $priorGames = $in->priorWins + $in->priorLosses;
        $priorPct = $priorGames > 0 ? $in->priorWins / $priorGames : 0.5;

        return ($in->wins + self::PRIOR_GAMES * $priorPct) / ($in->wins + $in->losses + self::PRIOR_GAMES);
    }

    public function guess(ModeInputs $in): ModeGuess
    {
        $recordPct = self::recordPct($in);

        $score = self::W_RECORD * self::clamp(($recordPct - 0.5) / self::RECORD_SPAN)
            + self::W_AGE * self::clamp(($in->rotationAge - self::AGE_PIVOT) / self::AGE_SPAN)
            + self::W_CONTRACTS * ($in->lockedShare - 0.5) * 2;

        return new ModeGuess($score >= 0 ? TeamMode::WinNow : TeamMode::Rebuild, $score, $recordPct, $in);
    }

    /**
     * Inputs for every team in $assets.
     *
     * @param array<int, TeamAssets> $assets Keyed by tid
     * @param array<int, array{wins: int, losses: int}> $current This season, keyed by tid
     * @param array<int, array{wins: int, losses: int}> $prior Last season, keyed by tid
     * @return array<int, ModeInputs> Keyed by tid
     */
    public static function inputsForLeague(array $assets, array $current, array $prior): array
    {
        $inputs = [];
        foreach ($assets as $tid => $team) {
            $rotation = self::rotation($team->players);
            $minutesTotal = 0;
            $lockedMinutes = 0;
            foreach ($rotation as $player) {
                $minutesTotal += $player->dcMinutes;
                if ($player->yearsRemaining >= 2) {
                    $lockedMinutes += $player->dcMinutes;
                }
            }

            $inputs[$tid] = new ModeInputs(
                wins: $current[$tid]['wins'] ?? 0,
                losses: $current[$tid]['losses'] ?? 0,
                priorWins: $prior[$tid]['wins'] ?? 0,
                priorLosses: $prior[$tid]['losses'] ?? 0,
                rotationAge: self::mean(array_map(static fn (SuggestionPlayer $p): float => $p->age, $rotation)),
                lockedShare: $minutesTotal > 0 ? $lockedMinutes / $minutesTotal : 0.0,
            );
        }

        return $inputs;
    }

    /**
     * Top ROTATION_SIZE players by dcMinutes, then salary, then lower pid.
     *
     * @param list<SuggestionPlayer> $players
     * @return list<SuggestionPlayer>
     */
    public static function rotation(array $players): array
    {
        return array_slice(SlotMapper::byMinutes($players), 0, self::ROTATION_SIZE);
    }

    /**
     * @param list<float> $values
     */
    private static function mean(array $values): float
    {
        return $values === [] ? 0.0 : array_sum($values) / count($values);
    }

    private static function clamp(float $value): float
    {
        return max(-1.0, min(1.0, $value));
    }
}
