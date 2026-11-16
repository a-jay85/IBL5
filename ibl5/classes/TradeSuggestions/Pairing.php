<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * The deal shape that answers one team's need with another's.
 */
enum Pairing
{
    case SalaryDump;
    case VeteranForFuture;
    case SurplusForHole;
    case Consolidation;
    case LengthForExpiring;
}
