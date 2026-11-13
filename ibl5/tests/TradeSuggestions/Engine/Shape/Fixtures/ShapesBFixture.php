<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine\Shape\Fixtures;

use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\LeagueSnapshotBuilder;
use TradeSuggestions\Engine\NeedPair;
use TradeSuggestions\Engine\Shape\Contracts\DealShapeInterface;
use TradeSuggestions\Profile\SeasonLineup;
use TradeSuggestions\Profile\SlotStrength;
use TradeSuggestions\Projection\ProjectedSeason;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\TradeCandidate;

/**
 * A small four-team snapshot for the deal-shape tests. Team 1 (partner) owns
 * pids 101..113 and team 2 (user) pids 201..213. Each team's bench at offset 0
 * is 106..110 / 206..210 (one per slot, PG SG SF PF C).
 */
final class ShapesBFixture
{
    public const int USER = 2;
    public const int PARTNER = 1;

    /**
     * @param list<int> $partnerExpiring partner pids with no salary past the current season
     * @param list<int> $userExpiring user pids with no salary past the current season
     * @param list<int> $partnerGap partner pids whose salary ends after next season
     * @param list<int> $userGap user pids whose salary ends after next season
     */
    public static function snapshot(array $partnerExpiring = [], array $userExpiring = [], array $partnerGap = [], array $userGap = []): LeagueSnapshot
    {
        $inputs = SnapshotFactory::inputs(4, 13, [
            self::PARTNER => self::roster(self::PARTNER, $partnerExpiring, $partnerGap),
            self::USER => self::roster(self::USER, $userExpiring, $userGap),
        ]);

        return LeagueSnapshotBuilder::build($inputs);
    }

    /**
     * @param list<int> $expiring
     * @param list<int> $gap
     * @return list<ProjectionPlayer>
     */
    private static function roster(int $tid, array $expiring, array $gap): array
    {
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];
        $roster = [];
        for ($i = 1; $i <= 13; $i++) {
            $pid = $tid * 100 + $i;
            $pos = $positions[($i - 1) % 5];
            if (in_array($pid, $expiring, true)) {
                $salary = [1 => 400, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
            } elseif (in_array($pid, $gap, true)) {
                $salary = [1 => 400, 2 => 400, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
            } else {
                $salary = [1 => 400, 2 => 400, 3 => 400, 4 => 400, 5 => 400, 6 => 400];
            }
            $roster[] = ProjectionFactory::player(
                pid: $pid,
                tid: $tid,
                pos: $pos,
                age: 28,
                salaryByYear: $salary,
                ratings: ProjectionFactory::ratingSet([], 30.0 + $i * 4 + ($tid === self::USER ? 0 : 2)),
                dcDepth: [$pos => intdiv($i - 1, 5) + 1],
                dcMinutes: $i <= 5 ? 32 : ($i <= 10 ? 16 : 0),
            );
        }

        return $roster;
    }

    /**
     * @param array<string, mixed> $changes constructor fields to replace
     */
    public static function with(LeagueSnapshot $s, array $changes): LeagueSnapshot
    {
        return new LeagueSnapshot(...array_merge(get_object_vars($s), $changes));
    }

    /**
     * @param array<string, array{0: int|null, 1?: list<int>}> $spec slot => [starter rank, buried pids]
     * @return array<string, SlotStrength>
     */
    public static function strengths(array $spec): array
    {
        $out = [];
        foreach ($spec as $slot => $row) {
            $out[$slot] = new SlotStrength($slot, $row[0], null, 3, false, $row[1] ?? []);
        }

        return $out;
    }

    /**
     * @param array<string, array{0: int|null, 1?: list<int>}> $spec
     */
    public static function withStrengths(LeagueSnapshot $s, int $tid, array $spec): LeagueSnapshot
    {
        $positionStrength = $s->positionStrength;
        $positionStrength[0][$tid] = self::strengths($spec);

        return self::with($s, ['positionStrength' => $positionStrength]);
    }

    /**
     * Replaces a team's offset-0 rotation (slot => pids in minutes order), keeping its depth chart.
     *
     * @param array<string, list<int>> $rotation
     */
    public static function withRotation(LeagueSnapshot $s, int $tid, array $rotation): LeagueSnapshot
    {
        $l = $s->lineup(0, $tid);
        $lineups = $s->lineups;
        $lineups[0][$tid] = new SeasonLineup($l->tid, $l->offset, $l->depth, $rotation, $l->players, $l->chartReliable, $l->minutesWeight);

        return self::with($s, ['lineups' => $lineups]);
    }

    /**
     * Reprices a pid so his negative surplus at offset 1 equals `$overpay` and
     * his surplus at offsets 2 and 3 is zero.
     */
    public static function withOverpay(LeagueSnapshot $s, int $pid, int $overpay): LeagueSnapshot
    {
        $surplus = $s->surplusBySeason($pid);
        $projections = $s->projections;
        foreach ([1, 2, 3] as $offset) {
            $season = $projections[$pid][$offset];
            $fair = ($surplus[$offset] ?? 0) + $season->salary;
            $salary = $fair + ($offset === 1 ? $overpay : 0);
            $projections[$pid][$offset] = new ProjectedSeason($season->offset, $season->age, $salary, true, $season->ratings, $season->per36);
        }

        return self::with($s, ['projections' => $projections]);
    }

    public static function needPair(int $giver, int $receiver, int $offset = 0): NeedPair
    {
        return new NeedPair($offset, NeedPair::KIND_SLOT, 'PG', $giver, $receiver, 3, 20, 1.0);
    }

    /**
     * @return list<TradeCandidate>
     */
    public static function collect(DealShapeInterface $shape, LeagueSnapshot $s, AssetPool $pool): array
    {
        return iterator_to_array($shape->generate($s, self::USER, self::PARTNER, $pool), false);
    }

    /**
     * @param list<TradeCandidate> $candidates
     * @return list<array{list<int>, list<int>, list<int>, list<int>}>
     */
    public static function flat(array $candidates): array
    {
        return array_map(
            static fn (TradeCandidate $c): array => [$c->userPids, $c->userPickIds, $c->partnerPids, $c->partnerPickIds],
            $candidates,
        );
    }
}
