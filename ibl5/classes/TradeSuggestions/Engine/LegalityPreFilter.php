<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use Team\Team;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Valuation\CapRoomAsset;

/**
 * Cheap legality screen before scoring. It encodes two league rules and
 * nothing more (docs/LEAGUE_RULES.md § Trades): after the trade every team is
 * at or under 15 players (injured players count) and at or under the hard
 * cap. Picks move no salary and take no roster spot. `Trading\TradeValidator`
 * stays the final gate.
 *
 * An asset the side does not own fails closed, so a shape bug cannot slip
 * through.
 */
final class LegalityPreFilter
{
    public const string ROSTER = 'ROSTER';
    public const string CAP = 'CAP';
    public const string OWNERSHIP = 'OWNERSHIP';

    public static function passes(LeagueSnapshot $s, TradeCandidate $c): bool
    {
        return self::reason($s, $c) === null;
    }

    /**
     * Why the candidate fails, or null when it passes.
     */
    public static function reason(LeagueSnapshot $s, TradeCandidate $c): ?string
    {
        $sides = [
            [$c->userTid, $c->userPids, $c->userPickIds, $c->partnerPids],
            [$c->partnerTid, $c->partnerPids, $c->partnerPickIds, $c->userPids],
        ];

        foreach ($sides as [$tid, $sentPids, $sentPicks]) {
            $roster = self::roster($s, $tid);
            foreach ($sentPids as $pid) {
                if (!isset($roster[$pid])) {
                    return self::OWNERSHIP;
                }
            }
            foreach ($sentPicks as $pickId) {
                if (!isset($s->picks[$pickId]) || $s->picks[$pickId]->ownerTid !== $tid) {
                    return self::OWNERSHIP;
                }
            }
        }

        foreach ($sides as [$tid, $sentPids, , $receivedPids]) {
            $count = count(self::roster($s, $tid)) - count($sentPids) + count($receivedPids);
            if ($count > Team::ROSTER_SPOTS_MAX) {
                return self::ROSTER;
            }
        }

        foreach ($sides as [$tid, $sentPids, , $receivedPids]) {
            $payroll = ($s->payrollByOffset[0][$tid] ?? 0)
                - self::salaryNow($s, $sentPids)
                + self::salaryNow($s, $receivedPids);
            if (CapRoomAsset::breachesHardCapNow($payroll)) {
                return self::CAP;
            }
        }

        return null;
    }

    /**
     * @return array<int, \TradeSuggestions\Projection\ProjectionPlayer>
     */
    private static function roster(LeagueSnapshot $s, int $tid): array
    {
        return isset($s->lineups[0][$tid]) ? $s->lineups[0][$tid]->players : [];
    }

    /**
     * @param list<int> $pids
     */
    private static function salaryNow(LeagueSnapshot $s, array $pids): int
    {
        $total = 0;
        foreach ($pids as $pid) {
            $total += $s->players[$pid]->salaryAt(0);
        }

        return $total;
    }
}
