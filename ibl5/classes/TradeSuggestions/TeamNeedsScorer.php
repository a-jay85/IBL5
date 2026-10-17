<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Pure scorer: turns a team's assets into now / short / long horizon scores.
 *
 * - now: rotation quality minus a penalty per missing player in each position group.
 * - short: rotation quality discounted for expiring and aging players.
 * - long: young-player quality + pick value + future cap room.
 */
final class TeamNeedsScorer
{
    public const int ROTATION_SIZE = 8;
    public const int MIN_PER_GROUP = 2;
    public const int POSITION_HOLE_PENALTY = 6;
    public const float EXPIRING_FACTOR = 0.5;
    public const float AGING_FACTOR = 0.6;
    public const int AGING_AFTER = 30;
    public const int YOUNG_AGE = 24;
    public const int FUTURE_CAP_REFERENCE = 20000;
    public const int CAP_ROOM_DIVISOR = 1000;

    public const string GROUP_GUARD = 'guard';
    public const string GROUP_WING = 'wing';
    public const string GROUP_BIG = 'big';

    /** @var list<string> */
    public const array GROUPS = [self::GROUP_GUARD, self::GROUP_WING, self::GROUP_BIG];

    public function score(TeamAssets $assets): HorizonScores
    {
        $rotation = self::rotation($assets->players);

        $groupCounts = [self::GROUP_GUARD => 0, self::GROUP_WING => 0, self::GROUP_BIG => 0];
        $now = 0.0;
        $short = 0.0;
        foreach ($rotation as $player) {
            $now += $player->quality;

            $group = self::positionGroup($player->pos);
            if ($group !== null) {
                $groupCounts[$group]++;
            }

            $contractFactor = $player->yearsRemaining >= 2 ? 1.0 : self::EXPIRING_FACTOR;
            $ageFactor = $player->age <= self::AGING_AFTER ? 1.0 : self::AGING_FACTOR;
            $short += $player->quality * $contractFactor * $ageFactor;
        }

        $positionHoles = [];
        foreach ($groupCounts as $group => $count) {
            $shortfall = self::MIN_PER_GROUP - $count;
            if ($shortfall > 0) {
                $positionHoles[$group] = $shortfall;
                $now -= self::POSITION_HOLE_PENALTY * $shortfall;
            }
        }

        $long = 0.0;
        $futureSalary = 0;
        foreach ($assets->players as $player) {
            if ($player->age <= self::YOUNG_AGE) {
                $long += $player->quality;
            }
            $futureSalary += $player->futureSalary;
        }
        foreach ($assets->picks as $pick) {
            $long += $pick->value;
        }
        $long += max(0, self::FUTURE_CAP_REFERENCE - $futureSalary) / self::CAP_ROOM_DIVISOR;

        return new HorizonScores($now, $short, $long, $positionHoles);
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

    /**
     * Top ROTATION_SIZE healthy players by quality, ties broken by lower pid.
     *
     * @param list<SuggestionPlayer> $players
     * @return list<SuggestionPlayer>
     */
    private static function rotation(array $players): array
    {
        $healthy = array_values(array_filter(
            $players,
            static fn (SuggestionPlayer $p): bool => !$p->injured
        ));
        usort($healthy, static fn (SuggestionPlayer $a, SuggestionPlayer $b): int => [$b->quality, $a->pid] <=> [$a->quality, $b->pid]);

        return array_slice($healthy, 0, self::ROTATION_SIZE);
    }
}
