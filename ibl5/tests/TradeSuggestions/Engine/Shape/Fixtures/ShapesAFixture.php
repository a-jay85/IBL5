<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine\Shape\Fixtures;

use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\LeagueSnapshotBuilder;
use TradeSuggestions\Engine\Shape\Contracts\DealShapeInterface;
use TradeSuggestions\Projection\ProjectedSeason;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;

/**
 * A small four-team snapshot (user team 3, partner team 1) shared by the
 * shape tests. Players 101-113 and 301-313 hold 400 a year for six years; the
 * depth chart makes pids 101-105 and 301-305 the PG, SG, SF, PF, C starters.
 */
final class ShapesAFixture
{
    public const int USER = 3;
    public const int PARTNER = 1;

    private static ?LeagueSnapshot $base = null;

    public static function base(): LeagueSnapshot
    {
        if (self::$base === null) {
            $inputs = SnapshotFactory::inputs(4, 13, [
                self::PARTNER => self::roster(self::PARTNER),
                self::USER => self::roster(self::USER),
            ]);
            self::$base = LeagueSnapshotBuilder::build($inputs);
        }

        return self::$base;
    }

    /**
     * @param array<string, mixed> $changes constructor fields to replace
     */
    public static function with(LeagueSnapshot $s, array $changes): LeagueSnapshot
    {
        return new LeagueSnapshot(...array_merge(get_object_vars($s), $changes));
    }

    public static function withModes(LeagueSnapshot $s, TeamMode $user, TeamMode $partner): LeagueSnapshot
    {
        return self::with($s, ['modes' => [self::USER => $user, self::PARTNER => $partner] + $s->modes]);
    }

    /**
     * Adds (or replaces) players in the snapshot's pid map.
     *
     * @param array<int, int> $ages pid => age
     */
    public static function withAges(LeagueSnapshot $s, array $ages): LeagueSnapshot
    {
        $players = $s->players;
        foreach ($ages as $pid => $age) {
            $players[$pid] = ProjectionFactory::player(pid: $pid, tid: self::PARTNER, age: $age);
        }

        return self::with($s, ['players' => $players]);
    }

    /**
     * Reprices a player so his surplus at each given offset equals the target.
     *
     * @param array<int, int> $targets offset => surplus
     */
    public static function withSurplus(LeagueSnapshot $s, int $pid, array $targets): LeagueSnapshot
    {
        $surplus = $s->surplusBySeason($pid);
        $projections = $s->projections;
        foreach ($targets as $offset => $target) {
            $season = $projections[$pid][$offset];
            $fair = ($surplus[$offset] ?? 0) + $season->salary;
            $projections[$pid][$offset] = new ProjectedSeason($season->offset, $season->age, $fair - $target, true, $season->ratings, $season->per36);
        }
        $repriced = self::with($s, ['projections' => $projections]);
        foreach ($targets as $offset => $target) {
            if ($repriced->surplusBySeason($pid)[$offset] !== $target) {
                throw new \LogicException("Could not price pid {$pid} at offset {$offset} to {$target}");
            }
        }

        return $repriced;
    }

    /**
     * @return list<TradeCandidate>
     */
    public static function collect(DealShapeInterface $shape, LeagueSnapshot $s, AssetPool $pool): array
    {
        return iterator_to_array($shape->generate($s, self::USER, self::PARTNER, $pool), false);
    }

    /**
     * @return list<ProjectionPlayer>
     */
    private static function roster(int $tid): array
    {
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];
        $roster = [];
        for ($i = 1; $i <= 13; $i++) {
            $pos = $positions[($i - 1) % 5];
            $roster[] = ProjectionFactory::player(
                pid: $tid * 100 + $i,
                tid: $tid,
                pos: $pos,
                age: 28,
                salaryByYear: [1 => 400, 2 => 400, 3 => 400, 4 => 400, 5 => 400, 6 => 400],
                ratings: ProjectionFactory::ratingSet([], 30.0 + $i * 4),
                dcDepth: [$pos => intdiv($i - 1, 5) + 1],
                dcMinutes: $i <= 5 ? 32 : ($i <= 10 ? 16 : 0),
            );
        }

        return $roster;
    }
}
