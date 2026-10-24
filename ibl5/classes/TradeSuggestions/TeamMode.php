<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Resolved team mode used by the trade suggestion engine.
 */
enum TeamMode
{
    case WinNow;
    case Rebuild;
}
