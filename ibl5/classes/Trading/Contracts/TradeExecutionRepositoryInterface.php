<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * TradeExecutionRepositoryInterface - Contract for trade-info cleanup database operations
 *
 * Defines the method for bulk trade-info cleanup.
 * Extracted from the original TradingRepositoryInterface to follow single-responsibility principle.
 */
interface TradeExecutionRepositoryInterface
{
    /**
     * Clear all trade info
     *
     * @return int Number of rows affected
     */
    public function clearTradeInfo(): int;
}
