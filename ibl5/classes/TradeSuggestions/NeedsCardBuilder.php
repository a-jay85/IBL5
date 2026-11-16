<?php

declare(strict_types=1);

namespace TradeSuggestions;

use League\League;

/**
 * Reads one team's needs from its payroll, depth chart and league starter
 * ranks. Pure; no I/O. Every threshold lives in NeedThresholds.
 *
 * Payroll now comes from TeamAssets::$capRoom, so cash considerations count.
 * Future payroll sums laterSalaries for the next two seasons; cash is left out there.
 */
final class NeedsCardBuilder
{
    public function build(TeamAssets $team, TeamDepth $depth, LeagueStarterRanks $ranks, TeamMode $mode, float $recordPct): NeedsCard
    {
        $payrollNow = $team->capRoom !== null
            ? League::HARD_CAP_MAX - $team->capRoom
            : array_sum(array_map(static fn (SuggestionPlayer $p): int => $p->currentSalary, $team->players));
        $futurePayroll = [];
        foreach ([1, 2] as $offset) {
            $futurePayroll[$offset] = array_sum(array_map(
                static fn (SuggestionPlayer $p): int => $p->laterSalaries[$offset - 1] ?? 0,
                $team->players,
            ));
        }

        $badContracts = $this->badContracts($team, $depth, $ranks);
        $needs = [
            ...$this->overHardCap($payrollNow),
            ...$this->futureCapStrain($futurePayroll),
            ...$this->cappedOut($payrollNow),
            ...$this->roomToAbsorb($payrollNow, $mode),
            ...$badContracts,
            ...$this->deadWeight($team, $badContracts),
            ...$this->weakStarters($depth, $ranks),
            ...$this->logjams($team, $depth),
            ...$this->expiringStars($depth, $ranks, $recordPct),
            ...$this->consolidate($team, $depth, $ranks),
            new Need($mode === TeamMode::WinNow ? NeedKind::WinNow : NeedKind::Rebuild, 1),
        ];

        usort($needs, static fn (Need $a, Need $b): int
            => [$a->kind->priority(), $b->severity] <=> [$b->kind->priority(), $a->severity]);

        return new NeedsCard($team->tid, $needs, $payrollNow, $futurePayroll, $mode, $recordPct);
    }

    /**
     * @return list<Need>
     */
    private function overHardCap(int $payrollNow): array
    {
        return $payrollNow > League::HARD_CAP_MAX
            ? [new Need(NeedKind::OverHardCap, $payrollNow - League::HARD_CAP_MAX)]
            : [];
    }

    /**
     * @param array<int, int> $futurePayroll
     * @return list<Need>
     */
    private function futureCapStrain(array $futurePayroll): array
    {
        $over1 = max(0, ($futurePayroll[1] ?? 0) - League::HARD_CAP_MAX);
        $over2 = max(0, ($futurePayroll[2] ?? 0) - League::HARD_CAP_MAX);
        if ($over1 === 0 && $over2 === 0) {
            return [];
        }

        $severity = (int) round(NeedThresholds::FUTURE_CAP_WEIGHT_NEXT * $over1 + NeedThresholds::FUTURE_CAP_WEIGHT_AFTER * $over2);

        return [new Need(NeedKind::FutureCapStrain, $severity)];
    }

    /**
     * @return list<Need>
     */
    private function cappedOut(int $payrollNow): array
    {
        $floor = League::HARD_CAP_MAX - NeedThresholds::CAPPED_OUT_MARGIN;

        return $payrollNow >= $floor && $payrollNow <= League::HARD_CAP_MAX
            ? [new Need(NeedKind::CappedOut, $payrollNow - $floor)]
            : [];
    }

    /**
     * @return list<Need>
     */
    private function roomToAbsorb(int $payrollNow, TeamMode $mode): array
    {
        $room = League::HARD_CAP_MAX - $payrollNow;

        return $room >= NeedThresholds::ROOM_TO_ABSORB_MIN && $mode !== TeamMode::WinNow
            ? [new Need(NeedKind::RoomToAbsorb, $room)]
            : [];
    }

    /**
     * @return list<Need>
     */
    private function badContracts(TeamAssets $team, TeamDepth $depth, LeagueStarterRanks $ranks): array
    {
        $needs = [];
        foreach ($team->players as $player) {
            if ($player->currentSalary < NeedThresholds::BAD_CONTRACT_MIN_SALARY
                || $player->yearsRemaining < NeedThresholds::BAD_CONTRACT_MIN_YEARS
                || $player->age < NeedThresholds::BAD_CONTRACT_MIN_AGE) {
                continue;
            }

            $slot = $depth->slotOf($player->pid);
            $rank = $slot === null ? null : $ranks->rankOf($player->pid, $slot);
            $earning = $slot !== null && $rank !== null
                && $rank <= (int) ceil($ranks->countFor($slot) * NeedThresholds::BAD_CONTRACT_RANK_SHARE);
            if (!$earning) {
                $needs[] = new Need(NeedKind::BadContract, $player->futureSalary, $slot, [$player->pid]);
            }
        }

        return $needs;
    }

