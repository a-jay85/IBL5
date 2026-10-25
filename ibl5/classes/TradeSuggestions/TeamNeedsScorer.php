<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Scores a whole roster for one team in its mode. Pure.
 *
 * Players are sorted by their value to the team; the best five count in full,
 * the next three at 60%, then less down the bench, so a new player helps only
 * as much as the slot he would take. Picks add their value. Each missing player
 * below MIN_PER_GROUP in a position group of the top eight costs a penalty,
 * larger for win-now teams.
 */
final class TeamNeedsScorer
{
    /** @var list<float> */
    public const array SLOT_WEIGHTS = [1.0, 1.0, 1.0, 1.0, 1.0, 0.6, 0.6, 0.6, 0.25, 0.25];
    public const float DEEP_BENCH_WEIGHT = 0.1;
    public const int MIN_PER_GROUP = 2;
    public const float WIN_NOW_HOLE_PENALTY = 8.0;
    public const float REBUILD_HOLE_PENALTY = 3.0;

    public const string GROUP_GUARD = 'guard';
    public const string GROUP_WING = 'wing';
    public const string GROUP_BIG = 'big';

    /** @var list<string> */
    public const array GROUPS = [self::GROUP_GUARD, self::GROUP_WING, self::GROUP_BIG];

    public function __construct(private readonly AssetValuer $valuer = new AssetValuer())
    {
    }

    public function score(TeamAssets $assets, TeamMode $mode): TeamScore
    {
        // An injured player fills no slot, so an injury gap lowers the score directly.
        $healthy = array_filter($assets->players, static fn (SuggestionPlayer $p): bool => !$p->injured);
        $values = array_values(array_map(fn (SuggestionPlayer $p): float => $this->valuer->playerValue($p, $mode), $healthy));
        rsort($values);

        $total = 0.0;
        foreach ($values as $slot => $value) {
            $total += $value * (self::SLOT_WEIGHTS[$slot] ?? self::DEEP_BENCH_WEIGHT);
        }
        foreach ($assets->picks as $pick) {
            $total += $this->valuer->pickValue($pick, $mode);
        }

        $groupCounts = [self::GROUP_GUARD => 0, self::GROUP_WING => 0, self::GROUP_BIG => 0];
        foreach (TeamModeGuesser::rotation($assets->players) as $player) {
            $group = self::positionGroup($player->pos);
            if ($group !== null) {
                $groupCounts[$group]++;
            }
        }

        $penalty = $mode === TeamMode::WinNow ? self::WIN_NOW_HOLE_PENALTY : self::REBUILD_HOLE_PENALTY;
        $positionHoles = [];
        foreach ($groupCounts as $group => $count) {
            $shortfall = self::MIN_PER_GROUP - $count;
            if ($shortfall > 0) {
                $positionHoles[$group] = $shortfall;
                $total -= $penalty * $shortfall;
            }
        }

        return new TeamScore($total, $groupCounts, $positionHoles);
    }

    /**
     * Map an ibl_plr.pos value to its position group, or null for an empty position.
     */
    public static function positionGroup(string $pos): ?string
    {
        return match ($pos) {
            'PG', 'SG', 'G' => self::GROUP_GUARD,
            'SF', 'GF', 'F' => self::GROUP_WING,
            'PF', 'C' => self::GROUP_BIG,
            default => null,
        };
    }
}
