<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\LeagueSnapshotBuilder;
use TradeSuggestions\Engine\PairRecordMemo;
use TradeSuggestions\Engine\SnapshotCodec;
use TradeSuggestions\Engine\TeamStateRebuilder;
use TradeSuggestions\Profile\LeagueProfiles;
use TradeSuggestions\Profile\PositionStrength;
use TradeSuggestions\Profile\SlotRankTable;
use TradeSuggestions\Profile\TeamProfile;
use TradeSuggestions\SlotMapper;
use TradeSuggestions\TradeCandidate;

final class TeamStateRebuilderTest extends TestCase
{
    private static ?LeagueSnapshot $league = null;

    private static function league(): LeagueSnapshot
    {
        return self::$league ??= SnapshotFactory::league();
    }

    public function testBeforeMatchesSnapshotRanks(): void
    {
        $s = self::league();
        foreach ($s->teamIds as $tid) {
            $before = TeamStateRebuilder::before($s, $tid, 0);
            foreach (SlotMapper::SLOTS as $slot) {
                self::assertSame($s->slotRanks[0]->starterRank($slot, $tid), $before->starterRank[$slot]);
                self::assertSame($s->slotRanks[0]->bestBenchRank($slot, $tid), $before->bestBenchRank[$slot]);
            }
            self::assertSame($s->payrollByOffset[0][$tid], $before->payroll);
            self::assertSame(count($s->lineups[0][$tid]->players), $before->rosterCount);
        }
    }

    public function testAfterSwapMatchesFullRebuild(): void
    {
        $s = self::league();
        $memo = new PairRecordMemo();
        foreach (self::seededSwaps($s, 20) as $c) {
            foreach (array_keys($s->slotRanks) as $offset) {
                $after = TeamStateRebuilder::after($s, $c, $offset, $memo);
                $lineups = array_replace($s->lineups[$offset], [
                    $c->userTid => $after['user']->lineup,
                    $c->partnerTid => $after['partner']->lineup,
                ]);
                $table = SlotRankTable::build($lineups, SnapshotCodec::ratingsAt($s->projections, $offset));
                foreach ($after as $state) {
                    foreach (SlotMapper::SLOTS as $slot) {
                        self::assertSame($table->starterRank($slot, $state->tid), $state->starterRank[$slot], "starter {$offset} {$slot} {$state->tid}");
                        self::assertSame($table->bestBenchRank($slot, $state->tid), $state->bestBenchRank[$slot], "bench {$offset} {$slot} {$state->tid}");
                    }
                    self::assertEquals(PositionStrength::build($state->tid, $state->lineup, $table), $state->positionStrength);
                }
            }
        }
    }

    public function testAfterReRankAgreesWithLeagueProfilesBuild(): void
    {
        $s = self::league();
        $c = self::seededSwaps($s, 1)[0];
        $offset = 1;
        $after = TeamStateRebuilder::after($s, $c, $offset, new PairRecordMemo());

        $ratings = SnapshotCodec::ratingsAt($s->projections, $offset);
        $per36 = [];
        foreach ($s->projections as $pid => $seasons) {
            if ($seasons[$offset]->onRoster) {
                $per36[$pid] = $seasons[$offset]->per36;
            }
        }
        $values = [];
        foreach ($s->profiles[$offset] as $tid => $profile) {
            $values[$tid] = $profile->values;
        }
        $values[$c->userTid] = TeamProfile::valuesFor($after['user']->lineup, $ratings, $per36);
        $values[$c->partnerTid] = TeamProfile::valuesFor($after['partner']->lineup, $ratings, $per36);
        $built = LeagueProfiles::build($values, $offset);

        self::assertEquals($built[$c->userTid], $after['user']->profile);
        self::assertEquals($built[$c->partnerTid], $after['partner']->profile);
        self::assertSame($built[$c->userTid]->balance(), $after['user']->profile->balance());
    }

    public function testReceivedPlayerOffBooksAtOffsetIsNotOnRoster(): void
    {
        $s = self::league();
        $received = null;
        foreach (array_keys($s->lineups[0][2]->players) as $pid) {
            if ($s->players[$pid]->salaryAt(2) === 0) {
                $received = $pid;
                break;
            }
        }
        self::assertNotNull($received, 'fixture has a team-2 player whose deal ends before offset 2');
        $c = new TradeCandidate(1, 2, [], [], [$received], []);

        $memo = new PairRecordMemo();
        $atZero = TeamStateRebuilder::after($s, $c, 0, $memo);
        $atTwo = TeamStateRebuilder::after($s, $c, 2, $memo);

        self::assertArrayHasKey($received, $atZero['user']->lineup->players);
        self::assertArrayNotHasKey($received, $atTwo['user']->lineup->players);
        self::assertArrayNotHasKey($received, $atTwo['partner']->lineup->players);
    }

