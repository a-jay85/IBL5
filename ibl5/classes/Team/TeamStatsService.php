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
    private $startersComponent;

    public function __construct()
    {
        $this->startersComponent = new StartersLineupComponent();
    }

    /**
     * @see TeamStatsServiceInterface::extractStartersData()
     */
    public function extractStartersData($result): array
    {
        // Handle different types of input
        if (is_array($result)) {
            $players = $result;
        } elseif (method_exists($result, 'fetchAssoc')) {
            // MockDatabaseResult in tests
            $players = [];
            while ($row = $result->fetchAssoc()) {
                $players[] = $row;
            }
        } elseif ($result instanceof \mysqli_result) {
            // Real mysqli result
            $players = [];
            while ($row = $result->fetch_assoc()) {
                $players[] = $row;
            }
        } else {
            // Fallback to array
            $players = [];
        }
        
        $starters = [
            'PG' => ['name' => null, 'pid' => null],
            'SG' => ['name' => null, 'pid' => null],
            'SF' => ['name' => null, 'pid' => null],
            'PF' => ['name' => null, 'pid' => null],
            'C' => ['name' => null, 'pid' => null]
        ];
        
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];
        
        foreach ($players as $player) {
            foreach ($positions as $position) {
                $depthField = $position . 'Depth';
                if (isset($player[$depthField]) && $player[$depthField] == 1) {
                    $starters[$position]['name'] = $player['name'] ?? null;
                    $starters[$position]['pid'] = $player['pid'] ?? null;
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
