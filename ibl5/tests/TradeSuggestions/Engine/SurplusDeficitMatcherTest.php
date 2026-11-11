<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\LeagueSnapshotBuilder;
use TradeSuggestions\Engine\NeedPair;
use TradeSuggestions\Engine\SurplusDeficitMatcher;
use TradeSuggestions\Profile\ProfileDimension;
use TradeSuggestions\Profile\SlotStrength;
use TradeSuggestions\Profile\TeamProfile;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\Projection\Rating;
use TradeSuggestions\SlotMapper;

final class SurplusDeficitMatcherTest extends TestCase
{
    private const int USER = 1;
    private const int PARTNER = 2;

    private static ?LeagueSnapshot $league = null;

    private static function league(): LeagueSnapshot
    {
        return self::$league ??= SnapshotFactory::league();
    }

    public function testSurplusToDeficitEmitsPairInBothDirections(): void
    {
        $s = self::withProfiles(
            [0 => [self::USER => ['r_3gp' => 3, 'r_blk' => 24], self::PARTNER => ['r_3gp' => 24, 'r_blk' => 3]]],
        );

        $pairs = SurplusDeficitMatcher::needPairs($s, self::USER, self::PARTNER);

        self::assertCount(2, $pairs);
        $byKey = [];
        foreach ($pairs as $pair) {
            $byKey[$pair->key] = $pair;
        }
        self::assertSame(self::USER, $byKey['r_3gp']->giverTid);
        self::assertSame(self::PARTNER, $byKey['r_3gp']->receiverTid);
        self::assertSame(3, $byKey['r_3gp']->giverRank);
        self::assertSame(24, $byKey['r_3gp']->receiverRank);
        self::assertSame(self::PARTNER, $byKey['r_blk']->giverTid);
        self::assertSame(self::USER, $byKey['r_blk']->receiverTid);
        self::assertSame(NeedPair::KIND_DIMENSION, $byKey['r_blk']->kind);
    }

    public function testMiddleRanksEmitNoPair(): void
    {
        $s = self::withProfiles(
            [0 => [self::USER => ['r_3gp' => 12, 'r_blk' => 15], self::PARTNER => ['r_3gp' => 22, 'r_blk' => 15]]],
        );

        self::assertSame([], SurplusDeficitMatcher::needPairs($s, self::USER, self::PARTNER));
    }

    public function testStrongSlotToWeakSlotEmitsSlotPair(): void
    {
        $s = self::withProfiles(
            [0 => [self::USER => [], self::PARTNER => []]],
            [0 => [self::USER => ['C' => 3], self::PARTNER => ['C' => 25]]],
        );

        $pairs = SurplusDeficitMatcher::needPairs($s, self::USER, self::PARTNER);

        self::assertCount(1, $pairs);
        self::assertSame(NeedPair::KIND_SLOT, $pairs[0]->kind);
        self::assertSame('C', $pairs[0]->key);
        self::assertSame(self::USER, $pairs[0]->giverTid);
        self::assertSame(25, $pairs[0]->receiverRank);
    }

    public function testPairsSortedByOverlapWeightDesc(): void
    {
        $s = self::withProfiles([
            0 => [self::USER => ['r_blk' => 2], self::PARTNER => ['r_blk' => 21]],
            1 => [self::USER => ['r_3gp' => 2, 'r_blk' => 2], self::PARTNER => ['r_3gp' => 28, 'r_blk' => 20]],
        ]);

        $pairs = SurplusDeficitMatcher::needPairs($s, self::USER, self::PARTNER);

        self::assertCount(3, $pairs);
        $previous = null;
        foreach ($pairs as $pair) {
            if ($previous !== null) {
                self::assertGreaterThanOrEqual($pair->overlapWeight, $previous->overlapWeight);
            }
            $previous = $pair;
        }
        self::assertSame([[0, 'r_blk'], [1, 'r_3gp'], [1, 'r_blk']], array_map(
            static fn (NeedPair $p): array => [$p->offset, $p->key],
            $pairs,
        ));
    }

    public function testAssetsForDimensionPairOrdersByThatRatingOnly(): void
    {
        $shooter = self::rated(9101, 'r_3gp', 90, 20);
        $allRound = self::rated(9102, 'r_3gp', 60, 85);
        $s = self::withRoster([$allRound, $shooter]);

        $assets = SurplusDeficitMatcher::assetsFor($s, self::USER, self::dimensionPair('r_3gp'), 5);

        self::assertSame([9101, 9102], $assets);
    }

    public function testAssetsForLowerIsBetterDimensionInvertsOrder(): void
    {
        $careful = self::rated(9101, 'r_tvr', 30, 50);
        $sloppy = self::rated(9102, 'r_tvr', 70, 50);
        $s = self::withRoster([$sloppy, $careful]);

        self::assertSame([9101, 9102], SurplusDeficitMatcher::assetsFor($s, self::USER, self::dimensionPair('r_tvr'), 5));
    }

