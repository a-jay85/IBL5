<?php

declare(strict_types=1);

namespace Trading;

use BaseMysqliRepository;
use Trading\Contracts\TradeExecutionRepositoryInterface;

/**
 * TradeExecutionRepository - Database operations for trade queue and execution
 *
 * Handles all queue-related database queries including inserting, retrieving,
 * executing, and clearing queued trade operations.
 *
 * @see TradeExecutionRepositoryInterface For method contracts
 * @see BaseMysqliRepository For base class documentation and error codes
 */
class TradeExecutionRepository extends BaseMysqliRepository implements TradeExecutionRepositoryInterface
{
    /**
     * Constructor - inherits from BaseMysqliRepository
     *
     * @param \mysqli $db Active mysqli connection
     * @throws \RuntimeException If connection is invalid (error code 1002)
     */
    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    /**
     * @see TradeExecutionRepositoryInterface::insertTradeQueue()
     */
    public function insertTradeQueue(string $operationType, array $params, string $tradeLine): int
    {
        $paramsJson = json_encode($params, JSON_THROW_ON_ERROR);
        return $this->execute(
            "INSERT INTO ibl_trade_queue (operation_type, params, tradeline) VALUES (?, ?, ?)",
            "sss",
            $operationType,
            $paramsJson,
            $tradeLine
        );
    }

    /**
     * @see TradeExecutionRepositoryInterface::getQueuedTrades()
     */
    public function getQueuedTrades(): array
    {
        /** @var list<array{id: int, operation_type: string, params: string, tradeline: string}> */
        return $this->fetchAll(
            "SELECT id, operation_type, params, tradeline FROM ibl_trade_queue ORDER BY id ASC"
        );
    }

    /**
     * @see TradeExecutionRepositoryInterface::executeQueuedPlayerTransfer()
     */
    public function executeQueuedPlayerTransfer(int $playerId, string $teamName, int $teamId): int
    {
        return $this->execute(
            "UPDATE ibl_plr SET teamname = ?, tid = ? WHERE pid = ?",
            "sii",
            $teamName,
            $teamId,
            $playerId
        );
    }

    /**
     * @see TradeExecutionRepositoryInterface::executeQueuedPickTransfer()
     */
    public function executeQueuedPickTransfer(int $pickId, string $newOwner): int
    {
        return $this->execute(
            "UPDATE ibl_draft_picks SET ownerofpick = ? WHERE pickid = ?",
            "si",
            $newOwner,
            $pickId
        );
    }

    /**
     * @see TradeExecutionRepositoryInterface::deleteQueuedTrade()
     */
    public function deleteQueuedTrade(int $queueId): int
    {
        return $this->execute(
            "DELETE FROM ibl_trade_queue WHERE id = ?",
            "i",
            $queueId
        );
    }

    /**
     * @see TradeExecutionRepositoryInterface::clearTradeQueue()
     */
    public function clearTradeQueue(): int
    {
        return $this->execute("TRUNCATE TABLE ibl_trade_queue");
    }

    /**
     * @see TradeExecutionRepositoryInterface::clearTradeInfo()
     */
    public function clearTradeInfo(): int
    {
        return $this->execute("DELETE FROM ibl_trade_info");
    }
}
