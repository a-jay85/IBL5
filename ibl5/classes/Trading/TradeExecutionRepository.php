<?php

declare(strict_types=1);

namespace Trading;

use Database\BaseMysqliRepository;
use Trading\Contracts\TradeExecutionRepositoryInterface;

/**
 * TradeExecutionRepository - Database operations for trade-info cleanup
 *
 * Handles clearing the ibl_trade_info table.
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
     * @see TradeExecutionRepositoryInterface::clearTradeInfo()
     */
    public function clearTradeInfo(): int
    {
        return $this->execute("DELETE FROM `ibl_trade_info`");
    }
}
