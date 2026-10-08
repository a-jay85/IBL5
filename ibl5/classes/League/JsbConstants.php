<?php

declare(strict_types=1);

namespace League;

/**
 * Jump Shot Basketball engine constants shared across modules.
 * Not parser code: .plr/.sch/.trn file parsing lives in JsbParser/.
 */
class JsbConstants
{
    const PLAYER_POSITIONS = ['PG', 'SG', 'SF', 'PF', 'C'];
    const PLAYOFF_MONTH = 22;
    const WAIVERS_ORDINAL = 960; // Any player with an ordinal > 960 is considered by JSB to be on waivers
}