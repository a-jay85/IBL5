<?php

declare(strict_types=1);

namespace TradeSuggestions;

use League\League;

/**
 * Judges a paired candidate by each side's own top need.
 *
 * Order: the effective-year cap gate, then the vetoes, then the sweetener
 * check for salary dumps, then each side's gain. A side with no gain makes the
 * deal OneSided. Future-year cap overage only lowers gain; it never rejects.
 */
final class NeedJudge
{
    public const int OLD_AGE = 31;
    public const int OLD_OWED_VETO = 2000;
    public const int ROSTER_SPOT_COST = 10;

    public function judge(
        PairedCandidate $p,
        NeedsCard $userCard,
        NeedsCard $partnerCard,
        TeamAssets $user,
        TeamAssets $partner,
        LeagueStarterRanks $ranks,
    ): Judgment|RejectReason {
        $c = $p->candidate;
        $userOut = self::players($user, $c->userPids);
        $partnerOut = self::players($partner, $c->partnerPids);
        $userPicksOut = self::picks($user, $c->userPickIds);
        $partnerPicksOut = self::picks($partner, $c->partnerPickIds);

        $userAfter = $user->afterTrade($c->userPids, $c->userPickIds, $partnerOut, $partnerPicksOut);
        $partnerAfter = $partner->afterTrade($c->partnerPids, $c->partnerPickIds, $userOut, $userPicksOut);

        $userPayrollAfter = self::payroll($userAfter);
        $partnerPayrollAfter = self::payroll($partnerAfter);
        if ($userPayrollAfter > League::HARD_CAP_MAX || $partnerPayrollAfter > League::HARD_CAP_MAX) {
            return RejectReason::CapStillOver;
        }

        $dumpShape = $p->pairing === Pairing::SalaryDump || $p->pairing === Pairing::LengthForExpiring;
        if (
            $this->worsened($user, $userAfter, $userCard, $userOut, $partnerOut, $dumpShape)
            || $this->worsened($partner, $partnerAfter, $partnerCard, $partnerOut, $userOut, $dumpShape)
        ) {
            return RejectReason::ImportantWorsened;
        }

        $userPaid = $this->paidTier($partnerOut, $partnerPicksOut, $ranks);
        $partnerPaid = $this->paidTier($userOut, $userPicksOut, $ranks);
        $requiredTier = 0;
        $paidTier = 0;
        if ($dumpShape) {
            $userOverpay = self::owed($partnerOut) - self::owed($userOut);
            $receiverPaid = $userOverpay > 0 ? $userPaid : $partnerPaid;
            $requiredTier = self::requiredTier(abs($userOverpay));
            $paidTier = $receiverPaid;
            if ($paidTier < $requiredTier) {
                return RejectReason::PaymentShort;
            }
        }

        $userGain = $this->gain($userCard, $user, $userAfter, $userOut, $partnerOut, $userPaid, $ranks);
        $partnerGain = $this->gain($partnerCard, $partner, $partnerAfter, $partnerOut, $userOut, $partnerPaid, $ranks);
        if ($userGain <= 0 || $partnerGain <= 0) {
            return RejectReason::OneSided;
        }

        return new Judgment($userGain, $partnerGain, $requiredTier, $paidTier, $userPayrollAfter, $partnerPayrollAfter);
    }

    /**
     * Sweetener tier owed for taking on $overpay thousand in total salary.
     */
    public static function requiredTier(int $overpay): int
    {
        return match (true) {
            $overpay >= 4000 => 3,
            $overpay >= 2500 => 2,
            $overpay >= 1000 => 1,
            default => 0,
        };
    }

    /**
     * Tier value of the picks received plus the young-player floor: 1 for a
     * player aged 24 or under, 2 when he ranks in the top half of his slot.
     *
     * @param list<SuggestionPlayer> $playersIn
     * @param list<SuggestionPick> $picksIn
     */
    public function paidTier(array $playersIn, array $picksIn, LeagueStarterRanks $ranks): int
    {
        $paid = 0;
        foreach ($picksIn as $pick) {
            $paid += ($pick->tier ?? PickTier::Second)->value;
        }
        foreach ($playersIn as $player) {
            if ($player->age > NeedThresholds::YOUNG_MAX_AGE) {
                continue;
            }
            $paid += self::inTopHalf($player, $ranks) ? 2 : 1;
        }

        return $paid;
    }

