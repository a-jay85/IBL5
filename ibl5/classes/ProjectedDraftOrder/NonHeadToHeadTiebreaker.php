<?php

declare(strict_types=1);

namespace ProjectedDraftOrder;

use ProjectedDraftOrder\Contracts\ProjectedDraftOrderRepositoryInterface;

/**
 * Non-head-to-head tiebreaker logic shared by draft order and playoff seeding.
 *
 * @phpstan-import-type StandingsRow from ProjectedDraftOrderRepositoryInterface
 */
class NonHeadToHeadTiebreaker
{
    /**
     * Apply non-H2H tiebreakers: division winner, division record, conference record,
     * point differential, alphabetical.
     *
     * Returns negative if A is the better team, positive if B is better.
     *
     * @param StandingsRow $a
     * @param StandingsRow $b
     * @param array<int, float> $pointDiffs
     */
    public function applyNonH2HTiebreakers(array $a, array $b, array $pointDiffs): int
    {
        // 2. Division winner status
        $aDivWinner = ($a['clinched_division'] ?? 0) === 1;
        $bDivWinner = ($b['clinched_division'] ?? 0) === 1;
        if ($aDivWinner !== $bDivWinner) {
            return $aDivWinner ? -1 : 1;
        }

        // 3. Division record (same-division teams only)
        if ($a['division'] === $b['division']) {
            $aDivPct = $this->safeWinPct($a['div_wins'], $a['div_losses']);
            $bDivPct = $this->safeWinPct($b['div_wins'], $b['div_losses']);
            $divDiff = $bDivPct <=> $aDivPct;
            if ($divDiff !== 0) {
                return $divDiff;
            }
        }

        // 4. Conference record
        $aConfPct = $this->safeWinPct($a['conf_wins'], $a['conf_losses']);
        $bConfPct = $this->safeWinPct($b['conf_wins'], $b['conf_losses']);
        $confDiff = $bConfPct <=> $aConfPct;
        if ($confDiff !== 0) {
            return $confDiff;
        }

        // 5. Point differential
        $aNetPts = $pointDiffs[$a['teamid']] ?? 0.0;
        $bNetPts = $pointDiffs[$b['teamid']] ?? 0.0;
        $ptsDiff = $bNetPts <=> $aNetPts;
        if ($ptsDiff !== 0) {
            return $ptsDiff;
        }

        // Fallback: alphabetical by team name (for deterministic ordering)
        return $a['team_name'] <=> $b['team_name'];
    }

    private function safeWinPct(?int $wins, ?int $losses): float
    {
        $w = $wins ?? 0;
        $l = $losses ?? 0;
        $total = $w + $l;
        if ($total === 0) {
            return 0.0;
        }
        return $w / $total;
    }
}
