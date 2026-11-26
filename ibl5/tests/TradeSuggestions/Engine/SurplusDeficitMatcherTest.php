<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\LeagueSnapshotBuilder;
use TradeSuggestions\Engine\NeedPair;
use TradeSuggestions\Engine\SurplusDeficitMatcher;
use TradeSuggestions\Profile\ProfileDimension;
use TradeSuggestions\Profile\RankUtility;
use TradeSuggestions\Profile\SlotStrength;
use TradeSuggestions\Profile\TeamProfile;
use TradeSuggestions\Projection\ProjectedSeason;
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

    public function testNeedPairsSkipOffsetsMissingEitherTeam(): void
    {
        foreach ([self::USER, self::PARTNER] as $present) {
            $s = self::withProfiles(
                [0 => [$present => ['r_3gp' => 3]]],
                [0 => [$present => ['C' => 3]]],
            );

            self::assertSame([], SurplusDeficitMatcher::needPairs($s, self::USER, self::PARTNER));
        }
    }

    /**
     * @return array<string, array{int, int, ?int}> user rank, partner rank, expected giver tid
     */
    public static function rankCombinationProvider(): array
    {
        $out = [];
        foreach ([3 => 'strong', 14 => 'middle', 25 => 'weak'] as $u => $uName) {
            foreach ([3 => 'strong', 14 => 'middle', 25 => 'weak'] as $p => $pName) {
                $giver = match (true) {
                    $u === 3 && $p === 25 => self::USER,
                    $u === 25 && $p === 3 => self::PARTNER,
                    default => null,
                };
                $out["user $uName, partner $pName"] = [$u, $p, $giver];
            }
        }

        return $out;
    }

    #[DataProvider('rankCombinationProvider')]
    public function testDimensionPairNeedsOneSurplusAndTheOtherSidesDeficit(int $userRank, int $partnerRank, ?int $giver): void
    {
        $s = self::withProfiles([0 => [self::USER => ['r_3gp' => $userRank], self::PARTNER => ['r_3gp' => $partnerRank]]]);

        $pairs = SurplusDeficitMatcher::needPairs($s, self::USER, self::PARTNER);

        self::assertSame($giver === null ? [] : [$giver], array_map(static fn (NeedPair $p): int => $p->giverTid, $pairs));
    }

    #[DataProvider('rankCombinationProvider')]
    public function testSlotPairNeedsOneStrongSlotAndTheOtherSidesWeakSlot(int $userRank, int $partnerRank, ?int $giver): void
    {
        $s = self::withProfiles([], [0 => [self::USER => ['C' => $userRank], self::PARTNER => ['C' => $partnerRank]]]);

        $pairs = SurplusDeficitMatcher::needPairs($s, self::USER, self::PARTNER);

        self::assertSame($giver === null ? [] : [$giver], array_map(static fn (NeedPair $p): int => $p->giverTid, $pairs));
    }

    public function testNeedPairsKeepScanningAfterAnOffsetMissingATeam(): void
    {
        $s = self::withProfiles(
            [
                0 => [self::USER => ['r_3gp' => 3]],
                1 => [self::USER => ['r_3gp' => 3], self::PARTNER => ['r_3gp' => 24]],
            ],
            [
                0 => [self::USER => ['C' => 3]],
                1 => [self::USER => ['C' => 3], self::PARTNER => ['C' => 25]],
            ],
        );

        $pairs = SurplusDeficitMatcher::needPairs($s, self::USER, self::PARTNER);

        self::assertSame([[1, NeedPair::KIND_SLOT, 'C'], [1, NeedPair::KIND_DIMENSION, 'r_3gp']], array_map(
            static fn (NeedPair $p): array => [$p->offset, $p->kind, $p->key],
            $pairs,
        ));
    }

    public function testAssetsForGiverWithoutALineupIsEmpty(): void
    {
        $s = self::withRoster([self::rotationPlayer(9101), self::rotationPlayer(9102)]);

        self::assertSame([], SurplusDeficitMatcher::assetsFor(
            $s,
            99,
            new NeedPair(0, NeedPair::KIND_SLOT, 'PG', 99, self::PARTNER, 3, 25, 1.0),
            5,
        ));
        self::assertSame([], SurplusDeficitMatcher::assetsFor($s, 99, self::dimensionPair('r_3gp'), 5));
    }

    public function testAssetsForSlotPairWithoutSlotRanksFallsBackToPidOrder(): void
    {
        $s = self::withRoster(self::slotRoster());
        $s = new LeagueSnapshot(...[...get_object_vars($s), 'slotRanks' => []]);
        $pair = new NeedPair(0, NeedPair::KIND_SLOT, 'PG', self::USER, self::PARTNER, 3, 25, 1.0);

        self::assertSame([9104, 9101, 9102, 9103, 9105], SurplusDeficitMatcher::assetsFor($s, self::USER, $pair, 10));
    }

    public function testAssetsForOrdersSeveralMissingProductionLinesLastByAscendingPid(): void
    {
        $s = self::withRoster([
            self::rotationPlayer(9103, noProduction: true),
            self::rotationPlayer(9101, production: ProjectionFactory::productionLine(['stats_fgm' => 50, 'stats_fga' => 100])),
            self::rotationPlayer(9102, noProduction: true),
            self::rotationPlayer(9105, production: ProjectionFactory::productionLine(['stats_fgm' => 90, 'stats_fga' => 100])),
            self::rotationPlayer(9100, noProduction: true),
        ]);

        self::assertSame([9105, 9101, 9100, 9102, 9103], SurplusDeficitMatcher::assetsFor($s, self::USER, self::dimensionPair('fg_pct'), 5));
    }

    public function testOverlapWeightIsTheGainOfOneReceiverPlace(): void
    {
        $s = self::withProfiles(
            [0 => [self::USER => ['r_3gp' => 3], self::PARTNER => ['r_3gp' => 24]]],
            [0 => [self::USER => ['C' => 3], self::PARTNER => ['C' => 25]]],
        );

        $pairs = SurplusDeficitMatcher::needPairs($s, self::USER, self::PARTNER);

        $byKind = [];
        foreach ($pairs as $pair) {
            $byKind[$pair->kind] = $pair;
        }
        self::assertSame(RankUtility::gain(24, 23), $byKind[NeedPair::KIND_DIMENSION]->overlapWeight);
        self::assertSame(RankUtility::gain(25, 24), $byKind[NeedPair::KIND_SLOT]->overlapWeight);
    }

    public function testAssetsForSlotPairListsStarterThenRestByPoolRank(): void
    {
        $s = self::withRoster(self::slotRoster());
        $pair = new NeedPair(0, NeedPair::KIND_SLOT, 'PG', self::USER, self::PARTNER, 3, 25, 1.0);

        // 9104 starts. The rest follow by pool rank, not by minutes (9101, 9102, 9103, 9105).
        self::assertSame(self::EXPECTED_SLOT_ORDER, SurplusDeficitMatcher::assetsFor($s, self::USER, $pair, 10));
        self::assertSame(array_slice(self::EXPECTED_SLOT_ORDER, 0, 3), SurplusDeficitMatcher::assetsFor($s, self::USER, $pair, 3));
        self::assertSame([9104], SurplusDeficitMatcher::assetsFor($s, self::USER, $pair, 1));
    }

    /**
     * @return array<string, array{string, array<string, int>, array<string, int>}>
     */
    public static function rateProvider(): array
    {
        return [
            'fg_pct' => ['fg_pct', ['stats_fgm' => 90, 'stats_fga' => 100, 'stats_3gm' => 2, 'stats_3ga' => 5], ['stats_fgm' => 0, 'stats_fga' => 0]],
            'ft_pct' => ['ft_pct', ['stats_ftm' => 90, 'stats_fta' => 100, 'stats_3gm' => 2, 'stats_3ga' => 5], ['stats_ftm' => 0, 'stats_fta' => 0]],
            'tpa_rate' => ['tpa_rate', ['stats_3ga' => 90, 'stats_fga' => 100], ['stats_3ga' => 0, 'stats_fga' => 0]],
        ];
    }

    /**
     * @param array<string, int> $high
     * @param array<string, int> $zero
     */
    #[DataProvider('rateProvider')]
    public function testAssetsForRateDimensionsRankByRatioAndZeroDenominatorIsZero(string $key, array $high, array $zero): void
    {
        $a = self::rotationPlayer(9101, production: ProjectionFactory::productionLine($high));
        $b = self::rotationPlayer(9102, production: ProjectionFactory::productionLine());
        $c = self::rotationPlayer(9103, production: ProjectionFactory::productionLine($zero));
        $none = self::rotationPlayer(9100, noProduction: true);
        $s = self::withRoster([$none, $c, $b, $a]);

        self::assertSame([9101, 9102, 9103, 9100], SurplusDeficitMatcher::assetsFor($s, self::USER, self::dimensionPair($key), 5));
    }

    public function testAssetsForRankingByRatioNotByProduct(): void
    {
        $small = self::rotationPlayer(9101, production: ProjectionFactory::productionLine(['stats_fgm' => 90, 'stats_fga' => 100]));
        $big = self::rotationPlayer(9102, production: ProjectionFactory::productionLine(['stats_fgm' => 200, 'stats_fga' => 420]));
        $s = self::withRoster([$big, $small]);

        self::assertSame([9101, 9102], SurplusDeficitMatcher::assetsFor($s, self::USER, self::dimensionPair('fg_pct'), 5));
    }

    public function testAssetsForPutsMissingProductionAfterAZeroRate(): void
    {
        $none = self::rotationPlayer(9100, noProduction: true);
        $zero = self::rotationPlayer(9101, production: ProjectionFactory::productionLine(['stats_fgm' => 0, 'stats_fga' => 100]));
        $s = self::withRoster([$none, $zero]);

        self::assertSame([9101, 9100], SurplusDeficitMatcher::assetsFor($s, self::USER, self::dimensionPair('fg_pct'), 5));
    }

    public function testAssetsForBreaksTiesByAscendingPid(): void
    {
        $s = self::withRoster([self::rated(9103, 'r_3gp', 70, 50), self::rated(9101, 'r_3gp', 70, 50), self::rated(9102, 'r_3gp', 70, 50)]);

        self::assertSame([9101, 9102, 9103], SurplusDeficitMatcher::assetsFor($s, self::USER, self::dimensionPair('r_3gp'), 5));
    }

    public function testAssetsForStatDimensionReadsProjectedPer36(): void
    {
        $roster = [];
        foreach ([9101, 9102, 9103, 9104] as $pid) {
            $roster[] = self::rotationPlayer($pid);
        }
        $s = self::withRoster($roster);
        $s = self::withSeason($s, 9101, 0, per36: ['pts' => 20.0]);
        $s = self::withSeason($s, 9102, 0, per36: ['pts' => 0.5]);
        $s = self::withSeason($s, 9103, 0, per36: []);
        $s = self::withSeason($s, 9104, 0, absent: true);

        self::assertSame([9101, 9102, 9103, 9104], SurplusDeficitMatcher::assetsFor($s, self::USER, self::dimensionPair('pts'), 5));
        // Lower is better for turnovers, so a missing line (0.0) leads.
        $s = self::withSeason($s, 9101, 0, per36: ['tov' => 3.0]);
        $s = self::withSeason($s, 9102, 0, per36: ['tov' => 1.0]);
        self::assertSame([9103, 9104, 9102, 9101], SurplusDeficitMatcher::assetsFor($s, self::USER, self::dimensionPair('tov'), 5));
    }

    public function testAssetsForRatingDimensionReadsProjectedRatingsWhenPresent(): void
    {
        $s = self::withRoster([
            self::rated(9101, 'r_3gp', 90, 50),
            self::rated(9102, 'r_3gp', 20, 50),
            self::rated(9103, 'r_3gp', 50, 50),
        ]);
        $s = self::withSeason($s, 9101, 0, ratings: ProjectionFactory::ratingSet(['r_3gp' => 20]));
        $s = self::withSeason($s, 9102, 0, ratings: ProjectionFactory::ratingSet(['r_3gp' => 80]));
        $s = self::withSeason($s, 9103, 0, absent: true);

        self::assertSame([9102, 9103, 9101], SurplusDeficitMatcher::assetsFor($s, self::USER, self::dimensionPair('r_3gp'), 5));
    }

    private const array EXPECTED_SLOT_ORDER = [9104, 9102, 9105, 9103, 9101];

    /**
     * Five point guards. Minutes run 9101 down to 9105; 9104 is the depth-chart
     * starter although he is fourth by minutes.
     *
     * @return list<ProjectionPlayer>
     */
    private static function slotRoster(): array
    {
        $roster = [];
        foreach ([9101 => [30, 40, 0], 9102 => [28, 80, 0], 9103 => [26, 60, 0], 9104 => [24, 50, 1], 9105 => [22, 70, 0]] as $pid => [$minutes, $base, $starter]) {
            $ratings = [];
            foreach (Rating::ALL as $k) {
                $ratings[$k] = $base;
            }
            $roster[] = ProjectionFactory::player(
                pid: $pid,
                tid: self::USER,
                pos: 'PG',
                ratings: ProjectionFactory::ratingSet($ratings),
                dcDepth: $starter === 1 ? ['PG' => 1] : [],
                dcMinutes: $minutes,
            );
        }

        // A reliable depth chart needs a starter at every slot.
        foreach (['SG', 'SF', 'PF', 'C'] as $i => $slot) {
            $roster[] = ProjectionFactory::player(pid: 9200 + $i, tid: self::USER, pos: $slot, dcDepth: [$slot => 1], dcMinutes: 0);
        }

        return $roster;
    }

    /**
     * @param array<string, float>|null $per36
     */
    private static function withSeason(LeagueSnapshot $s, int $pid, int $offset, ?array $per36 = null, ?\TradeSuggestions\Projection\RatingSet $ratings = null, bool $absent = false): LeagueSnapshot
    {
        $projections = $s->projections;
        $old = $projections[$pid][$offset];
        if ($absent) {
            unset($projections[$pid][$offset]);
        } else {
            $projections[$pid][$offset] = new ProjectedSeason(
                $old->offset,
                $old->age,
                $old->salary,
                $old->onRoster,
                $ratings ?? $old->ratings,
                $per36 ?? $old->per36,
            );
        }

        return new LeagueSnapshot(...[...get_object_vars($s), 'projections' => $projections]);
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
