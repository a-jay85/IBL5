<?php

declare(strict_types=1);

namespace Standings;

final class AggregateTiebreaker
{
    /**
     * @param list<int> $teamIds
     * @param callable(int, int): array{wins: int, losses: int} $pairRecord
     * @return array<int, float>
     */
    public static function computeAggregateH2HPcts(array $teamIds, callable $pairRecord): array
    {
        $pcts = [];
        foreach ($teamIds as $tid) {
            $totalWins = 0;
            $totalLosses = 0;
            foreach ($teamIds as $oppTid) {
                if ($oppTid === $tid) {
                    continue;
                }
                $record = $pairRecord($tid, $oppTid);
                $totalWins += $record['wins'];
                $totalLosses += $record['losses'];
            }
            $pcts[$tid] = self::safeWinPct($totalWins, $totalLosses);
        }
        return $pcts;
    }

    public static function safeWinPct(int $wins, int $losses): float
    {
        $total = $wins + $losses;
        if ($total === 0) {
            return 0.0;
        }
        return $wins / $total;
    }
}
