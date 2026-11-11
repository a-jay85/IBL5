<?php

declare(strict_types=1);

namespace TradeSuggestions\Projection;

/**
 * The F2 horizon. Offset 0 is the current season (the upcoming season when
 * contract years advance); offsets 1-3 follow.
 */
final class Horizon
{
    public const int SEASONS = 4;

    /** @var list<int> */
    public const array OFFSETS = [0, 1, 2, 3];
}
