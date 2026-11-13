<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine\Shape;

use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\Shape\Contracts\DealShapeInterface;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;

/**
 * Fires when the giver is Rebuild, the partner is WinNow, and the giver holds
 * a starter with `starterRank <= SlotStrength::STRONG_MAX_RANK` at offset 0
 * (Phase 4 docblock). Yields the user's strong-slot starter for the partner's
 * youngest pool player (age <= 25) plus 1 partner pick; fires only for a
 * Rebuild user against a WinNow partner and the mirror. A Middle side never
 * fires it.
 */
final class StarForYoungAndPicks implements DealShapeInterface
{
    private const int MAX_YOUNG_AGE = 25;

    public function id(): string
    {
        return 'STAR_FOR_YOUNG_AND_PICKS';
    }

    public function generate(LeagueSnapshot $s, int $userTid, int $partnerTid, AssetPool $pool): iterable
    {
        $userMode = $s->mode($userTid);
        $partnerMode = $s->mode($partnerTid);

        if ($userMode === TeamMode::Rebuild && $partnerMode === TeamMode::WinNow) {
            $young = $this->youngest($s, $pool->partnerPids);
            $pick = $pool->partnerPicks[0] ?? null;
            if ($young === null || $pick === null) {
                return;
            }
            foreach ($this->strongStarters($s, $userTid, $pool->userPids) as $star) {
                yield new TradeCandidate($userTid, $partnerTid, [$star], [], [$young], [$pick->pickId]);
            }
        } elseif ($userMode === TeamMode::WinNow && $partnerMode === TeamMode::Rebuild) {
            $young = $this->youngest($s, $pool->userPids);
            $pick = $pool->userPicks[0] ?? null;
            if ($young === null || $pick === null) {
                return;
            }
            foreach ($this->strongStarters($s, $partnerTid, $pool->partnerPids) as $star) {
                yield new TradeCandidate($userTid, $partnerTid, [$young], [$pick->pickId], [$star], []);
            }
        }
    }

    /**
     * @param list<int> $pids
     * @return list<int>
     */
    private function strongStarters(LeagueSnapshot $s, int $tid, array $pids): array
    {
        $stars = [];
        foreach ($s->positionStrength[0][$tid] ?? [] as $slot => $strength) {
            if (!$strength->isStrong()) {
                continue;
            }
            $starter = ($s->lineups[0][$tid] ?? null)?->starterPid($slot);
            if ($starter !== null && in_array($starter, $pids, true) && !in_array($starter, $stars, true)) {
                $stars[] = $starter;
            }
        }

        return $stars;
    }

    /**
     * @param list<int> $pids
     */
    private function youngest(LeagueSnapshot $s, array $pids): ?int
    {
        $best = null;
        $bestAge = self::MAX_YOUNG_AGE + 1;
        foreach ($pids as $pid) {
            $age = ($s->players[$pid] ?? null)?->age;
            if ($age === null || $age > self::MAX_YOUNG_AGE) {
                continue;
            }
            if ($age < $bestAge || ($age === $bestAge && $best !== null && $pid < $best)) {
                $best = $pid;
                $bestAge = $age;
            }
        }

        return $best;
    }
}
