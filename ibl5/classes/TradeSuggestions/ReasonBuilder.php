<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Builds the one-line "why" for one side of a deal. Pure.
 *
 * Picks the horizon with the largest weighted delta and maps it to a fixed
 * template. The only interpolated values are a position group label, a pick
 * year/round, or a salary integer; the view escapes the result.
 */
final class ReasonBuilder
{
    public function build(
        TeamAssets $before,
        TeamAssets $after,
        HorizonScores $beforeScores,
        HorizonScores $afterScores,
        TeamMode $mode,
    ): string {
        $weights = HorizonScores::weights($mode);
        $deltas = [
            'now' => $weights['now'] * ($afterScores->now - $beforeScores->now),
            'short' => $weights['short'] * ($afterScores->short - $beforeScores->short),
            'long' => $weights['long'] * ($afterScores->long - $beforeScores->long),
        ];

        $horizon = 'now';
        foreach ($deltas as $name => $delta) {
            if ($delta > $deltas[$horizon]) {
                $horizon = $name;
            }
        }

        return match ($horizon) {
            'now' => self::nowReason($beforeScores, $afterScores),
            'short' => 'Adds a rotation player signed beyond this season.',
            'long' => self::longReason($before, $after),
        };
    }

    private static function nowReason(HorizonScores $beforeScores, HorizonScores $afterScores): string
    {
        foreach (TeamNeedsScorer::GROUPS as $group) {
            $holeBefore = $beforeScores->positionHoles[$group] ?? 0;
            $holeAfter = $afterScores->positionHoles[$group] ?? 0;
            if ($holeAfter < $holeBefore) {
                return 'Fills a hole at ' . $group . '.';
            }
        }

        return 'Upgrades the rotation right away.';
    }

    private static function longReason(TeamAssets $before, TeamAssets $after): string
    {
        $heldPickIds = array_map(static fn (SuggestionPick $p): int => $p->pickId, $before->picks);
        foreach ($after->picks as $pick) {
            if (!in_array($pick->pickId, $heldPickIds, true)) {
                return 'Adds a ' . $pick->year . ' round ' . $pick->round . ' draft pick.';
            }
        }

        $cleared = self::futureSalary($before) - self::futureSalary($after);
        if ($cleared > 0) {
            return 'Clears ' . $cleared . 'K of future salary.';
        }

        return 'Adds young upside.';
    }

    private static function futureSalary(TeamAssets $assets): int
    {
        $total = 0;
        foreach ($assets->players as $player) {
            $total += $player->futureSalary;
        }

        return $total;
    }
}
