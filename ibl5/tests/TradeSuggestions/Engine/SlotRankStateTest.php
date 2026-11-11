<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Engine\AfterPositionStrength;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\PairRecordMemo;
use TradeSuggestions\Engine\SnapshotCodec;
use TradeSuggestions\Profile\PositionStrength;
use TradeSuggestions\Profile\SlotRankTable;
use TradeSuggestions\SlotMapper;

final class SlotRankStateTest extends TestCase
{
    private static ?LeagueSnapshot $league = null;

    private static function league(): LeagueSnapshot
    {
        return self::$league ??= SnapshotFactory::league();
    }

    public function testSnapshotRanksMatchSlotRankTableBuild(): void
    {
        $s = self::league();
        $tieChecked = false;
        foreach ($s->slotRanks as $offset => $state) {
            $table = SlotRankTable::build($s->lineups[$offset], SnapshotCodec::ratingsAt($s->projections, $offset));
            foreach (SlotMapper::SLOTS as $slot) {
                foreach (array_keys($s->players) as $pid) {
                    self::assertSame($table->poolRank($slot, $pid), $state->poolRank($slot, $pid), "pool {$offset} {$slot} {$pid}");
                }
                foreach ($s->teamIds as $tid) {
                    self::assertSame($table->starterRank($slot, $tid), $state->starterRank($slot, $tid), "starter {$offset} {$slot} {$tid}");
                    self::assertSame($table->bestBenchRank($slot, $tid), $state->bestBenchRank($slot, $tid), "bench {$offset} {$slot} {$tid}");
                }
                self::assertSame($table->worstStarterPoolRank($slot), $state->worstStarterPoolRank($slot));
                $a = $state->poolRank($slot, SnapshotFactory::TIE_PID_A);
                $b = $state->poolRank($slot, SnapshotFactory::TIE_PID_B);
                if ($a !== null && $b !== null && abs($a - $b) === 1) {
                    self::assertLessThan($b, $a, 'tie breaks by pid ascending');
                    $tieChecked = true;
                }
            }
        }
        self::assertTrue($tieChecked, 'the planted tie reached a shared pool');
    }

    public function testAfterPositionStrengthMatchesPositionStrength(): void
    {
        $s = self::league();
        foreach ($s->slotRanks as $offset => $state) {
            $table = SlotRankTable::build($s->lineups[$offset], SnapshotCodec::ratingsAt($s->projections, $offset));
            foreach ($s->lineups[$offset] as $tid => $lineup) {
                self::assertEquals(
                    PositionStrength::build($tid, $lineup, $table),
                    AfterPositionStrength::build($tid, $lineup, $state),
                    "offset {$offset} team {$tid}",
                );
            }
        }
    }

    public function testPairRecordMemoIsAntisymmetricAndCached(): void
    {
        $memo = new PairRecordMemo();
        $a = ProjectionFactory::ratingSet(['r_3gp' => 80]);
        $b = ProjectionFactory::ratingSet(['r_3gp' => 40, 'r_blk' => 70]);

        $ab = $memo->diff(0, 'SG', 5, $a, 9, $b);
        self::assertSame(1, $memo->size());
        $ba = $memo->diff(0, 'SG', 9, $b, 5, $a);

        self::assertSame(-$ab, $ba);
        self::assertSame(1, $memo->size());
        self::assertSame(0, $memo->diff(0, 'SG', 5, $a, 5, $a));
    }
}
