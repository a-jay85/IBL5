<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * One team's tradeable assets as seen by the suggestion engine.
 */
final readonly class TeamAssets
{
    /**
     * @param list<SuggestionPlayer> $players
     * @param list<SuggestionPick> $picks
     * @param int|null $rosterCount Players the roster-limit check counts; null counts $players
     * @param int|null $capRoom Salary this season before the hard cap (negative when over it); null skips the check
     */
    public function __construct(
        public int $tid,
        public array $players,
        public array $picks,
        public ?int $rosterCount = null,
        public ?int $capRoom = null,
    ) {
    }

    public function rosterCount(): int
    {
        return $this->rosterCount ?? count($this->players);
    }

    /**
     * Return the assets this team would hold after a trade. Never mutates $this.
     *
     * @param list<int> $outPids
     * @param list<int> $outPickIds
     * @param list<SuggestionPlayer> $inPlayers
     * @param list<SuggestionPick> $inPicks
     */
    public function afterTrade(array $outPids, array $outPickIds, array $inPlayers, array $inPicks): TeamAssets
    {
        $players = [];
        $salaryOut = 0;
        foreach ($this->players as $player) {
            if (!in_array($player->pid, $outPids, true)) {
                $players[] = $player;
            } else {
                $salaryOut += $player->currentSalary;
            }
        }
        $salaryIn = 0;
        foreach ($inPlayers as $player) {
            $players[] = $player;
            $salaryIn += $player->currentSalary;
        }

        $picks = [];
        foreach ($this->picks as $pick) {
            if (!in_array($pick->pickId, $outPickIds, true)) {
                $picks[] = $pick;
            }
        }
        foreach ($inPicks as $pick) {
            $picks[] = $pick;
        }

        $removed = count($this->players) + count($inPlayers) - count($players);
        $rosterCount = $this->rosterCount === null ? null : $this->rosterCount - $removed + count($inPlayers);

        $capRoom = $this->capRoom === null ? null : $this->capRoom + $salaryOut - $salaryIn;

        return new TeamAssets($this->tid, $players, $picks, $rosterCount, $capRoom);
    }
}
