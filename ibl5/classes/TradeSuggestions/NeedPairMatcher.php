<?php

declare(strict_types=1);

namespace TradeSuggestions;

use League\League;

/**
 * Builds candidate deals by pairing one team's need with another's.
 *
 * Every partner is tried in both orientations (user as side A, then as side
 * B) over every need pair in priority order. A partner whose top need wants
 * the same scarce currency as the user's top need is skipped: two dumpers,
 * two sellers or two buyers have nothing to give each other.
 */
final class NeedPairMatcher
{
    /** Veterans a rebuilding side sells. */
    public const int VETERAN_MIN_AGE = 28;

    /** Variants per veteran pick list. */
    public const int MAX_VETERANS = 3;

    /** An expiring deal matches a long one within this share of its salary. */
    public const float LENGTH_SALARY_SHARE = 0.25;

    public function __construct(private readonly int $maxPerPartner = NeedThresholds::MAX_PER_PARTNER)
    {
    }

    /**
     * @param array<int, NeedsCard> $partnerCards Keyed by partner tid
     * @param array<int, TeamAssets> $partnerAssets Keyed by partner tid
     * @param array<int, TeamDepth> $partnerDepths Keyed by partner tid
     * @return \Generator<int, PairedCandidate>
     */
    public function generate(
        NeedsCard $userCard,
        TeamAssets $user,
        array $partnerCards,
        array $partnerAssets,
        TeamDepth $userDepth,
        array $partnerDepths,
        LeagueStarterRanks $ranks,
    ): \Generator {
        $userSide = new MatchSide($userCard, $user, $userDepth);
        foreach ($partnerCards as $tid => $partnerCard) {
            $assets = $partnerAssets[$tid] ?? null;
            $depth = $partnerDepths[$tid] ?? null;
            if ($assets === null || $depth === null) {
                continue;
            }
            $userTop = $userCard->topNeed();
            $partnerTop = $partnerCard->topNeed();
            if ($userTop !== null && $partnerTop !== null && NeedKind::sameWant($userTop->kind, $partnerTop->kind)) {
                continue;
            }

            $partnerSide = new MatchSide($partnerCard, $assets, $depth);
            $emitted = 0;
            $seen = [];
            foreach ([[$userSide, $partnerSide, true], [$partnerSide, $userSide, false]] as [$a, $b, $userIsA]) {
                $perPairing = [];
                foreach ($a->card->needs as $aNeed) {
                    foreach ($b->card->needs as $bNeed) {
                        $pairing = self::pairingFor($aNeed, $bNeed);
                        if ($pairing === null) {
                            continue;
                        }
                        foreach ($this->deals($pairing, $a, $b, $aNeed, $bNeed, $ranks) as $deal) {
                            if (($perPairing[$pairing->name] ?? 0) >= NeedThresholds::MAX_PER_PAIRING) {
                                break;
                            }
                            if (!self::passesGuards($a, $b, $deal)) {
                                continue;
                            }
                            $candidate = $userIsA
                                ? new TradeCandidate($user->tid, $tid, $deal['aPids'], $deal['aPicks'], $deal['bPids'], $deal['bPicks'])
                                : new TradeCandidate($user->tid, $tid, $deal['bPids'], $deal['bPicks'], $deal['aPids'], $deal['aPicks']);
                            $key = implode(',', $candidate->userPids) . '|' . implode(',', $candidate->userPickIds)
                                . '|' . implode(',', $candidate->partnerPids) . '|' . implode(',', $candidate->partnerPickIds);
                            if (isset($seen[$key])) {
                                continue;
                            }
                            $seen[$key] = true;
                            $perPairing[$pairing->name] = ($perPairing[$pairing->name] ?? 0) + 1;

                            yield new PairedCandidate(
                                $candidate,
                                $pairing,
                                $userIsA ? $aNeed : $bNeed,
                                $userIsA ? $bNeed : $aNeed,
                                $deal['dump'] ?? [],
                            );
                            $emitted++;
                            if ($emitted >= $this->maxPerPartner) {
                                continue 5;
                            }
                        }
                    }
                }
            }
        }
    }

