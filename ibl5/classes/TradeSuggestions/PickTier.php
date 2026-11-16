<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * How much a draft pick pays toward a sweetener. The value is the tier's
 * weight in NeedJudge's paid-tier sum.
 */
enum PickTier: int
{
    case Second = 1;
    case Late = 2;
    case High = 3;
    case Lottery = 4;
}
