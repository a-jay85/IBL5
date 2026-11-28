<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine\Contracts;

/**
 * The database-derived parts of the snapshot cache key.
 */
interface SnapshotKeyRepositoryInterface
{
    /**
     * SHA1 over every `ibl_plr` row's pid, team, ordinal, contract year,
     * injury and retired flag. `'empty'` when the table has no rows.
     */
    public function rosterDigest(): string;

    /**
     * Latest `ibl_trade_info.updated_at` joined to a SHA1 of round 1-2 draft
     * pick ownership (`<updated_at>|<digest>`), so a picks-only trade changes
     * the key. Either part is `''` when its table has no rows.
     */
    public function tradeMarker(): string;
}
