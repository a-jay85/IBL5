<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * TradeProcessorInterface - Trade acceptance and processing
 *
 * Handles the execution of accepted trades including player transfers,
 * draft pick transfers, cash transactions, news stories, and notifications.
 * 
 * @package Trading\Contracts
 */
interface TradeProcessorInterface
{
    /**
     * Process a complete trade acceptance
     *
     * Executes all trade items (players, picks, cash), creates a news story,
     * sends notifications, and cleans up trade data.
     *
     * @param int $offerId Trade offer ID to process
     * @return array{success: bool, error?: string, storytext?: string, storytitle?: string}
     *         Result:
     *         - 'success': bool - Whether trade was processed successfully
     *         - 'error': string - Error message if no trade data found
     *         - 'storytext': string - Full trade description text on success
     *         - 'storytitle': string - News story title on success
     *
     * IMPORTANT BEHAVIORS:
     *  - Fetches all trade items from ibl_trade_info table
     *  - Processes each item: player transfers, pick transfers, cash transactions
     *  - For players: Updates teamname and tid in ibl_plr
     *  - For picks: Updates ownerofpick in ibl_draft_picks
     *  - For cash: Creates cash transaction records via CashTransactionHandler
     *  - Creates news story with category ID 2, topic ID 31
     *  - Sends email to ibldepthcharts@gmail.com (production only)
     *  - Posts to Discord #trades and #general-chat channels
     *  - Queues queries during Playoffs/Draft/Free Agency phases
     *  - Deletes trade data from ibl_trade_info and ibl_trade_cash
     *
     * Trade Queue:
     *  During certain season phases, trade queries are queued in ibl_trade_queue
     *  rather than executed immediately to prevent roster conflicts.
     */
    public function processTrade(int $offerId): array;
}
