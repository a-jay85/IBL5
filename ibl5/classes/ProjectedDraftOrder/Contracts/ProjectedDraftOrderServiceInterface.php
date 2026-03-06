<?php

declare(strict_types=1);

namespace ProjectedDraftOrder\Contracts;

/**
 * @phpstan-type DraftSlot array{pick: int, teamId: int, teamName: string, wins: int, losses: int, color1: string, color2: string, ownerId: int, ownerName: string, ownerColor1: string, ownerColor2: string, isTraded: bool, notes: string, movement: int, player: string}
 * @phpstan-type ProjectedDraftOrderResult array{round1: list<DraftSlot>, round2: list<DraftSlot>}
 * @see \ProjectedDraftOrder\ProjectedDraftOrderService
 */
interface ProjectedDraftOrderServiceInterface
{
    /**
     * Calculate the projected draft order based on current standings.
     *
     * Returns two rounds of draft picks with ownership information.
     * Picks 1-12: non-playoff teams (worst record first).
     * Picks 13-28: playoff teams (worst record first).
     *
     * @return ProjectedDraftOrderResult
     */
    public function calculateDraftOrder(int $seasonYear): array;

    /**
     * Get the final draft order if finalized, otherwise the projected order.
     *
     * @return ProjectedDraftOrderResult
     */
    public function getFinalOrProjectedDraftOrder(int $seasonYear): array;

    /**
     * Save the lottery order (picks 1-12) and append projected picks 13-28.
     *
     * @param list<int> $lotteryTeamIds Ordered list of 12 team IDs for picks 1-12
     */
    public function saveLotteryOrder(int $seasonYear, array $lotteryTeamIds): void;
}
