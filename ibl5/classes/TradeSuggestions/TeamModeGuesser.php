<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Pure win-now/rebuild guess. No I/O.
 *
 * score = 0.45 * record + 0.35 * roster strength + 0.10 * rotation age
 *       + 0.10 * contract horizon, each term scaled to -1..1. score >= 0 is Win now.
 *
 * - record: this season's win% blended with last season's, which counts as
 *   PRIOR_GAMES games, so a 3-1 start does not outweigh an 82-game season.
 * - strength: league rank of the top-8 average ovr.
 * - age: older rotations lean Win now.
 * - contract horizon: a core signed beyond this season leans Win now.
 */
final class TeamModeGuesser
{
    public const int ROTATION_SIZE = 8;
    public const int PRIOR_GAMES = 20;
    public const float RECORD_SPAN = 0.2;
    public const float AGE_PIVOT = 26.5;
    public const float AGE_SPAN = 3.5;
    public const float W_RECORD = 0.45;
    public const float W_STRENGTH = 0.35;
    public const float W_AGE = 0.10;
    public const float W_CONTRACTS = 0.10;

    public function guess(ModeInputs $in): ModeGuess
    {
        $priorGames = $in->priorWins + $in->priorLosses;
        $priorPct = $priorGames > 0 ? $in->priorWins / $priorGames : 0.5;
        $recordPct = ($in->wins + self::PRIOR_GAMES * $priorPct) / ($in->wins + $in->losses + self::PRIOR_GAMES);

        $strength = $in->teamCount > 1
            ? ($in->teamCount - $in->strengthRank) / ($in->teamCount - 1)
            : 0.5;

        $score = self::W_RECORD * self::clamp(($recordPct - 0.5) / self::RECORD_SPAN)
            + self::W_STRENGTH * ($strength - 0.5) * 2
            + self::W_AGE * self::clamp(($in->rotationAge - self::AGE_PIVOT) / self::AGE_SPAN)
            + self::W_CONTRACTS * ($in->lockedShare - 0.5) * 2;

        return new ModeGuess($score >= 0 ? TeamMode::WinNow : TeamMode::Rebuild, $score, $recordPct, $in);
    }

    /**
     * Inputs for every team in $assets. Strength ranks are league-relative, so
     * this always runs over the whole league at once.
     *
     * @param array<int, TeamAssets> $assets Keyed by tid
     * @param array<int, array{wins: int, losses: int}> $current This season, keyed by tid
     * @param array<int, array{wins: int, losses: int}> $prior Last season, keyed by tid
     * @return array<int, ModeInputs> Keyed by tid
     */
    public static function inputsForLeague(array $assets, array $current, array $prior): array
    {
        $rotations = [];
        $strength = [];
        foreach ($assets as $tid => $team) {
            $rotations[$tid] = self::rotation($team->players);
            $strength[$tid] = self::mean(array_map(static fn (SuggestionPlayer $p): float => $p->ovr, $rotations[$tid]));
        }

        $order = array_keys($strength);
        usort($order, static fn (int $a, int $b): int => [$strength[$b], $a] <=> [$strength[$a], $b]);
        $ranks = array_flip($order);

        $inputs = [];
        foreach ($assets as $tid => $team) {
            $rotation = $rotations[$tid];
            $ovrTotal = 0;
            $lockedOvr = 0;
            foreach ($rotation as $player) {
                $ovrTotal += $player->ovr;
                if ($player->yearsRemaining >= 2) {
                    $lockedOvr += $player->ovr;
                }
            }

            $inputs[$tid] = new ModeInputs(
                wins: $current[$tid]['wins'] ?? 0,
                losses: $current[$tid]['losses'] ?? 0,
                priorWins: $prior[$tid]['wins'] ?? 0,
                priorLosses: $prior[$tid]['losses'] ?? 0,
                rotationOvr: $strength[$tid],
                strengthRank: $ranks[$tid] + 1,
                teamCount: count($assets),
                rotationAge: self::mean(array_map(static fn (SuggestionPlayer $p): float => $p->age, $rotation)),
                lockedShare: $ovrTotal > 0 ? $lockedOvr / $ovrTotal : 0.0,
            );
        }

        return $inputs;
    }

    /**
     * Top ROTATION_SIZE players by ovr, ties by lower pid.
     *
     * @param list<SuggestionPlayer> $players
     * @return list<SuggestionPlayer>
     */
    public static function rotation(array $players): array
    {
        usort($players, static fn (SuggestionPlayer $a, SuggestionPlayer $b): int => [$b->ovr, $a->pid] <=> [$a->ovr, $b->pid]);

        return array_slice($players, 0, self::ROTATION_SIZE);
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