    /**
     * @param list<SuggestionPlayer> $out
     * @param list<SuggestionPlayer> $in
     */
    private function worsened(
        TeamAssets $before,
        TeamAssets $after,
        NeedsCard $card,
        array $out,
        array $in,
        bool $dumpShape,
    ): bool {
        $depth = SlotMapper::depthFor($before->players);
        foreach ($out as $player) {
            $slot = $depth->slotOf($player->pid);
            if ($slot === null) {
                continue;
            }
            if ($card->mode === TeamMode::WinNow) {
                return true;
            }
        }

        if (!$dumpShape && self::owed(self::old($in)) - self::owed(self::old($out)) >= self::OLD_OWED_VETO) {
            return true;
        }

        foreach (SlotMapper::SLOTS as $slot) {
            if (self::anyEligible($before->players, $slot) && !self::anyEligible($after->players, $slot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<SuggestionPlayer> $out
     * @param list<SuggestionPlayer> $in
     */
    private function gain(
        NeedsCard $card,
        TeamAssets $before,
        TeamAssets $after,
        array $out,
        array $in,
        int $paidTier,
        LeagueStarterRanks $ranks,
    ): int {
        $need = $card->topNeed();
        if ($need === null) {
            return 0;
        }

        $gain = match ($need->kind) {
            NeedKind::OverHardCap => self::payroll($after) <= League::HARD_CAP_MAX ? 100 : 0,
            NeedKind::BadContract, NeedKind::DeadWeight => min(100, intdiv(max(0, self::owed($out) - self::owed($in)), 30)),
            NeedKind::CappedOut => min(100, intdiv(max(0, self::later($out) - self::later($in)), 20)),
            NeedKind::WeakStarter => $this->slotGain($before, $in, $need->slot, $ranks),
            NeedKind::WinNow => $this->slotGain($before, $in, null, $ranks),
            NeedKind::Logjam => 40 + $this->weakStarterGain($card, $before, $in, $ranks),
            NeedKind::Consolidate => $this->consolidateGain($in, $ranks),
            NeedKind::RoomToAbsorb, NeedKind::Rebuild, NeedKind::ExpiringStarOnLoser => min(100, 25 * $paidTier),
            NeedKind::FutureCapStrain => min(100, intdiv(max(0, self::futureOverage($before) - self::futureOverage($after)), 20)),
        };

        $netIncoming = max(0, count($in) - count($out));
        $gain -= self::ROSTER_SPOT_COST * $netIncoming;

        $increase = self::futureOverage($after) - self::futureOverage($before);
        if ($increase > 0) {
            $gain -= intdiv($increase, 10);
        }

        return $gain;
    }

    /**
     * Rank places gained at a slot, as a share of the slot's ranked field.
     * With $slot null each incoming player is measured at his own best slot.
     * The best incoming player counts fully and each lesser one at half.
     *
     * @param list<SuggestionPlayer> $in
     */
    private function slotGain(TeamAssets $before, array $in, ?string $slot, LeagueStarterRanks $ranks): int
    {
        $depth = SlotMapper::depthFor($before->players);
        $gains = [];
        foreach ($in as $player) {
            $slots = $slot === null ? SlotMapper::slotsForPos($player->pos) : [$slot];
            $best = 0;
            foreach ($slots as $s) {
                if (!SlotMapper::eligible($player, $s)) {
                    continue;
                }
                $count = $ranks->countFor($s);
                $newRank = $ranks->rankIfStarting($player, $s);
                if ($count === 0 || $newRank === null) {
                    continue;
                }
                $starter = $depth->starterAt($s);
                $oldRank = ($starter === null ? null : $ranks->rankOf($starter->pid, $s)) ?? $count + 1;
                $best = max($best, intdiv(($oldRank - $newRank) * 100, $count));
            }
            $gains[] = $best;
        }

        return self::package($gains);
    }

    /**
     * @param list<SuggestionPlayer> $in
     */
    private function weakStarterGain(NeedsCard $card, TeamAssets $before, array $in, LeagueStarterRanks $ranks): int
    {
        foreach ($card->needs as $need) {
            if ($need->kind === NeedKind::WeakStarter) {
                return max(0, $this->slotGain($before, $in, $need->slot, $ranks));
            }
        }

        return 0;
    }

    /**
     * @param list<SuggestionPlayer> $in
     */
    private function consolidateGain(array $in, LeagueStarterRanks $ranks): int
    {
        $gains = [];
        foreach ($in as $player) {
            $best = 0;
            foreach (SlotMapper::slotsForPos($player->pos) as $slot) {
                $rank = $ranks->rankIfStarting($player, $slot);
                if ($rank !== null) {
                    $best = max($best, (6 - $rank) * 20);
                }
            }
            $gains[] = $best;
        }

        return self::package($gains);
    }

    /**
     * @param list<int> $gains
     */
    private static function package(array $gains): int
    {
        if ($gains === []) {
            return 0;
        }
        rsort($gains);
        $total = (float) array_shift($gains);
        foreach ($gains as $g) {
            $total += 0.5 * $g;
        }

        return (int) round($total);
    }

    /**
     * @param list<SuggestionPlayer> $in
     */
    private static function anyEligible(array $in, string $slot): bool
    {
        foreach ($in as $player) {
            if (SlotMapper::eligible($player, $slot)) {
                return true;
            }
        }

        return false;
    }

    private static function inTopHalf(SuggestionPlayer $player, LeagueStarterRanks $ranks): bool
    {
        foreach (SlotMapper::slotsForPos($player->pos) as $slot) {
            $rank = $ranks->rankIfStarting($player, $slot);
            if ($rank !== null && $rank * 2 <= $ranks->countFor($slot)) {
                return true;
            }
        }

        return false;
    }

    private static function payroll(TeamAssets $team): int
    {
        return $team->capRoom !== null
            ? League::HARD_CAP_MAX - $team->capRoom
            : array_sum(array_map(static fn (SuggestionPlayer $p): int => $p->currentSalary, $team->players));
    }

    /**
     * Weighted overage for the next two seasons (0.35 and 0.15).
     */
    private static function futureOverage(TeamAssets $team): int
    {
        $over = 0.0;
        foreach ([1 => NeedThresholds::FUTURE_CAP_WEIGHT_NEXT, 2 => NeedThresholds::FUTURE_CAP_WEIGHT_AFTER] as $offset => $weight) {
            $payroll = 0;
            foreach ($team->players as $p) {
                $payroll += $p->laterSalaries[$offset - 1] ?? 0;
            }
            $over += $weight * max(0, $payroll - League::HARD_CAP_MAX);
        }

        return (int) round($over);
    }

    /**
     * Total salary owed: this season plus every later season.
     *
     * @param list<SuggestionPlayer> $players
     */
    private static function owed(array $players): int
    {
        $total = 0;
        foreach ($players as $p) {
            $total += $p->currentSalary + array_sum($p->laterSalaries);
        }

        return $total;
    }

    /**
     * @param list<SuggestionPlayer> $players
     */
    private static function later(array $players): int
    {
        $total = 0;
        foreach ($players as $p) {
            $total += array_sum($p->laterSalaries);
        }

        return $total;
    }

    /**
     * @param list<SuggestionPlayer> $players
     * @return list<SuggestionPlayer>
     */
    private static function old(array $players): array
    {
        return array_values(array_filter($players, static fn (SuggestionPlayer $p): bool => $p->age >= self::OLD_AGE));
    }

    /**
     * @param list<int> $pids
     * @return list<SuggestionPlayer>
     */
    private static function players(TeamAssets $team, array $pids): array
    {
        return array_values(array_filter($team->players, static fn (SuggestionPlayer $p): bool => in_array($p->pid, $pids, true)));
    }

    /**
     * @param list<int> $pickIds
     * @return list<SuggestionPick>
     */
    private static function picks(TeamAssets $team, array $pickIds): array
    {
        return array_values(array_filter($team->picks, static fn (SuggestionPick $p): bool => in_array($p->pickId, $pickIds, true)));
    }
}
