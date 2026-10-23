<?php

declare(strict_types=1);

namespace TradeSuggestions;

use BasketballStats\StatsFormatter;
use League\League;

/**
 * Builds the plain-language "why" line for one side of a judged deal. Pure.
 *
 * Every line comes from the side's top need and the deal's own numbers: names,
 * money, years, age, record, raw per-game stats and per-36 ranks within a slot.
 * It returns null when a number the template needs is missing, and the caller
 * drops the deal. The view escapes the result.
 */
final class ReasonBuilder
{
    /**
     * The reason one side takes a judged deal, built from its own top need.
     * The user side speaks as "You", the partner as "They". Returns null when
     * a number the template needs is missing; the caller drops the deal.
     */
    public function forSide(
        PairedCandidate $p,
        Judgment $j,
        bool $userSide,
        NeedsCard $card,
        TeamAssets $self,
        TeamAssets $other,
        LeagueStarterRanks $ranks,
    ): ?string {
        $need = $card->topNeed();
        if ($need === null) {
            return null;
        }
        $c = $p->candidate;
        $out = self::pick($self->players, $userSide ? $c->userPids : $c->partnerPids);
        $in = self::pick($other->players, $userSide ? $c->partnerPids : $c->userPids);
        $inPickIds = $userSide ? $c->partnerPickIds : $c->userPickIds;
        $outPickIds = $userSide ? $c->userPickIds : $c->partnerPickIds;
        $inPicks = array_values(array_filter($other->picks, static fn (SuggestionPick $pk): bool => in_array($pk->pickId, $inPickIds, true)));
        $outPicks = array_values(array_filter($self->picks, static fn (SuggestionPick $pk): bool => in_array($pk->pickId, $outPickIds, true)));

        $v = $userSide
            ? ['We' => 'You', 'Were' => "You're", 'our' => 'your', 'us' => 'you', 'we' => 'you', 'They' => 'They']
            : ['We' => 'They', 'Were' => "They're", 'our' => 'their', 'us' => 'them', 'we' => 'they', 'They' => 'You'];
        $payment = self::payment($in, $inPicks);
        $named = self::named($out, $need) ?? ($out[0] ?? null);
        $payrollAfter = $userSide ? $j->userPayrollAfter : $j->partnerPayrollAfter;
        $under = League::HARD_CAP_MAX - $payrollAfter;

        $reason = match ($need->kind) {
            NeedKind::OverHardCap => $v['Were'] . ' ' . ($card->payrollNow - League::HARD_CAP_MAX) . ' over the hard cap. This gets '
                . $v['us'] . ' ' . ($under === 0 ? 'exactly to the hard cap.' : $under . ' under.'),
            NeedKind::BadContract => $named === null ? null
                : $named->name . ' is owed ' . array_sum($named->laterSalaries) . ' after this season at age ' . $named->age . '. This clears it.',
            NeedKind::DeadWeight => $named === null ? null
                : $named->name . ' plays ' . $named->dcMinutes . ' minutes and costs ' . $named->currentSalary . '. This frees the salary.',
            NeedKind::WeakStarter => $this->weakStarter($v, $need->slot, $self, $in, $ranks),
            NeedKind::ExpiringStarOnLoser => $named === null || $payment === null ? null
                : $named->name . ' is in the last year of his deal and ' . $v['we'] . ' win ' . (int) round($card->recordPct * 100)
                    . '% of games. This turns him into ' . $payment . '.',
            NeedKind::CappedOut => $named === null ? null
                : $v['Were'] . ' ' . (League::HARD_CAP_MAX - $card->payrollNow) . ' under the hard cap. This swaps '
                    . count($named->laterSalaries) . ' years of ' . $named->name . "'s salary for an expiring deal.",
            NeedKind::Logjam => $this->logjam($v, $need, $named, $in, $payment),
            NeedKind::Consolidate => $this->consolidate($v, $in, $ranks),
            NeedKind::RoomToAbsorb => $payment === null || $in === [] ? null
                : $v['We'] . ' have ' . (League::HARD_CAP_MAX - $card->payrollNow) . ' of room under the hard cap. Taking on '
                    . $in[0]->name . ' earns ' . $v['us'] . ' ' . $payment . '.',
            NeedKind::Rebuild => $payment === null || $named === null ? null
                : ucfirst($payment) . ' for ' . $named->name . ', age ' . $named->age . ', fits ' . $v['our'] . ' rebuild.',
            NeedKind::WinNow => $this->winNow($v, $self, $in, $ranks),
            NeedKind::FutureCapStrain => null,
        };
        if ($reason === null) {
            return null;
        }

        $dump = $p->pairing === Pairing::SalaryDump || $p->pairing === Pairing::LengthForExpiring;
        if ($dump && $j->paidTier > 0 && $outPicks !== [] && $out !== []) {
            $years = max(array_map(static fn (SuggestionPlayer $pl): int => $pl->yearsRemaining, $out));
            $reason .= ' ' . $v['They'] . ' take on ' . $years . ' more years of salary, so ' . $v['we'] . ' send '
                . implode(' and ', array_map(self::pickLabel(...), $outPicks)) . '.';
        }

        return $reason;
    }

    public static function ordinal(int $n): string
    {
        $suffix = match (true) {
            $n % 100 >= 11 && $n % 100 <= 13 => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        };

        return $n . $suffix;
    }

