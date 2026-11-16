<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Who starts at each slot for one team.
 *
 * chartReliable is false when the GM's depth chart lacks exactly one depth-1
 * starter per slot; the starters then come from the dc_minutes fallback in
 * SlotMapper::depthFor(). A slot with no eligible player is null.
 */
final readonly class TeamDepth
{
    /**
     * @param array<string, ?SuggestionPlayer> $starters Keyed PG SG SF PF C
     */
    public function __construct(
        public array $starters,
        public bool $chartReliable,
    ) {
    }

    public function starterAt(string $slot): ?SuggestionPlayer
    {
        return $this->starters[$slot] ?? null;
    }

    /**
     * The slot this player starts at, or null for a bench player.
     */
    public function slotOf(int $pid): ?string
    {
        foreach ($this->starters as $slot => $player) {
            if ($player !== null && $player->pid === $pid) {
                return $slot;
            }
        }

        return null;
    }
}