    public function testAssetsForRateDimensionReadsProductionLine(): void
    {
        $poor = self::rotationPlayer(9101, production: ProjectionFactory::productionLine(['stats_3gm' => 20, 'stats_3ga' => 100]));
        $good = self::rotationPlayer(9102, production: ProjectionFactory::productionLine(['stats_3gm' => 45, 'stats_3ga' => 100]));
        $none = self::rotationPlayer(9100, noProduction: true);
        $s = self::withRoster([$none, $poor, $good]);

        self::assertSame([9102, 9101, 9100], SurplusDeficitMatcher::assetsFor($s, self::USER, self::dimensionPair('tp_pct'), 5));
    }

    public function testAssetsForEmptyRosterIsEmpty(): void
    {
        $s = self::withRoster([]);

        self::assertSame([], SurplusDeficitMatcher::assetsFor($s, self::USER, self::dimensionPair('r_3gp'), 5));
        self::assertSame([], SurplusDeficitMatcher::assetsFor(
            $s,
            self::USER,
            new NeedPair(0, NeedPair::KIND_SLOT, 'PG', self::USER, self::PARTNER, 3, 25, 1.0),
            5,
        ));
    }

    public function testAssetsForRespectsLimit(): void
    {
        $roster = [];
        for ($i = 1; $i <= 5; $i++) {
            $roster[] = self::rotationPlayer(9100 + $i);
        }
        $s = self::withRoster($roster);

        self::assertCount(2, SurplusDeficitMatcher::assetsFor($s, self::USER, self::dimensionPair('r_3gp'), 2));
    }

    private static function dimensionPair(string $key): NeedPair
    {
        return new NeedPair(0, NeedPair::KIND_DIMENSION, $key, self::USER, self::PARTNER, 3, 24, 1.0);
    }

    private static function rated(int $pid, string $key, int $value, int $base): ProjectionPlayer
    {
        $ratings = [];
        foreach (Rating::ALL as $k) {
            $ratings[$k] = $base;
        }
        $ratings[$key] = $value;

        return self::rotationPlayer($pid, ratings: $ratings);
    }

    /**
     * @param array<string, int> $ratings
     */
    private static function rotationPlayer(
        int $pid,
        array $ratings = [],
        ?\TradeSuggestions\ProductionLine $production = null,
        bool $noProduction = false,
    ): ProjectionPlayer {
        return ProjectionFactory::player(
            pid: $pid,
            tid: self::USER,
            pos: 'PG',
            ratings: ProjectionFactory::ratingSet($ratings),
            noProduction: $noProduction,
            production: $production,
            dcMinutes: 30,
        );
    }

    /**
     * @param list<ProjectionPlayer> $roster
     */
    private static function withRoster(array $roster): LeagueSnapshot
    {
        return LeagueSnapshotBuilder::build(SnapshotFactory::inputs(overrideRosters: [self::USER => $roster]));
    }

    /**
     * Copy of the synthetic league with hand-set profile ranks and slot
     * starter ranks for the user and partner. Every rank not named is 14 (no
     * surplus, no deficit) and every slot not named has starter rank 14.
     *
     * @param array<int, array<int, array<string, int>>> $profileRanks offset => tid => key => rank
     * @param array<int, array<int, array<string, int>>> $slotRanks offset => tid => slot => starter rank
     */
    private static function withProfiles(array $profileRanks, array $slotRanks = []): LeagueSnapshot
    {
        $s = self::league();
        $profiles = [];
        foreach ($profileRanks as $offset => $byTid) {
            foreach ($byTid as $tid => $overrides) {
                $ranks = [];
                foreach ([...ProfileDimension::KEYS, ProfileDimension::OFFENSE_MEAN, ProfileDimension::DEFENSE_MEAN] as $key) {
                    $ranks[$key] = $overrides[$key] ?? 14;
                }
                $profiles[$offset][$tid] = new TeamProfile($tid, $offset, [], $ranks);
            }
        }
        $strength = [];
        foreach ($slotRanks as $offset => $byTid) {
            foreach ($byTid as $tid => $overrides) {
                foreach (SlotMapper::SLOTS as $slot) {
                    $strength[$offset][$tid][$slot] = new SlotStrength($slot, $overrides[$slot] ?? 14, null, 2, false, []);
                }
            }
        }

        return new LeagueSnapshot(
            seasonYear: $s->seasonYear,
            advancesContractYears: $s->advancesContractYears,
            regularSeason: $s->regularSeason,
            teamIds: $s->teamIds,
            players: $s->players,
            teamOfPid: $s->teamOfPid,
            projections: $s->projections,
            lineups: $s->lineups,
            slotRanks: $s->slotRanks,
            profiles: $profiles,
            positionStrength: $strength,
            situations: $s->situations,
            standings: $s->standings,
            streaks: $s->streaks,
            fairSalary: $s->fairSalary,
            cashByTeamAndOffset: $s->cashByTeamAndOffset,
            payrollByOffset: $s->payrollByOffset,
            payrollRanks: $s->payrollRanks,
            picks: $s->picks,
            draftOrder: $s->draftOrder,
            draftIndex: $s->draftIndex,
            modes: $s->modes,
            injury: $s->injury,
            teamFactors: $s->teamFactors,
            rivalFactors: $s->rivalFactors,
            resignLikelihood: $s->resignLikelihood,
            likelyToWalk: $s->likelyToWalk,
            teamNames: $s->teamNames,
        );
    }
}
