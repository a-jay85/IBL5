<?php

declare(strict_types=1);

namespace OneOnOne;

/**
 * OneOnOnePlayerStats - Player statistics for a One-on-One game
 * 
 * Data transfer object containing all statistics tracked for a player
 * during a single One-on-One game.
 */
class OneOnOnePlayerStats
{
    public int $fieldGoalsMade = 0;
    public int $fieldGoalsAttempted = 0;
    public int $threePointersMade = 0;
    public int $threePointersAttempted = 0;
    public int $offensiveRebounds = 0;
    public int $totalRebounds = 0;
    public int $steals = 0;
    public int $blocks = 0;
    public int $turnovers = 0;
    public int $fouls = 0;
}
