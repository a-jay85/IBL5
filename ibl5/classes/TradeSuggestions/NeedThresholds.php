<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Every tunable number the needs engine reads, in one place.
 *
 * Salaries are in thousands, like ibl_plr.salary_yr*.
 */
final class NeedThresholds
{
    /** A season line counts only past this many minutes (about ten 40-minute games). */
    public const int MIN_SAMPLE_MINUTES = 400;
}