    private static function pairingFor(Need $a, Need $b): ?Pairing
    {
        $absorber = in_array($b->kind, [NeedKind::RoomToAbsorb, NeedKind::Rebuild], true);

        return match (true) {
            in_array($a->kind, [NeedKind::OverHardCap, NeedKind::BadContract, NeedKind::DeadWeight], true) && $absorber => Pairing::SalaryDump,
            $a->kind === NeedKind::WinNow && in_array($b->kind, [NeedKind::Rebuild, NeedKind::ExpiringStarOnLoser], true) => Pairing::VeteranForFuture,
            $a->kind === NeedKind::Logjam && $b->kind === NeedKind::WeakStarter && $a->slot === $b->slot => Pairing::SurplusForHole,
            $a->kind === NeedKind::Consolidate && in_array($b->kind, [NeedKind::Logjam, NeedKind::Rebuild], true) => Pairing::Consolidation,
            $a->kind === NeedKind::CappedOut && $absorber => Pairing::LengthForExpiring,
            default => null,
        };
    }

    /**
     * @return list<array{aPids: list<int>, aPicks: list<int>, bPids: list<int>, bPicks: list<int>, dump?: list<int>}>
     */
    private function deals(Pairing $pairing, MatchSide $a, MatchSide $b, Need $aNeed, Need $bNeed, LeagueStarterRanks $ranks): array
    {
        return match ($pairing) {
            Pairing::SalaryDump => self::salaryDump($a, $aNeed),
            Pairing::VeteranForFuture => self::veteranForFuture($a, $b, $bNeed, $ranks),
            Pairing::SurplusForHole => self::surplusForHole($a, $b, $aNeed),
            Pairing::Consolidation => self::consolidation($a, $b, $ranks),
            Pairing::LengthForExpiring => self::lengthForExpiring($a, $b, $ranks),
        };
    }

    /**
     * @return list<array{aPids: list<int>, aPicks: list<int>, bPids: list<int>, bPicks: list<int>, dump?: list<int>}>
     */
    private static function salaryDump(MatchSide $a, Need $aNeed): array
    {
        $dump = $aNeed->pids;
        if ($aNeed->kind === NeedKind::OverHardCap) {
            $bench = $a->tradeableBench();
            usort($bench, static fn (SuggestionPlayer $x, SuggestionPlayer $y): int
                => [$x->dcMinutes, $y->currentSalary, $x->pid] <=> [$y->dcMinutes, $x->currentSalary, $y->pid]);
            $dump = [];
            $sum = 0;
            foreach (array_slice($bench, 0, 2) as $player) {
                $dump[] = $player->pid;
                $sum += $player->currentSalary;
                if ($sum >= $aNeed->severity) {
                    break;
                }
            }
            if ($sum < $aNeed->severity) {
                return [];
            }
        }
        if ($dump === []) {
            return [];
        }

        $deals = [];
        foreach ($a->paymentLadder() as $pay) {
            if (array_intersect($pay['pids'], $dump) !== []) {
                continue;
            }
            $deals[] = ['aPids' => [...$dump, ...$pay['pids']], 'aPicks' => $pay['picks'], 'bPids' => [], 'bPicks' => [], 'dump' => $dump];
        }

        return $deals;
    }

    /**
     * @return list<array{aPids: list<int>, aPicks: list<int>, bPids: list<int>, bPicks: list<int>, dump?: list<int>}>
     */
    private static function veteranForFuture(MatchSide $a, MatchSide $b, Need $bNeed, LeagueStarterRanks $ranks): array
    {
        if ($bNeed->kind === NeedKind::ExpiringStarOnLoser) {
            $vets = array_values(array_filter(array_map($b->player(...), $bNeed->pids)));
        } else {
            $vets = [];
            foreach (SlotMapper::SLOTS as $slot) {
                $starter = $b->depth->starterAt($slot);
                if ($starter !== null) {
                    $vets[] = ['p' => $starter, 'rank' => $ranks->rankOf($starter->pid, $slot) ?? PHP_INT_MAX];
                }
            }
            usort($vets, static fn (array $x, array $y): int => [$x['rank'], $x['p']->pid] <=> [$y['rank'], $y['p']->pid]);
            $vets = array_map(static fn (array $v): SuggestionPlayer => $v['p'], $vets);
        }
        $vets = array_slice(array_values(array_filter(
            $vets,
            static fn (SuggestionPlayer $p): bool => $p->tradeable && $p->age >= self::VETERAN_MIN_AGE,
        )), 0, self::MAX_VETERANS);

        $deals = [];
        foreach ($vets as $vet) {
            foreach (array_slice($a->paymentLadder(), 1) as $pay) {
                $aPids = $pay['pids'];
                $room = ($a->assets->capRoom ?? PHP_INT_MAX) - $vet->currentSalary
                    + array_sum(array_map(static fn (int $pid): int => $a->player($pid)->currentSalary ?? 0, $aPids));
                if ($room < 0) {
                    $filler = null;
                    foreach (array_reverse($a->tradeableBench()) as $player) {
                        if (!in_array($player->pid, $aPids, true) && $player->currentSalary >= -$room) {
                            $filler = $player;
                            break;
                        }
                    }
                    if ($filler === null) {
                        continue;
                    }
                    $aPids[] = $filler->pid;
                }
                $deals[] = ['aPids' => $aPids, 'aPicks' => $pay['picks'], 'bPids' => [$vet->pid], 'bPicks' => []];
            }
        }

        return $deals;
    }

