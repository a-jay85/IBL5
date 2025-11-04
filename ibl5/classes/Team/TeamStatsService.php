<?php

namespace Team;

use Player\Player;
use UI\Components\StartersLineupComponent;

/**
 * TeamStatsService - Handles statistical calculations for teams
 * 
 * This service processes player statistics and generates team-level statistics
 * and last sim's starting lineup information.
 */
class TeamStatsService
{
    private $db;
    private $startersComponent;

    public function __construct($db)
    {
        $this->db = $db;
        $this->startersComponent = new StartersLineupComponent();
    }

    /**
     * Extract starting lineup data from database result
     * 
     * @param mixed $result Database result object
     * @return array Array of starters keyed by position
     */
    public function extractStartersData($result): array
    {
        $num = $this->db->sql_numrows($result);
        $starters = [
            'PG' => ['name' => null, 'pid' => null],
            'SG' => ['name' => null, 'pid' => null],
            'SF' => ['name' => null, 'pid' => null],
            'PF' => ['name' => null, 'pid' => null],
            'C' => ['name' => null, 'pid' => null]
        ];
        
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];
        
        for ($i = 0; $i < $num; $i++) {
            foreach ($positions as $position) {
                $depthField = $position . 'Depth';
                if ($this->db->sql_result($result, $i, $depthField) == 1) {
                    $starters[$position]['name'] = $this->db->sql_result($result, $i, "name");
                    $starters[$position]['pid'] = $this->db->sql_result($result, $i, "pid");
                }
            }
        }
        
        return $starters;
    }

    /**
     * Get last sim's starting lineup for a team
     * Returns HTML table with starting 5 players
     * 
     * @param mixed $result Database result object
     * @param object $team Team object with color1 and color2 properties
     * @return string HTML representation of starting lineup
     */
    public function getLastSimsStarters($result, $team): string
    {
        $starters = $this->extractStartersData($result);
        return $this->startersComponent->render($starters, $team->color1, $team->color2);
    }
}
