<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine\Shape;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Shape\Fixtures\ShapesBFixture;
use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\Shape\ChangeOfScenery;

/**
 * User pid 201 is buried at PG. The partner's bench pids at offset 0 are
 * 106 (PG), 107 (SG), 108 (SF), 109 (PF) and 110 (C).
 */
final class ChangeOfSceneryTest extends TestCase
{
    private const int USER = ShapesBFixture::USER;
    private const int PARTNER = ShapesBFixture::PARTNER;
    private const array BENCH = [106, 107, 108, 109, 110];

    /**
     * @param array<string, array{0: int|null, 1?: list<int>}> $partnerSpec
     */
    private static function snapshot(array $partnerSpec): LeagueSnapshot
    {
        $s = ShapesBFixture::snapshot();
        $s = ShapesBFixture::withStrengths($s, self::USER, ['PG' => [12, [201]], 'SG' => [12]]);

        return ShapesBFixture::withStrengths($s, self::PARTNER, $partnerSpec);
    }

    /**
     * @param list<int> $partnerPids
     * @return list<array{list<int>, list<int>, list<int>, list<int>}>
     */
    private static function candidates(LeagueSnapshot $s, array $partnerPids): array
    {
        return ShapesBFixture::flat(ShapesBFixture::collect(new ChangeOfScenery(), $s, new AssetPool([201], $partnerPids, [], [], [])));
    }

    /**
     * @param int|null $pid
     * @return list<array{list<int>, list<int>, list<int>, list<int>}>
     */
    private static function one(?int $pid): array
    {
        return $pid === null ? [] : [[[201], [], [$pid], []]];
    }

    public function testBenchOfTheWeakestSlotIsTheTarget(): void
    {
        $s = self::snapshot(['PG' => [22], 'SG' => [25], 'SF' => [23], 'PF' => [21], 'C' => [24]]);

        self::assertSame(self::one(107), self::candidates($s, self::BENCH));
    }

    public function testTieOnWeakestRankKeepsTheFirstSlot(): void
    {
        $s = self::snapshot(['PG' => [25], 'SG' => [25], 'SF' => [5], 'PF' => [5], 'C' => [5]]);

        self::assertSame(self::one(106), self::candidates($s, self::BENCH));
    }

    public function testSlotsAfterAStrongerSlotAreStillConsidered(): void
    {
        $s = self::snapshot(['PG' => [21], 'SG' => [5], 'SF' => [25], 'PF' => [5], 'C' => [5]]);

        self::assertSame(self::one(108), self::candidates($s, self::BENCH));
    }

    public function testSlotWithoutAStarterRankCountsAsRankTwentyNine(): void
    {
        $nullAfterTwentyEight = self::snapshot(['PG' => [28], 'SG' => [null], 'SF' => [5], 'PF' => [5], 'C' => [5]]);
        self::assertSame(self::one(107), self::candidates($nullAfterTwentyEight, self::BENCH));

        $twentyNineBeforeNull = self::snapshot(['PG' => [29], 'SG' => [null], 'SF' => [5], 'PF' => [5], 'C' => [5]]);
        self::assertSame(self::one(106), self::candidates($twentyNineBeforeNull, self::BENCH));
    }

    public function testOnlyBenchPlayersInThePoolAreCandidates(): void
    {
        $s = self::snapshot(['PG' => [22], 'SG' => [25], 'SF' => [23], 'PF' => [21], 'C' => [24]]);

        self::assertSame(self::one(108), self::candidates($s, [106, 108]));
        self::assertSame([], self::candidates($s, []));
    }

    public function testFirstBenchPlayerInThePoolWinsWithinASlot(): void
    {
        $s = ShapesBFixture::withRotation(
            self::snapshot(['PG' => [25], 'SG' => [5], 'SF' => [5], 'PF' => [5], 'C' => [5]]),
            self::PARTNER,
            ['PG' => [106, 111], 'SG' => [107], 'SF' => [108], 'PF' => [109], 'C' => [110]],
        );

        self::assertSame(self::one(106), self::candidates($s, [106, 111]));
    }

    public function testWeakestBenchReadsTheOffsetZeroLineup(): void
    {
        $s = self::snapshot(['PG' => [22], 'SG' => [25], 'SF' => [23], 'PF' => [21], 'C' => [24]]);
        $lineups = $s->lineups;
        unset($lineups[1][self::PARTNER]);
        $s = ShapesBFixture::with($s, ['lineups' => $lineups]);

        self::assertSame(self::one(107), self::candidates($s, self::BENCH));
    }

    public function testMissingPartnerLineupLeavesOnlyBuriedTargets(): void
    {
        $s = self::snapshot(['PG' => [25], 'SG' => [25, [111]]]);
        $lineups = $s->lineups;
        unset($lineups[0][self::PARTNER]);
        $s = ShapesBFixture::with($s, ['lineups' => $lineups]);

        self::assertSame(self::one(111), self::candidates($s, [111]));
    }

    public function testBuriedPlayersOnBothSidesPairAcrossDifferentSlots(): void
    {
        $s = ShapesBFixture::snapshot();
        $s = ShapesBFixture::withStrengths($s, self::USER, ['PG' => [12, [201, 202]], 'SG' => [12, [203]]]);
        $s = ShapesBFixture::withStrengths($s, self::PARTNER, ['PG' => [25, [111]], 'SG' => [25, [112, 113]]]);

        $flat = ShapesBFixture::flat(ShapesBFixture::collect(new ChangeOfScenery(), $s, new AssetPool([201, 202, 203], [111, 112, 113], [], [], [])));

        self::assertSame([
            [[201], [], [112], []],
            [[201], [], [113], []],
            [[202], [], [112], []],
            [[202], [], [113], []],
            [[203], [], [111], []],
        ], $flat);
    }

    public function testBuriedTargetAndWeakestBenchAreBothYielded(): void
    {
        $s = self::snapshot(['PG' => [25], 'SG' => [25, [111]], 'SF' => [5], 'PF' => [5], 'C' => [5]]);

        self::assertSame(
            [[[201], [], [111], []], [[201], [], [106], []]],
            self::candidates($s, [106, 111]),
        );
    }

    public function testYieldsNothingWithoutABuriedUserPlayerAtAWeakPartnerSlot(): void
    {
        $s = self::snapshot(['PG' => [5], 'SG' => [25], 'SF' => [25], 'PF' => [25], 'C' => [25]]);

        self::assertSame([], self::candidates($s, self::BENCH));
    }
}
