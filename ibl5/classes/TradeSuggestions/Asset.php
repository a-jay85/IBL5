<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * What a need gives up or asks for in a trade.
 */
enum Asset
{
    case Salary;
    case Sweetener;
    case LessSalary;
    case ShorterContract;
    case CapRoom;
    case RosterSpot;
    case Picks;
    case YoungPlayers;
    case Veterans;
    case Surplus;
    case Starter;
}
