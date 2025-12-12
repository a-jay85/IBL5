<?php

declare(strict_types=1);

namespace RookieOption\Contracts;

/**
 * RookieOptionRepositoryInterface - Contract for rookie option database operations
 * 
 * Defines the data access layer for rookie option transactions.
 * Handles contract updates for exercising rookie options.
 * 
 * @package RookieOption\Contracts
 */
interface RookieOptionRepositoryInterface
{
    /**
     * Updates a player's rookie option contract year
     * 
     * Sets the appropriate contract year salary based on draft round.
     * First round picks get their 4th year set, second round picks get their 3rd year.
     * 
     * @param int $playerID Player ID to update
     * @param int $draftRound Draft round (1 or 2)
     * @param int $extensionAmount Contract extension amount in thousands
     * @return bool True if update succeeded, false on database error
     * 
     * **Database Changes:**
     * - Draft round 1: Sets cy4 = extensionAmount
     * - Draft round 2: Sets cy3 = extensionAmount
     * 
     * **Behaviors:**
     * - Casts playerID and extensionAmount to integers
     * - Does not validate draft round (caller responsibility)
     */
    public function updatePlayerRookieOption(int $playerID, int $draftRound, int $extensionAmount): bool;
}
