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
     */
    public function __construct(
        public int $tid,
        public array $players,
        public array $picks,
    ) {
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
        foreach ($this->players as $player) {
            if (!in_array($player->pid, $outPids, true)) {
                $players[] = $player;
            }
        }
        foreach ($inPlayers as $player) {
            $players[] = $player;
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

        return new TeamAssets($this->tid, $players, $picks);
    }
}
