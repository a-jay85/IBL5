<?php

declare(strict_types=1);

namespace Trading;

/**
 * Trade item types stored in ibl_trade_info.itemtype (VARCHAR)
 *
 * The database stores '0' for draft picks, '1' for players, and 'cash' for cash.
 * This enum encapsulates those values so callers don't need to remember the mapping.
 */
enum TradeItemType: string
{
    case DraftPick = '0';
    case Player = '1';
    case Cash = 'cash';

    /**
     * Convert a form/HTML integer asset type to the enum
     *
     * Trade offer forms send 0 (pick) or 1 (player) as integers.
     *
     * @throws \ValueError If the integer doesn't map to a valid type
     */
    public static function fromFormInt(int $assetType): self
    {
        return self::from((string) $assetType);
    }
}
