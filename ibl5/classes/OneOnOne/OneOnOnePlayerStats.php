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

    /**
     * Reset all statistics to zero
     */
    public function reset(): void
    {
        $this->fieldGoalsMade = 0;
        $this->fieldGoalsAttempted = 0;
        $this->threePointersMade = 0;
        $this->threePointersAttempted = 0;
        $this->offensiveRebounds = 0;
        $this->totalRebounds = 0;
        $this->steals = 0;
        $this->blocks = 0;
        $this->turnovers = 0;
        $this->fouls = 0;
    }

    /**
     * Get stats as an array for display
     * 
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'fgm' => $this->fieldGoalsMade,
            'fga' => $this->fieldGoalsAttempted,
            '3gm' => $this->threePointersMade,
            '3ga' => $this->threePointersAttempted,
            'orb' => $this->offensiveRebounds,
            'reb' => $this->totalRebounds,
            'stl' => $this->steals,
            'blk' => $this->blocks,
            'to' => $this->turnovers,
            'foul' => $this->fouls,
        ];
    }
}