    /**
     * @return list<array{aPids: list<int>, aPicks: list<int>, bPids: list<int>, bPicks: list<int>, dump?: list<int>}>
     */
    private static function surplusForHole(MatchSide $a, MatchSide $b, Need $aNeed): array
    {
        $surplus = isset($aNeed->pids[0]) ? $a->player($aNeed->pids[0]) : null;
        if ($surplus === null || $aNeed->slot === null || !SlotMapper::eligible($surplus, $aNeed->slot)) {
            return [];
        }

        foreach ($a->card->needs as $need) {
            if ($need->kind !== NeedKind::WeakStarter || $need->slot === null) {
                continue;
            }
            foreach ($b->tradeableBench() as $player) {
                if ($player->dcMinutes >= NeedThresholds::REAL_MINUTES && SlotMapper::eligible($player, $need->slot)) {
                    return [['aPids' => [$surplus->pid], 'aPicks' => [], 'bPids' => [$player->pid], 'bPicks' => []]];
                }
            }
        }

        $deals = [];
        foreach (array_slice($b->cheapestPicks(), 0, 2) as $pick) {
            $deals[] = ['aPids' => [$surplus->pid], 'aPicks' => [], 'bPids' => [], 'bPicks' => [$pick->pickId]];
        }

        return $deals;
    }

    /**
     * @return list<array{aPids: list<int>, aPicks: list<int>, bPids: list<int>, bPicks: list<int>, dump?: list<int>}>
     */
    private static function consolidation(MatchSide $a, MatchSide $b, LeagueStarterRanks $ranks): array
    {
        if ($b->assets->rosterCount() > NeedThresholds::ROSTER_MAX - 1) {
            return [];
        }
        $bench = array_slice($a->tradeableBench(), 0, 2);
        if (count($bench) < 2) {
            return [];
        }
        $aPids = [$bench[0]->pid, $bench[1]->pid];
        $cheapest = $a->cheapestPicks()[0] ?? null;

        $deals = [];
        foreach (SlotMapper::SLOTS as $slot) {
            $starter = $b->depth->starterAt($slot);
            $rank = $starter === null ? null : $ranks->rankOf($starter->pid, $slot);
            if ($starter === null || !$starter->tradeable || $rank === null || $rank > NeedThresholds::TOP_PLAYER_MAX_RANK) {
                continue;
            }
            $deals[] = ['aPids' => $aPids, 'aPicks' => [], 'bPids' => [$starter->pid], 'bPicks' => []];
            if ($cheapest !== null) {
                $deals[] = ['aPids' => $aPids, 'aPicks' => [$cheapest->pickId], 'bPids' => [$starter->pid], 'bPicks' => []];
            }
        }

        return $deals;
    }

