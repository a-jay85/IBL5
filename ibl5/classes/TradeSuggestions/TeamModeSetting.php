<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Stored per-team trade mode setting (ibl_team_info.trade_mode).
 *
 * These three backing values are the only strings that ever reach SQL.
 */
enum TeamModeSetting: string
{
    case Auto = 'auto';
    case WinNow = 'win_now';
    case Rebuild = 'rebuild';
}
