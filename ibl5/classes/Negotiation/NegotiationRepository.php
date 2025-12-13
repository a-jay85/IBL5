<?php

declare(strict_types=1);

namespace Negotiation;

use BaseMysqliRepository;
use Negotiation\Contracts\NegotiationRepositoryInterface;

/**
 * @see NegotiationRepositoryInterface
 * @see BaseMysqliRepository
 */
class NegotiationRepository extends BaseMysqliRepository implements NegotiationRepositoryInterface
{
    public function __construct(object $db)
    {
        parent::__construct($db);
    }

    /**
     * @see NegotiationRepositoryInterface::getTeamPerformance()
     */
    public function getTeamPerformance(string $teamName): array
    {
        $result = $this->fetchOne(
            "SELECT Contract_Wins, Contract_Losses, Contract_AvgW, Contract_AvgL 
             FROM ibl_team_info 
             WHERE team_name = ?",
            "s",
            $teamName
        );
        
        if ($result === null) {
            return [
                'Contract_Wins' => 41,
                'Contract_Losses' => 41,
                'Contract_AvgW' => 41,
                'Contract_AvgL' => 41,
            ];
        }
        
        return [
            'Contract_Wins' => (int) ($result['Contract_Wins'] ?? 41),
            'Contract_Losses' => (int) ($result['Contract_Losses'] ?? 41),
            'Contract_AvgW' => (int) ($result['Contract_AvgW'] ?? 41),
            'Contract_AvgL' => (int) ($result['Contract_AvgL'] ?? 41),
        ];
    }

    /**
     * @see NegotiationRepositoryInterface::getPositionSalaryCommitment()
     */
    public function getPositionSalaryCommitment(string $teamName, string $position, string $excludePlayerName): int
    {
        $rows = $this->fetchAll(
            "SELECT cy, cy2, cy3, cy4, cy5, cy6 
             FROM ibl_plr 
             WHERE teamname = ? 
               AND pos = ? 
               AND name != ?",
            "sss",
            $teamName,
            $position,
            $excludePlayerName
        );
        
        $totalCommitted = 0;
        
        foreach ($rows as $row) {
            $currentYear = (int) ($row['cy'] ?? 0);
            
            // Look at salary committed next year (for extensions)
            switch ($currentYear) {
                case 1:
                    $totalCommitted += (int) ($row['cy2'] ?? 0);
                    break;
                case 2:
                    $totalCommitted += (int) ($row['cy3'] ?? 0);
                    break;
                case 3:
                    $totalCommitted += (int) ($row['cy4'] ?? 0);
                    break;
                case 4:
                    $totalCommitted += (int) ($row['cy5'] ?? 0);
                    break;
                case 5:
                    $totalCommitted += (int) ($row['cy6'] ?? 0);
                    break;
            }
        }
        
        return $totalCommitted;
    }

    /**
     * @see NegotiationRepositoryInterface::getTeamCapSpaceNextSeason()
     */
    public function getTeamCapSpaceNextSeason(string $teamName): int
    {
        $rows = $this->fetchAll(
            "SELECT cy, cy2, cy3, cy4, cy5, cy6 
             FROM ibl_plr 
             WHERE teamname = ? 
               AND retired = '0'",
            "s",
            $teamName
        );
        
        $capSpace = \League::HARD_CAP_MAX;
        
        foreach ($rows as $row) {
            $currentYear = (int) ($row['cy'] ?? 0);
            
            // Look at salary committed next year
            switch ($currentYear) {
                case 1:
                    $capSpace -= (int) ($row['cy2'] ?? 0);
                    break;
                case 2:
                    $capSpace -= (int) ($row['cy3'] ?? 0);
                    break;
                case 3:
                    $capSpace -= (int) ($row['cy4'] ?? 0);
                    break;
                case 4:
                    $capSpace -= (int) ($row['cy5'] ?? 0);
                    break;
                case 5:
                    $capSpace -= (int) ($row['cy6'] ?? 0);
                    break;
            }
        }
        
        return $capSpace;
    }
}
