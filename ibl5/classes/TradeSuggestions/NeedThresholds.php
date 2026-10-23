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

    /** Depth-chart minutes that mark a real rotation role. */
    public const int REAL_MINUTES = 15;

    /** Payroll this close under the hard cap counts as capped out. */
    public const int CAPPED_OUT_MARGIN = 150;

    /** Cap room that lets a team take on salary. */
    public const int ROOM_TO_ABSORB_MIN = 1500;

    /** How much next season's and the season after's overage count now. */
    public const float FUTURE_CAP_WEIGHT_NEXT = 0.35;
    public const float FUTURE_CAP_WEIGHT_AFTER = 0.15;

    /** A bad contract: big, long, old, and not earning it. */
    public const int BAD_CONTRACT_MIN_SALARY = 700;
    public const int BAD_CONTRACT_MIN_YEARS = 3;
    public const int BAD_CONTRACT_MIN_AGE = 30;
    public const float BAD_CONTRACT_RANK_SHARE = 0.67;

    /** A weak starter ranks in the bottom N of a slot with at least MIN_FIELD ranked starters. */
    public const int WEAK_STARTER_BOTTOM_N = 4;
    public const int WEAK_STARTER_MIN_FIELD = 8;

    /** Real-minute players at one slot that make a logjam. */
    public const int LOGJAM_MIN_PLAYERS = 3;

    /** Dead weight: under UNUSED_MINUTES depth-chart minutes on a deal of at least DEAD_WEIGHT_MIN_SALARY. */
    public const int UNUSED_MINUTES = 10;
    public const int DEAD_WEIGHT_MIN_SALARY = 600;

    /** A starter ranked this high or better at his slot is a top player. */
    public const int TOP_PLAYER_MAX_RANK = 5;

    /** Blended record below this is a losing team. */
    public const float LOSING_PCT = 0.45;

    /** Real-minute players that make a team deep enough to consolidate. */
    public const int CONSOLIDATE_MIN_DEPTH = 9;

    /** Players this age or younger count as young. */
    public const int YOUNG_MAX_AGE = 24;

    /** Mirrors the roster limit TradeValidator checks. */
    public const int ROSTER_MAX = 15;

    /** Candidate size limits for NeedPairMatcher. */
    public const int MAX_PICKS_PER_SIDE = 2;
    public const int MAX_PER_PAIRING = 4;
    public const int MAX_PER_PARTNER = 12;
}