    /**
     * @param list<Need> $badContracts
     * @return list<Need>
     */
    private function deadWeight(TeamAssets $team, array $badContracts): array
    {
        $listed = array_merge(...array_map(static fn (Need $n): array => $n->pids, $badContracts));
        $needs = [];
        foreach ($team->players as $player) {
            if ($player->dcMinutes < NeedThresholds::UNUSED_MINUTES
                && $player->currentSalary >= NeedThresholds::DEAD_WEIGHT_MIN_SALARY
                && !in_array($player->pid, $listed, true)) {
                $needs[] = new Need(NeedKind::DeadWeight, $player->currentSalary * $player->yearsRemaining, null, [$player->pid]);
            }
        }

        return $needs;
    }

    /**
     * @return list<Need>
     */
    private function weakStarters(TeamDepth $depth, LeagueStarterRanks $ranks): array
    {
        $needs = [];
        foreach (SlotMapper::SLOTS as $slot) {
            $count = $ranks->countFor($slot);
            $starter = $depth->starterAt($slot);
            if ($starter === null || $count < NeedThresholds::WEAK_STARTER_MIN_FIELD) {
                continue;
            }
            $rank = $ranks->rankOf($starter->pid, $slot);
            $cutoff = $count - NeedThresholds::WEAK_STARTER_BOTTOM_N;
            if ($rank !== null && $rank > $cutoff) {
                $needs[] = new Need(NeedKind::WeakStarter, $rank - $cutoff, $slot, [$starter->pid]);
            }
        }

        return $needs;
    }

    /**
     * The logjam's pid is the slot's lowest-minutes real-minute player who
     * does not start there.
     *
     * @return list<Need>
     */
    private function logjams(TeamAssets $team, TeamDepth $depth): array
    {
        $needs = [];
        foreach (SlotMapper::SLOTS as $slot) {
            $rotation = SlotMapper::rotationAt($team->players, $slot);
            if (count($rotation) < NeedThresholds::LOGJAM_MIN_PLAYERS) {
                continue;
            }
            $starterPid = $depth->starterAt($slot)?->pid;
            $spare = array_values(array_filter(
                array_reverse(SlotMapper::byMinutes($rotation)),
                static fn (SuggestionPlayer $p): bool => $p->pid !== $starterPid,
            ));
            if ($spare === []) {
                continue;
            }
            $needs[] = new Need(NeedKind::Logjam, count($rotation) - 2, $slot, [$spare[0]->pid]);
        }

        return $needs;
    }

    /**
     * @return list<Need>
     */
    private function expiringStars(TeamDepth $depth, LeagueStarterRanks $ranks, float $recordPct): array
    {
        if ($recordPct >= NeedThresholds::LOSING_PCT) {
            return [];
        }

        $needs = [];
        foreach (SlotMapper::SLOTS as $slot) {
            $starter = $depth->starterAt($slot);
            if ($starter === null || $starter->yearsRemaining !== 1) {
                continue;
            }
            $rank = $ranks->rankOf($starter->pid, $slot);
            if ($rank !== null && $rank <= NeedThresholds::TOP_PLAYER_MAX_RANK) {
                $needs[] = new Need(NeedKind::ExpiringStarOnLoser, $starter->currentSalary, $slot, [$starter->pid]);
            }
        }

        return $needs;
    }

    /**
     * @return list<Need>
     */
    private function consolidate(TeamAssets $team, TeamDepth $depth, LeagueStarterRanks $ranks): array
    {
        $realMinutes = count(array_filter(
            $team->players,
            static fn (SuggestionPlayer $p): bool => $p->dcMinutes >= NeedThresholds::REAL_MINUTES,
        ));
        if ($realMinutes < NeedThresholds::CONSOLIDATE_MIN_DEPTH) {
            return [];
        }

        foreach (SlotMapper::SLOTS as $slot) {
            $starter = $depth->starterAt($slot);
            $rank = $starter === null ? null : $ranks->rankOf($starter->pid, $slot);
            if ($rank !== null && $rank <= NeedThresholds::TOP_PLAYER_MAX_RANK) {
                return [];
            }
        }

        return [new Need(NeedKind::Consolidate, $realMinutes - (NeedThresholds::CONSOLIDATE_MIN_DEPTH - 1))];
    }
}