    /**
     * @return list<array{aPids: list<int>, aPicks: list<int>, bPids: list<int>, bPicks: list<int>, dump?: list<int>}>
     */
    private static function lengthForExpiring(MatchSide $a, MatchSide $b, LeagueStarterRanks $ranks): array
    {
        $long = null;
        foreach ($a->assets->players as $player) {
            if (!$player->tradeable || $player->yearsRemaining < 3) {
                continue;
            }
            $slot = $a->depth->slotOf($player->pid);
            $rank = $slot === null ? null : $ranks->rankOf($player->pid, $slot);
            if ($rank !== null && $rank <= NeedThresholds::TOP_PLAYER_MAX_RANK) {
                continue;
            }
            if ($long === null || [$player->currentSalary, -$player->pid] > [$long->currentSalary, -$long->pid]) {
                $long = $player;
            }
        }
        if ($long === null) {
            return [];
        }

        $picks = $a->cheapestPicks();
        $sweeteners = isset($picks[0]) ? [[$picks[0]->pickId]] : [[]];
        if (isset($picks[0], $picks[1])) {
            $sweeteners[] = [$picks[0]->pickId, $picks[1]->pickId];
        }

        $deals = [];
        foreach ($b->assets->players as $player) {
            if (!$player->tradeable || $player->yearsRemaining !== 1
                || abs($player->currentSalary - $long->currentSalary) > self::LENGTH_SALARY_SHARE * $long->currentSalary) {
                continue;
            }
            foreach ($sweeteners as $pickIds) {
                $deals[] = ['aPids' => [$long->pid], 'aPicks' => $pickIds, 'bPids' => [$player->pid], 'bPicks' => [], 'dump' => [$long->pid]];
            }
        }

        return $deals;
    }

    /**
     * Cheap prefilters. The Trading validator still decides legality.
     *
     * @param array{aPids: list<int>, aPicks: list<int>, bPids: list<int>, bPicks: list<int>, dump?: list<int>} $deal
     */
    private static function passesGuards(MatchSide $a, MatchSide $b, array $deal): bool
    {
        if (count($deal['aPicks']) > NeedThresholds::MAX_PICKS_PER_SIDE || count($deal['bPicks']) > NeedThresholds::MAX_PICKS_PER_SIDE) {
            return false;
        }
        if ($deal['aPids'] === [] && $deal['bPids'] === []) {
            return false;
        }

        $aOut = self::players($a, $deal['aPids']);
        $bOut = self::players($b, $deal['bPids']);
        $aPicks = self::picks($a, $deal['aPicks']);
        $bPicks = self::picks($b, $deal['bPicks']);
        if ($aOut === null || $bOut === null || $aPicks === null || $bPicks === null) {
            return false;
        }

        $aAfter = $a->assets->afterTrade($deal['aPids'], $deal['aPicks'], $bOut, $bPicks);
        $bAfter = $b->assets->afterTrade($deal['bPids'], $deal['bPicks'], $aOut, $aPicks);

        return self::sideHolds($a->assets, $aAfter) && self::sideHolds($b->assets, $bAfter);
    }

    private static function sideHolds(TeamAssets $before, TeamAssets $after): bool
    {
        if ($after->rosterCount() > NeedThresholds::ROSTER_MAX) {
            return false;
        }
        if ($after->capRoom !== null && League::HARD_CAP_MAX - $after->capRoom > League::HARD_CAP_MAX) {
            return false;
        }
        foreach (SlotMapper::SLOTS as $slot) {
            if (self::covers($before, $slot) && !self::covers($after, $slot)) {
                return false;
            }
        }

        return true;
    }

    private static function covers(TeamAssets $team, string $slot): bool
    {
        foreach ($team->players as $player) {
            if (SlotMapper::eligible($player, $slot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<int> $pids
     * @return list<SuggestionPlayer>|null Null when a pid is missing or untradeable
     */
    private static function players(MatchSide $side, array $pids): ?array
    {
        $players = [];
        foreach ($pids as $pid) {
            $player = $side->player($pid);
            if ($player === null || !$player->tradeable) {
                return null;
            }
            $players[] = $player;
        }

        return $players;
    }

    /**
     * @param list<int> $pickIds
     * @return list<SuggestionPick>|null Null when the side does not own a pick
     */
    private static function picks(MatchSide $side, array $pickIds): ?array
    {
        $picks = [];
        foreach ($pickIds as $pickId) {
            $found = null;
            foreach ($side->assets->picks as $pick) {
                if ($pick->pickId === $pickId) {
                    $found = $pick;
                }
            }
            if ($found === null) {
                return null;
            }
            $picks[] = $found;
        }

        return $picks;
    }
}
