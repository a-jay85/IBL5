<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * TradeExecutionRepositoryInterface - Contract for trade queue/execution database operations
 *
 * Defines methods for managing the trade execution queue and bulk trade cleanup.
 * Extracted from TradingRepositoryInterface to follow single-responsibility principle.
 */
interface TradeExecutionRepositoryInterface
{
    /**
     * Insert trade into queue for deferred execution
     *
     * Stores structured data for safe execution via prepared statements.
     *
     * @param string $operationType Type of operation ('player_transfer' or 'pick_transfer')
     * @param array<string, int|string> $params Operation parameters (e.g., player_id, team_name, team_id)
     * @param string $tradeLine Trade description text
     * @return int Number of rows affected
     */
    public function insertTradeQueue(string $operationType, array $params, string $tradeLine): int;

    /**
     * Get all queued trade operations
     *
     * @return list<array{id: int, operation_type: string, params: string, tradeline: string}> Queue entries
     */
    public function getQueuedTrades(): array;

    /**
     * Execute a queued player transfer
     *
     * @param int $playerId Player ID
     * @param string $teamName New team name
     * @param int $teamId New team ID
     * @return int Number of affected rows
     */
    public function executeQueuedPlayerTransfer(int $playerId, string $teamName, int $teamId): int;

    /**
     * Execute a queued pick transfer
     *
     * @param int $pickId Pick ID
     * @param string $newOwner New owner team name
     * @return int Number of affected rows
     */
    public function executeQueuedPickTransfer(int $pickId, string $newOwner): int;

    /**
     * Delete a queued trade entry by ID
     *
     * @param int $queueId Queue entry ID
     * @return int Number of affected rows
     */
    public function deleteQueuedTrade(int $queueId): int;

    /**
     * Clear all queued trades (used at start of preseason)
     *
     * @return int Number of affected rows
     */
    public function clearTradeQueue(): int;

    /**
     * Clear all trade info
     *
     * @return int Number of rows affected
     */
    public function clearTradeInfo(): int;
}
