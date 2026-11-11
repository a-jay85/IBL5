<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\Projection\Horizon;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Valuation\GainWeights;
use TradeSuggestions\Valuation\ResignLikelihood;

/**
 * Scores both sides of a candidate as weighted rank places per season. Pure:
 * the same snapshot and candidate give the same result. It never sorts or
 * filters; ranking belongs to the caller.
 */
final class DealScorer
{
    /** The components that are mixed over a moved walker's two outcomes. */
    public const array MIXED_KEYS = ['starter_slots', 'bench_slots', 'profile_dimensions', 'balance'];

    public static function score(LeagueSnapshot $s, TradeCandidate $c, string $shapeId): ScoredDeal
    {
        return self::scoreWith($s, $c, $shapeId, new PairRecordMemo());
    }

    /**
     * @param array<string, string> $shapeIdByKey candidate key => shape id
     * @param PairRecordMemo|null $memo shared pair memo; the timing command passes one to read its size
     * @return list<ScoredDeal>
     */
    public static function scoreAll(LeagueSnapshot $s, GenerationResult $g, array $shapeIdByKey, ?PairRecordMemo $memo = null): array
    {
        $memo ??= new PairRecordMemo();
        $out = [];
        foreach ($g->candidates as $c) {
            $key = CandidateKey::of($c);
            if (!isset($shapeIdByKey[$key])) {
                throw new \OutOfBoundsException("No shape id for candidate {$key}");
            }
            $out[] = self::scoreWith($s, $c, $shapeIdByKey[$key], $memo);
        }

        return $out;
    }

    private static function scoreWith(LeagueSnapshot $s, TradeCandidate $c, string $shapeId, PairRecordMemo $memo): ScoredDeal
    {
        $components = [$c->userTid => [], $c->partnerTid => []];
        $deltas = [$c->userTid => [], $c->partnerTid => []];

        foreach (Horizon::OFFSETS as $offset) {
            $after = TeamStateRebuilder::after($s, $c, $offset, $memo);
            $sides = [
                $c->userTid => [$after['user'], $c->partnerPids, $c->userPids],
                $c->partnerTid => [$after['partner'], $c->userPids, $c->partnerPids],
            ];
            foreach ($sides as $tid => [$afterState, $received, $sent]) {
                $before = TeamStateRebuilder::before($s, $tid, $offset);
                $base = SideGain::fromStates($before, $afterState, $s, $c, $tid);
                if ($offset >= 1) {
                    $base = self::mixWalkers($s, $c, $tid, $offset, $memo, $base, $received, $sent);
                }
                $components[$tid][$offset] = $base;
                $deltas[$tid][$offset] = ProfileDelta::fromStates($before, $afterState);
            }
        }

        return new ScoredDeal(
            $c,
            $shapeId,
            self::side($s, $c->userTid, $components[$c->userTid]),
            self::side($s, $c->partnerTid, $components[$c->partnerTid]),
            $deltas[$c->userTid],
            $deltas[$c->partnerTid],
        );
    }

    /**
     * @param array<int, array<string, float>> $components
     */
    private static function side(LeagueSnapshot $s, int $tid, array $components): SideGain
    {
        $mode = $s->mode($tid);
        $rental = $mode === TeamMode::WinNow && isset($s->situations[$tid])
            ? GainWeights::rentalScale($s->situations[$tid]->gamesLeft)
            : 1.0;

        return new SideGain($tid, $mode, $components, GainWeights::seasonWeights($mode), $rental);
    }

    /**
     * Mixes the slot, bench, profile and balance components over every
     * outcome of the moved walkers on this side. A received walker who leaves
     * drops out of the after roster; a sent walker who would have left drops
     * out of the before roster. Each outcome is weighted by the product of
     * the walkers' factors. With no moved walker the base components return
     * unchanged.
     *
     * @param array<string, float> $base
     * @param list<int> $received
     * @param list<int> $sent
     * @return array<string, float>
     */
    private static function mixWalkers(LeagueSnapshot $s, TradeCandidate $c, int $tid, int $offset, PairRecordMemo $memo, array $base, array $received, array $sent): array
    {
        $walkers = [];
        foreach ([[$received, true], [$sent, false]] as [$pids, $isReceived]) {
            foreach ($pids as $pid) {
                if (($s->likelyToWalk[$pid] ?? false) && $s->players[$pid]->onRosterAt($offset)) {
                    $walkers[] = [$pid, $isReceived, ResignLikelihood::futureValueFactor($offset, true, true, $s->resignLikelihood[$pid] ?? 1.0)];
                }
            }
        }
        if ($walkers === []) {
            return $base;
        }

        $mixed = array_fill_keys(self::MIXED_KEYS, 0.0);
        $count = count($walkers);
        for ($mask = 0; $mask < (1 << $count); $mask++) {
            $weight = 1.0;
            $goneAfter = [];
            $goneBefore = [];
            foreach ($walkers as $i => [$pid, $isReceived, $f]) {
                $stays = ($mask & (1 << $i)) === 0;
                $weight *= $stays ? $f : 1.0 - $f;
                if (!$stays) {
                    if ($isReceived) {
                        $goneAfter[] = $pid;
                    } else {
                        $goneBefore[] = $pid;
                    }
                }
            }
            if ($weight === 0.0) {
                continue;
            }
            $before = $goneBefore === []
                ? TeamStateRebuilder::before($s, $tid, $offset)
                : TeamStateRebuilder::beforeWithout($s, $tid, $offset, $memo, $goneBefore);
            $afterPair = TeamStateRebuilder::after($s, $c, $offset, $memo, $goneAfter);
            $after = $tid === $c->userTid ? $afterPair['user'] : $afterPair['partner'];
            $outcome = SideGain::fromStates($before, $after, $s, $c, $tid);
            foreach (self::MIXED_KEYS as $key) {
                $mixed[$key] += $weight * $outcome[$key];
            }
        }

        return array_replace($base, $mixed);
    }
}
