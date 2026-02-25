<?php

declare(strict_types=1);

namespace DraftOrder\Contracts;

/**
 * @phpstan-type DraftSlot array{pick: int, teamId: int, teamName: string, wins: int, losses: int, color1: string, color2: string, ownerId: int, ownerName: string, ownerColor1: string, ownerColor2: string, isTraded: bool, notes: string}
 * @phpstan-type DraftOrderResult array{round1: list<DraftSlot>, round2: list<DraftSlot>}
 * @see \DraftOrder\DraftOrderService
 */
interface DraftOrderServiceInterface
{
    /**
     * Calculate the projected draft order based on current standings.
     *
     * Returns two rounds of draft picks with ownership information.
     * Picks 1-12: non-playoff teams (worst record first).
     * Picks 13-28: playoff teams (worst record first).
     *
     * @return DraftOrderResult
     */
    public function calculateDraftOrder(int $seasonYear): array;
}
