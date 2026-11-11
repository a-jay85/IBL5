<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\SnapshotCodec;
use TradeSuggestions\SlotMapper;
use TradeSuggestions\Valuation\ContractValue;
use TradeSuggestions\Valuation\FairSalaryTable;

final class LeagueSnapshotTest extends TestCase
{
    private static ?LeagueSnapshot $league = null;

    private static function league(): LeagueSnapshot
    {
        return self::$league ??= SnapshotFactory::league();
    }

    public function testModeAndLineupReadersThrowOnUnknownTeam(): void
    {
        $s = self::league();
        try {
            $s->mode(99);
            self::fail('mode(99) did not throw');
        } catch (\OutOfRangeException) {
        }
        $this->expectException(\OutOfRangeException::class);
        $s->lineup(0, 99);
    }

    public function testTeamIdsAreTheRealFranchisesOnly(): void
    {
        $s = self::league();

        self::assertSame(range(1, 28), $s->teamIds);
        self::assertNotContains(SnapshotFactory::NON_FRANCHISE_TID, $s->teamIds);
        self::assertArrayNotHasKey(SnapshotFactory::NON_FRANCHISE_PID, $s->players);
    }

    public function testEncodeProducesScalarArraysOnly(): void
    {
        $encoded = SnapshotCodec::encode(self::league());

        $walk = static function (mixed $node) use (&$walk): void {
            if (is_array($node)) {
                foreach ($node as $child) {
                    $walk($child);
                }

                return;
            }
            self::assertTrue(
                $node === null || is_int($node) || is_float($node) || is_string($node) || is_bool($node),
                'Encoded payload holds a non-scalar: ' . get_debug_type($node),
            );
        };
        $walk($encoded);
        self::assertSame(SnapshotCodec::VERSION, $encoded['v']);
    }

    public function testDecodeOfEncodeIsEqualSnapshot(): void
    {
        $s = self::league();
        $json = json_encode(SnapshotCodec::encode($s), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        $raw = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($raw);
        $decoded = SnapshotCodec::decode($raw);

        self::assertEquals($s, $decoded);
        self::assertNotSame([], $decoded->picks);
        self::assertSame($s->slotRanks[0]->starterRank('PG', 1), $decoded->slotRanks[0]->starterRank('PG', 1));
        self::assertSame($s->fairSalary[0]->fairSalary('C', 1), $decoded->fairSalary[0]->fairSalary('C', 1));
        self::assertSame($s->players[101]->ratings->toArray(), $decoded->players[101]->ratings->toArray());
        self::assertEquals($s->players[101]->production, $decoded->players[101]->production);
        self::assertSame($s->resignLikelihood[101] ?? null, $decoded->resignLikelihood[101] ?? null);
    }

    public function testDecodeRanksComeFromStoredTallies(): void
    {
        $s = self::league();
        $ranks = $s->slotRanks[0]->poolRanks()['PG'];
        asort($ranks);
        $pids = array_keys($ranks);
        [$first, $second] = [$pids[0], $pids[1]];

        $encoded = SnapshotCodec::encode($s);
        self::assertIsArray($encoded['tallies']);
        self::assertIsArray($encoded['tallies'][0]);
        self::assertIsArray($encoded['tallies'][0]['pool']);
        self::assertIsArray($encoded['tallies'][0]['pool']['PG']);
        $pool = $encoded['tallies'][0]['pool']['PG'];
        [$pool[$first], $pool[$second]] = [$pool[$second], $pool[$first]];
        $encoded['tallies'][0]['pool']['PG'] = $pool;

        $decoded = SnapshotCodec::decode($encoded);

        self::assertSame($ranks[$second], $decoded->slotRanks[0]->poolRank('PG', $first));
        self::assertSame($ranks[$first], $decoded->slotRanks[0]->poolRank('PG', $second));
    }

    public function testDecodeRejectsWrongVersion(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        SnapshotCodec::decode(['v' => 0]);
    }

    public function testDecodeRejectsMissingVersion(): void
    {
        $encoded = SnapshotCodec::encode(self::league());
        unset($encoded['v']);

        $this->expectException(\UnexpectedValueException::class);
        SnapshotCodec::decode($encoded);
    }

    public function testSurplusBySeasonUsesFairSalaryAtPoolRankTier(): void
    {
        $s = self::league();
        $checked = 0;
        foreach (array_keys($s->players) as $pid) {
            $surplus = $s->surplusBySeason($pid);
            foreach ($s->slotRanks as $offset => $state) {
                $bestSlot = null;
                $bestRank = null;
                foreach (SlotMapper::SLOTS as $slot) {
                    $rank = $state->poolRank($slot, $pid);
                    if ($rank !== null && ($bestRank === null || $rank < $bestRank)) {
                        [$bestSlot, $bestRank] = [$slot, $rank];
                    }
                }
                if ($bestSlot === null || $bestRank === null) {
                    self::assertSame(0, $surplus[$offset], "pid {$pid} offset {$offset} has no pool rank");
                    continue;
                }
                $season = $s->projections[$pid][$offset];
                $fair = $s->fairSalary[$offset]->fairSalary($bestSlot, FairSalaryTable::tierFor($bestRank));
                $expected = ContractValue::surplusBySeason([$offset => $season->salary], [$offset => $fair])[$offset];
                self::assertSame($expected, $surplus[$offset], "pid {$pid} offset {$offset}");
                $checked++;
            }
        }
        self::assertGreaterThan(100, $checked);
    }
}
