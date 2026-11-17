<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

use PlrParser\Contracts\PlrExportRepositoryInterface;

/**
 * Interface for database queries needed for JSB file export.
 *
 * Inherits the .plr read (getAllPlayerChangeableFields) from PlrExportRepositoryInterface
 * and adds the .trn read (getCompletedTradeItems).
 */
interface JsbExportRepositoryInterface extends PlrExportRepositoryInterface
{
    /**
     * Get completed trade transactions for TRN export, filtered by season start date.
     *
     * @param string $seasonStartDate ISO date string (e.g., '2025-07-01') — only trades
     *                                 created on or after this date are included
     * @return list<array{
     *     tradeofferid: int,
     *     itemid: int,
     *     itemtype: string,
     *     trade_from: string,
     *     trade_to: string,
     *     created_at: string,
     *     pick_year: int|null,
     *     pick_round: int|null,
     *     pick_teamid: int|null
     * }> The pick_* fields come from `ibl_draft_picks` for a draft pick item and are null otherwise
     */
    public function getCompletedTradeItems(string $seasonStartDate): array;
}
