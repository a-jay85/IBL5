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
        $components = [];
        $deltas = [];

        foreach (Horizon::OFFSETS as $offset) {
            $after = TeamStateRebuilder::after($s, $c, $offset, $memo);
            $sides = [
                $c->userTid => [$after['user'], $c->partnerPids, $c->userPids],
                $c->partnerTid => [$after['partner'], $c->userPids, $c->partnerPids],
            ];
            foreach ($sides as $tid => [$afterState, $received, $sent]) {
                $before = TeamStateRebuilder::before($s, $tid, $offset);
                $odds = self::receivedOdds($s, $tid, $received);
                $walkFactor = self::receivedFactors($s, $offset, $odds);
                $base = SideGain::fromStates($before, $afterState, $s, $c, $tid, $walkFactor);
                if ($offset >= 1) {
                    $walkers = self::walkers($s, $offset, $received, $sent, $odds, $walkFactor);
                    if ($walkers !== []) {
                        $base = self::mixWalkers($s, $c, $tid, $offset, $memo, $base, $walkers, $walkFactor);
                    }
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
     * Re-sign odds for each received player against the receiving team (F10),
     * not against the team the snapshot scored him with. Players with no
     * offset-0 salary have no odds, as in the snapshot.
     *
     * @param list<int> $received
     * @return array<int, array{likelihood: float, walks: bool}>
     */
    private static function receivedOdds(LeagueSnapshot $s, int $tid, array $received): array
    {
        $odds = [];
        foreach ($received as $pid) {
            $p = $s->players[$pid];
            if ($p->salaryAt(0) <= 0) {
                continue;
            }
            $odds[$pid] = ResignOdds::forTeam($p, $tid, $s->lineups[0], $s->slotRanks[0], $s->teamFactors);
        }

        return $odds;
    }

    /**
     * @param array<int, array{likelihood: float, walks: bool}> $odds
     * @return array<int, float> received pid => F10 factor at $offset
     */
    private static function receivedFactors(LeagueSnapshot $s, int $offset, array $odds): array
    {
        $factors = [];
        foreach ($odds as $pid => $o) {
            $factors[$pid] = ResignLikelihood::futureValueFactor($offset, $s->players[$pid]->onRosterAt($offset), $o['walks'], $o['likelihood']);
        }

        return $factors;
    }

    /**
     * The moved walkers on this side at `$offset`, each as pid, whether this
     * side received him, and his factor.
     *
     * @param list<int> $received
     * @param list<int> $sent
     * @param array<int, array{likelihood: float, walks: bool}> $odds received pid => odds against the receiving team
     * @param array<int, float> $walkFactor received pid => factor at this offset
     * @return list<array{int, bool, float}>
     */
    private static function walkers(LeagueSnapshot $s, int $offset, array $received, array $sent, array $odds, array $walkFactor): array
    {
        $walkers = [];
        foreach ($received as $pid) {
            if (($odds[$pid]['walks'] ?? false) && $s->players[$pid]->onRosterAt($offset)) {
                $walkers[] = [$pid, true, $walkFactor[$pid]];
            }
        }
        foreach ($sent as $pid) {
            if (($s->likelyToWalk[$pid] ?? false) && $s->players[$pid]->onRosterAt($offset)) {
                $walkers[] = [$pid, false, ResignLikelihood::futureValueFactor($offset, true, true, $s->resignLikelihood[$pid] ?? 1.0)];
            }
        }

        return $walkers;
    }

    /**
     * Mixes the slot, bench, profile and balance components over every
     * outcome of the moved walkers on this side. A received walker who leaves
     * drops out of the after roster; a sent walker who would have left drops
     * out of the before roster. Each outcome is weighted by the product of
     * the walkers' factors. The caller skips this when no walker moved, so
     * the base components stand unchanged.
     *
     * @param array<string, float> $base
     * @param list<array{int, bool, float}> $walkers
     * @param array<int, float> $walkFactor received pid => factor at this offset
     * @return array<string, float>
     */
    private static function mixWalkers(LeagueSnapshot $s, TradeCandidate $c, int $tid, int $offset, PairRecordMemo $memo, array $base, array $walkers, array $walkFactor): array
    {
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
            $outcome = SideGain::fromStates($before, $after, $s, $c, $tid, $walkFactor);
            foreach (self::MIXED_KEYS as $key) {
                $mixed[$key] += $weight * $outcome[$key];
            }
        }

        return array_replace($base, $mixed);
    }
}