    public function testEmptyRosterSideAfterDealHasNullStarters(): void
    {
        $only = ProjectionFactory::player(pid: 201, tid: 2, pos: 'C', dcMinutes: 30);
        $s = LeagueSnapshotBuilder::build(SnapshotFactory::inputs(28, 13, [2 => [$only]]));
        $c = new TradeCandidate(1, 2, [], [], [201], []);

        $after = TeamStateRebuilder::after($s, $c, 0, new PairRecordMemo());

        self::assertSame(0, $after['partner']->rosterCount);
        foreach (SlotMapper::SLOTS as $slot) {
            self::assertNull($after['partner']->starterRank[$slot]);
        }
        self::assertArrayHasKey(201, $after['user']->lineup->players);
    }

    public function testBeforeWithoutDropsTheAbsentPlayer(): void
    {
        $s = self::league();
        $starter = $s->lineups[1][1]->starterPid('PG');
        self::assertNotNull($starter);

        $without = TeamStateRebuilder::beforeWithout($s, 1, 1, new PairRecordMemo(), [$starter]);

        self::assertArrayNotHasKey($starter, $without->lineup->players);
        self::assertSame(count($s->lineups[1][1]->players) - 1, $without->rosterCount);
        self::assertLessThan($s->payrollByOffset[1][1], $without->payroll);
    }

    public function testRotationChangeWithoutMovedPlayerIsRanked(): void
    {
        $s = self::league();
        $checked = 0;
        foreach (self::seededSwaps($s, 20) as $c) {
            $moved = array_merge($c->userPids, $c->partnerPids);
            foreach (array_keys($s->slotRanks) as $offset) {
                [$state, $table, $changed] = self::postDealRanks($s, $c, $offset);
                foreach ($changed as $lineup) {
                    foreach (SlotMapper::SLOTS as $slot) {
                        foreach ($lineup->rotation[$slot] ?? [] as $pid) {
                            if (in_array($pid, $moved, true)) {
                                continue;
                            }
                            $checked++;
                            self::assertNotNull($state->poolRank($slot, $pid), "pid {$pid} {$slot} offset {$offset}");
                            self::assertSame($table->poolRank($slot, $pid), $state->poolRank($slot, $pid), "pool {$pid} {$slot} offset {$offset}");
                        }
                    }
                }
            }
        }

        self::assertGreaterThan(0, $checked);
    }

    public function testUntouchedTeamBestBenchFlipIsUpdated(): void
    {
        $s = self::league();
        $checked = 0;
        foreach (self::seededSwaps($s, 20) as $c) {
            foreach (array_keys($s->slotRanks) as $offset) {
                [$state, $table] = self::postDealRanks($s, $c, $offset);
                foreach ($s->teamIds as $tid) {
                    if ($tid === $c->userTid || $tid === $c->partnerTid) {
                        continue;
                    }
                    foreach (SlotMapper::SLOTS as $slot) {
                        $checked++;
                        self::assertSame($table->bestBenchRank($slot, $tid), $state->bestBenchRank($slot, $tid), "bench {$offset} {$slot} {$tid}");
                        self::assertSame($table->starterRank($slot, $tid), $state->starterRank($slot, $tid), "starter {$offset} {$slot} {$tid}");
                    }
                }
            }
        }

        self::assertGreaterThan(0, $checked);
    }

    /**
     * @return array{0: \TradeSuggestions\Engine\SlotRankState, 1: SlotRankTable, 2: array<int, \TradeSuggestions\Profile\SeasonLineup>}
     */
    private static function postDealRanks(LeagueSnapshot $s, TradeCandidate $c, int $offset): array
    {
        $after = TeamStateRebuilder::after($s, $c, $offset, new PairRecordMemo());
        $changed = [$c->userTid => $after['user']->lineup, $c->partnerTid => $after['partner']->lineup];
        $ratings = SnapshotCodec::ratingsAt($s->projections, $offset);
        $state = $s->slotRanks[$offset]->withLineups($changed, $ratings, new PairRecordMemo());
        $table = SlotRankTable::build(array_replace($s->lineups[$offset], $changed), $ratings);

        return [$state, $table, $changed];
    }

    /**
     * Seeded one-for-one swaps between real teams, both players on the books
     * now.
     *
     * @return list<TradeCandidate>
     */
    private static function seededSwaps(LeagueSnapshot $s, int $n): array
    {
        mt_srand(11);
        $out = [];
        while (count($out) < $n) {
            $a = $s->teamIds[mt_rand(0, count($s->teamIds) - 1)];
            $b = $s->teamIds[mt_rand(0, count($s->teamIds) - 1)];
            if ($a === $b) {
                continue;
            }
            $pidsA = array_keys($s->lineups[0][$a]->players);
            $pidsB = array_keys($s->lineups[0][$b]->players);
            $out[] = new TradeCandidate($a, $b, [$pidsA[mt_rand(0, count($pidsA) - 1)]], [], [$pidsB[mt_rand(0, count($pidsB) - 1)]], []);
        }

        return $out;
    }
}
