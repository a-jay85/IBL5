<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use Team\Team;
use TradeSuggestions\Profile\ProfileDimension;
use TradeSuggestions\Profile\RankUtility;
use TradeSuggestions\Projection\Horizon;
use TradeSuggestions\Projection\InjuryStatus;
use TradeSuggestions\SlotMapper;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Valuation\CapRoomAsset;
use TradeSuggestions\Valuation\GainWeights;
use TradeSuggestions\Valuation\PickWorth;
use TradeSuggestions\Valuation\ResignLikelihood;
use TradeSuggestions\Valuation\RookieControl;

/**
 * One side's gain from a deal, as a table of weighted rank places per season
 * and component. Every component is a team-level rank move or a contract
 * property. None is a player quality figure.
 */
final readonly class SideGain
{
    /** `contracts` is last so the older key positions never move. */
    public const array KEYS = ['starter_slots', 'bench_slots', 'profile_dimensions', 'balance', 'payroll', 'cap_room', 'picks', 'roster_spots', 'contracts'];

    /**
     * Freeing a spot on a full roster is worth half a bottom place: the spot
     * lets the team sign a minimum player, roughly half a bottom-tier upgrade.
     */
    public const float ROSTER_SPOT_PLACES = 0.5;

    /** A missing rank (an empty slot) counts as the worst place. */
    public const int EMPTY_SLOT_RANK = RankUtility::MAX_RANK;

    /**
     * @param array<int, array<string, float>> $components offset => key => weighted rank places
     * @param list<float> $seasonWeights offset => weight
     */
    public function __construct(
        public int $tid,
        public TeamMode $mode,
        private array $components,
        private array $seasonWeights,
        private float $rentalScale,
    ) {
    }

    /**
     * @return array<int, array<string, float>>
     */
    public function components(): array
    {
        return $this->components;
    }

    /**
     * @return list<float>
     */
    public function seasonWeights(): array
    {
        return $this->seasonWeights;
    }

    public function rentalScale(): float
    {
        return $this->rentalScale;
    }

    public function seasonTotal(int $offset): float
    {
        $sum = array_sum($this->components[$offset] ?? []);

        return $offset === 0 ? $sum * $this->rentalScale : $sum;
    }

    public function total(): float
    {
        $total = 0.0;
        foreach ($this->seasonWeights as $offset => $weight) {
            $total += $weight * $this->seasonTotal($offset);
        }

        return $total;
    }

    /**
     * One offset's components for side `$tid`.
     *
     * @param array<int, float> $walkFactor pid => F10 factor at this offset; a pid not listed uses the snapshot's likelihood
     * @return array<string, float>
     */
    public static function fromStates(TeamState $before, TeamState $after, LeagueSnapshot $s, TradeCandidate $c, int $tid, array $walkFactor = []): array
    {
        $offset = $after->offset;
        $isUser = $tid === $c->userTid;
        $receivedPids = $isUser ? $c->partnerPids : $c->userPids;
        $sentPids = $isUser ? $c->userPids : $c->partnerPids;
        $receivedPicks = $isUser ? $c->partnerPickIds : $c->userPickIds;
        $sentPicks = $isUser ? $c->userPickIds : $c->partnerPickIds;
        $mode = $s->mode($tid);

        $availability = [];
        if ($offset === 0) {
            foreach (SlotMapper::SLOTS as $slot) {
                $pid = $after->lineup->starterPid($slot);
                if ($pid !== null && in_array($pid, $receivedPids, true) && isset($s->injury[$pid])) {
                    $availability[$slot] = self::availability($s->injury[$pid], $mode);
                }
            }
        }

        $factors = [];
        foreach ([...$receivedPids, ...$sentPids] as $pid) {
            $factors[$pid] = $walkFactor[$pid] ?? ResignLikelihood::futureValueFactor(
                $offset,
                $s->players[$pid]->onRosterAt($offset),
                $s->likelyToWalk[$pid] ?? false,
                $s->resignLikelihood[$pid] ?? 1.0,
            );
        }

        return [
            'starter_slots' => self::slots($before->starterRank, $after->starterRank, GainWeights::STARTER_WEIGHT, $availability),
            'bench_slots' => self::slots($before->bestBenchRank, $after->bestBenchRank, GainWeights::BENCH_WEIGHT, []),
            'profile_dimensions' => self::profileDimensions($before->profile->ranks, $after->profile->ranks),
            'balance' => self::balance($before->profile->balance(), $after->profile->balance()),
            'payroll' => CapRoomAsset::places(
                $offset,
                $before->payroll,
                $after->payroll,
                $before->payrollRank,
                $offset === 0 ? $after->payrollRank : $before->payrollRank,
            ),
            'cap_room' => self::capRoom($offset, $before->payrollRank, $after->payrollRank),
            'picks' => $offset === 0 ? self::picks($s, $mode, $after->rosterCount, $receivedPicks, $sentPicks) : 0.0,
            'roster_spots' => self::rosterSpots($before->rosterCount, $after->rosterCount),
            'contracts' => self::contracts($s, $offset, $receivedPids, $sentPids, $factors),
        ];
    }

    /**
     * @param array<string, ?int> $before
     * @param array<string, ?int> $after
     * @param array<string, float> $availability slot => weight for a received new starter
     */
    public static function slots(array $before, array $after, float $weight, array $availability): float
    {
        $sum = 0.0;
        foreach ($after as $slot => $rank) {
            $gain = RankUtility::gain($before[$slot] ?? self::EMPTY_SLOT_RANK, $rank ?? self::EMPTY_SLOT_RANK);
            $sum += $gain * ($availability[$slot] ?? 1.0);
        }

        return $weight * $sum;
    }

    /**
     * @param array<string, int> $before
     * @param array<string, int> $after
     */
    public static function profileDimensions(array $before, array $after): float
    {
        $sum = 0.0;
        foreach (ProfileDimension::KEYS as $key) {
            if (isset($before[$key], $after[$key])) {
                $sum += RankUtility::gain($before[$key], $after[$key]);
            }
        }

        return $sum;
    }

    public static function balance(int $before, int $after): float
    {
        return RankUtility::gain(self::clampRank(abs($before) + 1), self::clampRank(abs($after) + 1));
    }

    public static function capRoom(int $offset, int $rankBefore, int $rankAfter): float
    {
        return $offset === 0 ? 0.0 : CapRoomAsset::FUTURE_ROOM_WEIGHT * RankUtility::gain($rankBefore, $rankAfter);
    }

    public static function rosterSpots(int $countBefore, int $countAfter): float
    {
        return $countBefore >= Team::ROSTER_SPOTS_MAX ? self::ROSTER_SPOT_PLACES * ($countBefore - $countAfter) : 0.0;
    }

    /**
     * Rookie-control places at one offset: `assetPlaces` over a surplus mask
     * that keeps only offset `$offset`. Summed over the four offsets this
     * equals the unmasked call.
     *
     * @param array<int, int> $surplusBySeason
     */
    public static function rookieAt(int $draftRound, int $exp, int $offset, array $surplusBySeason): float
    {
        $mask = [];
        foreach (Horizon::OFFSETS as $k) {
            $mask[$k] = $k === $offset ? ($surplusBySeason[$k] ?? 0) : 0;
        }

        return RookieControl::assetPlaces(RookieControl::controlYearsLeft($draftRound, $exp), $mask);
    }

    /**
     * @param list<int> $receivedPids
     * @param list<int> $sentPids
     * @param array<int, float> $factors pid => F10 factor at this offset
     */
    private static function contracts(LeagueSnapshot $s, int $offset, array $receivedPids, array $sentPids, array $factors): float
    {
        $money = 0.0;
        $rookie = 0.0;
        foreach ([[$receivedPids, 1], [$sentPids, -1]] as [$pids, $sign]) {
            foreach ($pids as $pid) {
                $surplus = $s->surplusBySeason($pid);
                $money += $sign * $surplus[$offset] * $factors[$pid];
                $p = $s->players[$pid];
                $rookie += $sign * self::rookieAt($p->draftRound, $p->exp, $offset, $surplus);
            }
        }

        return $money * MoneyPlaces::placesPerSalaryUnit() + $rookie;
    }

    /**
     * Received pick worth minus sent pick worth. Open spots are the roster
     * spots left on the after-state roster; the ordinal counts received picks
     * in order.
     *
     * @param list<int> $receivedPicks
     * @param list<int> $sentPicks
     */
    private static function picks(LeagueSnapshot $s, TeamMode $mode, int $rosterAfter, array $receivedPicks, array $sentPicks): float
    {
        $open = Team::ROSTER_SPOTS_MAX - $rosterAfter;
        $sum = 0.0;
        $ordinal = 0;
        foreach ($receivedPicks as $id) {
            $ordinal++;
            $sum += self::pickWorth($s, $s->picks[$id], $mode, $open, $ordinal);
        }
        foreach ($sentPicks as $id) {
            $sum -= self::pickWorth($s, $s->picks[$id], $mode, $open, 1);
        }

        return $sum;
    }

    private static function pickWorth(LeagueSnapshot $s, PickAsset $pick, TeamMode $mode, int $open, int $ordinal): float
    {
        return PickWorth::worth($pick->round, $pick->yearsAway, $pick->originalTid, $s->draftIndex, $mode, $open, $ordinal);
    }

    private static function availability(InjuryStatus $status, TeamMode $mode): float
    {
        return $mode === TeamMode::WinNow ? $status->playoffWeight() : $status->availabilityWeight();
    }

    private static function clampRank(int $rank): int
    {
        return min(RankUtility::MAX_RANK, $rank);
    }
}
