<?php

declare(strict_types=1);

namespace Negotiation;

use BaseMysqliRepository;
use Negotiation\Contracts\NegotiationRepositoryInterface;

/**
 * @see NegotiationRepositoryInterface
 * @see BaseMysqliRepository
 *
 * @phpstan-type ContractRow array{cy: int, cy2: int, cy3: int, cy4: int, cy5: int, cy6: int}
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
        /** @var array{Contract_Wins?: int, Contract_Losses?: int, Contract_AvgW?: int, Contract_AvgL?: int}|null $result */
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
            'Contract_Wins' => $result['Contract_Wins'] ?? 41,
            'Contract_Losses' => $result['Contract_Losses'] ?? 41,
            'Contract_AvgW' => $result['Contract_AvgW'] ?? 41,
            'Contract_AvgL' => $result['Contract_AvgL'] ?? 41,
        ];
    }

    /**
     * @see NegotiationRepositoryInterface::getPositionSalaryCommitment()
     */
    public function getPositionSalaryCommitment(string $teamName, string $position, string $excludePlayerName): int
    {
        /** @var list<ContractRow> $rows */
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
            // Look at salary committed next year (for extensions)
            switch ($row['cy']) {
                case 1:
                    $totalCommitted += $row['cy2'];
                    break;
                case 2:
                    $totalCommitted += $row['cy3'];
                    break;
                case 3:
                    $totalCommitted += $row['cy4'];
                    break;
                case 4:
                    $totalCommitted += $row['cy5'];
                    break;
                case 5:
                    $totalCommitted += $row['cy6'];
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
        /** @var list<ContractRow> $rows */
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
            // Look at salary committed next year
            switch ($row['cy']) {
                case 1:
                    $capSpace -= $row['cy2'];
                    break;
                case 2:
                    $capSpace -= $row['cy3'];
                    break;
                case 3:
                    $capSpace -= $row['cy4'];
                    break;
                case 4:
                    $capSpace -= $row['cy5'];
                    break;
                case 5:
                    $capSpace -= $row['cy6'];
                    break;
            }
        }

        return $capSpace;
    }

    /**
     * @see NegotiationRepositoryInterface::isFreeAgencyActive()
     */
    public function isFreeAgencyActive(): bool
    {
        /** @var array{active: int}|null $result */
        $result = $this->fetchOne(
            "SELECT active FROM nuke_modules WHERE title = ?",
            "s",
            "Free_Agency"
        );

        return $result !== null && isset($result['active']) && $result['active'] === 1;
    }

    /**
     * @see NegotiationRepositoryInterface::getMarketMaximums()
     *
     * @return array{fga: int, fgp: int, fta: int, ftp: int, tga: int, tgp: int, orb: int, drb: int, ast: int, stl: int, to: int, blk: int, foul: int, oo: int, od: int, do: int, dd: int, po: int, pd: int, td: int}
     */
    public function getMarketMaximums(): array
    {
        $stats = [
            'r_fga' => 'fga',
            'r_fgp' => 'fgp',
            'r_fta' => 'fta',
            'r_ftp' => 'ftp',
            'r_tga' => 'tga',
            'r_tgp' => 'tgp',
            'r_orb' => 'orb',
            'r_drb' => 'drb',
            'r_ast' => 'ast',
            'r_stl' => 'stl',
            'r_to' => 'to',
            'r_blk' => 'blk',
            'r_foul' => 'foul',
            'oo' => 'oo',
            'od' => 'od',
            'do' => 'do',
            'dd' => 'dd',
            'po' => 'po',
            'pd' => 'pd',
            'to' => 'to',
            'td' => 'td'
        ];

        /** @var array{fga: int, fgp: int, fta: int, ftp: int, tga: int, tgp: int, orb: int, drb: int, ast: int, stl: int, to: int, blk: int, foul: int, oo: int, od: int, do: int, dd: int, po: int, pd: int, td: int} $maximums */
        $maximums = [];
        foreach ($stats as $dbColumn => $key) {
            /** @var array{max_value?: int|null}|null $result */
            $result = $this->fetchOne(
                "SELECT MAX(`$dbColumn`) as max_value FROM ibl_plr"
            );
            $maxVal = $result['max_value'] ?? null;
            $maximums[$key] = (is_int($maxVal) && $maxVal > 0) ? $maxVal : 1;
        }

        return $maximums;
    }
}