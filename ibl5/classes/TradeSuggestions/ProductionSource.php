<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Which season a player's production line came from.
 */
enum ProductionSource: string
{
    case CurrentSeason = 'current';
    case LastSeason = 'last';
}
