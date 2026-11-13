<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\PairRecordMemo;
use TradeSuggestions\Engine\SlotNetTallies;
use TradeSuggestions\Engine\SlotRankState;
use TradeSuggestions\Engine\SnapshotCodec;
use TradeSuggestions\SlotMapper;

/**
 * `SlotRankState::withLineups` on tallies that are not what a full pairwise
 * pass would give, so the stored nets (not a recount) must drive the result.
 */
final class SlotRankStateEdgeTest extends TestCase
{
    private static ?LeagueSnapshot $league = null;

    private static function league(): LeagueSnapshot
    {
        return self::$league ??= SnapshotFactory::league(4);
    }

    public function testStarterWithoutRatingsIsLeftOutOfEveryGroup(): void
    {
        $s = self::league();
        $starter = $s->lineups[0][1]->starterPid('PG');
        self::assertNotNull($starter);

        $data = $s->slotRanks[0]->tallies()->toArray();
        foreach (['pool', 'starters', 'bench'] as $group) {
            foreach ($data[$group] as $slot => $nets) {
                unset($data[$group][$slot][$starter]);
            }
        }
        $before = SlotRankState::fromTallies(SlotNetTallies::fromArray($data), $s->lineups[0]);
        $ratings = SnapshotCodec::ratingsAt($s->projections, 0);
        unset($ratings[$starter]);

        $after = self::withoutWarnings(static fn (): SlotRankState => $before->withLineups([], $ratings, new PairRecordMemo()));

        self::assertNull($after->poolRank('PG', $starter));
        self::assertNull($after->starterRank('PG', 1));
        self::assertSame($before->poolRanks(), $after->poolRanks());
        foreach (SlotMapper::SLOTS as $slot) {
            foreach (array_keys($s->lineups[0]) as $tid) {
                self::assertSame($before->starterRank($slot, $tid), $after->starterRank($slot, $tid), "starter {$slot} {$tid}");
                self::assertSame($before->bestBenchRank($slot, $tid), $after->bestBenchRank($slot, $tid), "bench {$slot} {$tid}");
            }
        }
    }

    public function testStoredStarterAndBenchNetsAreKeptWhenMembershipIsUnchanged(): void
    {
        $s = self::league();
        $data = $s->slotRanks[0]->tallies()->toArray();
        foreach (['starters', 'bench'] as $group) {
            foreach ($data[$group] as $slot => $nets) {
                foreach (SlotRankState::rankByNet($nets) as $pid => $rank) {
                    $data[$group][$slot][$pid] = $rank;
                }
            }
        }
        $inverted = SlotNetTallies::fromArray($data);
        $before = SlotRankState::fromTallies($inverted, $s->lineups[0]);
        $real = $s->slotRanks[0];
        $differs = false;
        foreach (SlotMapper::SLOTS as $slot) {
            foreach (array_keys($s->lineups[0]) as $tid) {
                $differs = $differs
                    || $before->starterRank($slot, $tid) !== $real->starterRank($slot, $tid)
                    || $before->bestBenchRank($slot, $tid) !== $real->bestBenchRank($slot, $tid);
            }
        }
        self::assertTrue($differs, 'guard: the inverted nets rank differently from the real ones');

        $after = $before->withLineups([], SnapshotCodec::ratingsAt($s->projections, 0), new PairRecordMemo());

        self::assertSame($data['starters'], $after->tallies()->toArray()['starters']);
        self::assertSame($data['bench'], $after->tallies()->toArray()['bench']);
        foreach (SlotMapper::SLOTS as $slot) {
            foreach (array_keys($s->lineups[0]) as $tid) {
                self::assertSame($before->starterRank($slot, $tid), $after->starterRank($slot, $tid), "starter {$slot} {$tid}");
                self::assertSame($before->bestBenchRank($slot, $tid), $after->bestBenchRank($slot, $tid), "bench {$slot} {$tid}");
            }
        }
    }

    /**
     * @template T
     * @param callable(): T $run
     * @return T
     */
    private static function withoutWarnings(callable $run): mixed
    {
        set_error_handler(static function (int $no, string $message): never {
            throw new \ErrorException($message, 0, $no);
        });
        try {
            return $run();
        } finally {
            restore_error_handler();
        }
    }
}
