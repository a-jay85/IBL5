<?php

declare(strict_types=1);

namespace TradeSuggestions\Veto;

use TradeSuggestions\Engine\PickAsset;
use TradeSuggestions\Engine\ProfileDelta;
use TradeSuggestions\Engine\SideGain;
use TradeSuggestions\Engine\TeamState;
use TradeSuggestions\Profile\TeamSituation;
use TradeSuggestions\Projection\InjuryStatus;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\TeamMode;
use TradeSuggestions\Valuation\ContractValue;

/**
 * One side of a deal as the vetoes read it: who moves, the before and after
 * team states, and the money. Plain values; a veto never reaches back into
 * the snapshot.
 */
final readonly class SideView
{
    /**
     * @param list<ProjectionPlayer> $sent players at offset 0
     * @param list<ProjectionPlayer> $received players at offset 0
     * @param list<PickAsset> $sentPicks
     * @param list<PickAsset> $receivedPicks
     * @param array<int, TeamState> $before offset => state
     * @param array<int, TeamState> $after offset => state
     * @param array<int, array<int, int>> $surplusByPid pid => offset => contract surplus, for every sent and received player
     * @param array<int, ProfileDelta> $delta offset => delta
     * @param array<int, float> $pickWorthById pickId => worth in this side's mode, for every sent and received pick
     * @param array<int, float> $availabilityByPid pid => offset-0 injury availability weight, for every player on either team before or after
     * @param array<int, InjuryStatus> $injuryByPid pid => offset-0 status, for every received player
     */
    public function __construct(
        public int $tid,
        public TeamMode $mode,
        public array $sent,
        public array $received,
        public array $sentPicks,
        public array $receivedPicks,
        public array $before,
        public array $after,
        public SideGain $gain,
        public ?TeamSituation $situation,
        public array $surplusByPid,
        public array $delta,
        public array $pickWorthById,
        public array $availabilityByPid,
        public array $injuryByPid = [],
    ) {
    }

    /**
     * @return list<ProjectionPlayer>
     */
    public function receivedPlayersAged(int $min): array
    {
        return array_values(array_filter(
            $this->received,
            static fn (ProjectionPlayer $p): bool => $p->age >= $min,
        ));
    }

    /**
     * Contract money beyond the current season this side takes on, as a
     * positive number.
     */
    public function negativeSurplusAbsorbed(): int
    {
        $total = 0;
        foreach ($this->received as $p) {
            $total += ContractValue::negativeSurplusBeyondCurrent($this->surplusByPid[$p->pid] ?? []);
        }

        return $total;
    }

    /**
     * Payroll this side adds at offset 0, never below zero.
     */
    public function capRoomConsumed(): int
    {
        return max(0, $this->after[0]->payroll - $this->before[0]->payroll);
    }

    public function receivedPickWorth(): float
    {
        $sum = 0.0;
        foreach ($this->receivedPicks as $pick) {
            $sum += $this->pickWorthById[$pick->pickId] ?? 0.0;
        }

        return $sum;
    }
}