    /**
     * @param array<string, string> $v
     * @param list<SuggestionPlayer> $in
     */
    private function weakStarter(array $v, ?string $slot, TeamAssets $self, array $in, LeagueStarterRanks $ranks): ?string
    {
        if ($slot === null) {
            return null;
        }
        $starter = SlotMapper::depthFor($self->players)->starterAt($slot);
        $rank = $starter === null ? null : $ranks->rankOf($starter->pid, $slot);
        [$incoming, $newRank] = self::bestAt($in, $slot, $ranks);
        if ($rank === null || $incoming === null || $newRank === null) {
            return null;
        }

        return ucfirst($v['our']) . ' starting ' . $slot . ' ranks ' . self::ordinal($rank) . ' of ' . $ranks->countFor($slot)
            . ' starting ' . $slot . 's in points per 36. ' . $incoming->name . ' would rank ' . self::ordinal($newRank)
            . ' (' . self::ppg($incoming) . ' ppg).';
    }

    /**
     * @param array<string, string> $v
     * @param list<SuggestionPlayer> $in
     */
    private function logjam(array $v, Need $need, ?SuggestionPlayer $named, array $in, ?string $payment): ?string
    {
        if ($named === null || $need->slot === null) {
            return null;
        }
        $head = $v['We'] . ' have ' . $need->severity . ' ' . $need->slot . 's getting minutes. This turns ' . $named->name . ' into ';
        foreach ($in as $player) {
            foreach (SlotMapper::slotsForPos($player->pos) as $slot) {
                if ($slot !== $need->slot) {
                    return $head . 'a starting ' . $slot . '.';
                }
            }
        }

        return $payment === null ? null : $head . $payment . '.';
    }

    /**
     * @param array<string, string> $v
     * @param list<SuggestionPlayer> $in
     */
    private function consolidate(array $v, array $in, LeagueStarterRanks $ranks): ?string
    {
        foreach ($in as $player) {
            $line = $player->production;
            foreach (SlotMapper::slotsForPos($player->pos) as $slot) {
                $rank = $ranks->rankIfStarting($player, $slot);
                if ($rank !== null && $line !== null) {
                    return $v['We'] . ' get ' . $player->name . ', ' . self::ordinal($rank) . ' of ' . $ranks->countFor($slot)
                        . ' starting ' . $slot . 's in points per 36 (' . self::ppg($player) . ' ppg, '
                        . StatsFormatter::formatPerGameAverage($line->rebounds, $line->games) . ' rpg, '
                        . StatsFormatter::formatPerGameAverage($line->assists, $line->games) . ' apg).';
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $v
     * @param list<SuggestionPlayer> $in
     */
    private function winNow(array $v, TeamAssets $self, array $in, LeagueStarterRanks $ranks): ?string
    {
        $depth = SlotMapper::depthFor($self->players);
        foreach ($in as $player) {
            foreach (SlotMapper::slotsForPos($player->pos) as $slot) {
                $newRank = $ranks->rankIfStarting($player, $slot);
                $starter = $depth->starterAt($slot);
                $oldRank = $starter === null ? null : $ranks->rankOf($starter->pid, $slot);
                if ($newRank !== null && $oldRank !== null && $newRank < $oldRank) {
                    return $player->name . ' (' . self::ppg($player) . ' ppg) would start at ' . $slot . ' for ' . $v['us'] . ', '
                        . self::ordinal($newRank) . ' of ' . $ranks->countFor($slot) . ' in points per 36, up from '
                        . self::ordinal($oldRank) . '.';
                }
            }
        }

        return null;
    }

    /**
     * @param list<SuggestionPlayer> $in
     * @return array{0: ?SuggestionPlayer, 1: ?int}
     */
    private static function bestAt(array $in, string $slot, LeagueStarterRanks $ranks): array
    {
        $best = null;
        $bestRank = null;
        foreach ($in as $player) {
            if (!SlotMapper::eligible($player, $slot)) {
                continue;
            }
            $rank = $ranks->rankIfStarting($player, $slot);
            if ($rank !== null && ($bestRank === null || $rank < $bestRank)) {
                $best = $player;
                $bestRank = $rank;
            }
        }

        return [$best, $bestRank];
    }

    private static function ppg(SuggestionPlayer $p): string
    {
        return StatsFormatter::formatPerGameAverage($p->production->points ?? 0, $p->production->games ?? 0);
    }

    /**
     * @param list<SuggestionPlayer> $in
     * @param list<SuggestionPick> $inPicks
     */
    private static function payment(array $in, array $inPicks): ?string
    {
        $parts = array_map(self::pickLabel(...), $inPicks);
        foreach ($in as $player) {
            if ($player->age <= NeedThresholds::YOUNG_MAX_AGE) {
                $parts[] = $player->name . ' (age ' . $player->age . ')';
            }
        }

        return $parts === [] ? null : implode(' and ', $parts);
    }

    private static function pickLabel(SuggestionPick $pick): string
    {
        return 'the ' . $pick->year . ' Round ' . $pick->round . ' pick';
    }

    /**
     * @param list<SuggestionPlayer> $out
     */
    private static function named(array $out, Need $need): ?SuggestionPlayer
    {
        foreach ($out as $player) {
            if (in_array($player->pid, $need->pids, true)) {
                return $player;
            }
        }

        return null;
    }

    /**
     * @param list<SuggestionPlayer> $players
     * @param list<int> $pids
     * @return list<SuggestionPlayer>
     */
    private static function pick(array $players, array $pids): array
    {
        return array_values(array_filter($players, static fn (SuggestionPlayer $pl): bool => in_array($pl->pid, $pids, true)));
    }
}
