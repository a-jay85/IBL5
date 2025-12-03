<?php

declare(strict_types=1);

namespace Team;

use UI\Components\StartersLineupComponent;
use Team\Contracts\TeamStatsServiceInterface;

/**
 * @see TeamStatsServiceInterface
 */
class TeamStatsService implements TeamStatsServiceInterface
{
    private $db;
    private $startersComponent;

    public function __construct($db)
    {
        $this->db = $db;
        $this->startersComponent = new StartersLineupComponent();
    }

    /**
     * @see TeamStatsServiceInterface::extractStartersData()
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
     * @see TeamStatsServiceInterface::getLastSimsStarters()
     */
    public function getLastSimsStarters($result, $team): string
    {
        $starters = $this->extractStartersData($result);
        return $this->startersComponent->render($starters, $team->color1, $team->color2);
    }
}
