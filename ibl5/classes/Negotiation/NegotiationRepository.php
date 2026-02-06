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
        /** @var array{fga: int|null, fgp: int|null, fta: int|null, ftp: int|null, tga: int|null, tgp: int|null, orb: int|null, drb: int|null, ast: int|null, stl: int|null, r_to: int|null, blk: int|null, foul: int|null, oo: int|null, od: int|null, do: int|null, dd: int|null, po: int|null, pd: int|null, td: int|null}|null $result */
        $result = $this->fetchOne(
            "SELECT
                MAX(`r_fga`) AS fga,
                MAX(`r_fgp`) AS fgp,
                MAX(`r_fta`) AS fta,
                MAX(`r_ftp`) AS ftp,
                MAX(`r_tga`) AS tga,
                MAX(`r_tgp`) AS tgp,
                MAX(`r_orb`) AS orb,
                MAX(`r_drb`) AS drb,
                MAX(`r_ast`) AS ast,
                MAX(`r_stl`) AS stl,
                MAX(`r_to`) AS r_to,
                MAX(`r_blk`) AS blk,
                MAX(`r_foul`) AS foul,
                MAX(`oo`) AS oo,
                MAX(`od`) AS od,
                MAX(`do`) AS `do`,
                MAX(`dd`) AS dd,
                MAX(`po`) AS po,
                MAX(`pd`) AS pd,
                MAX(`td`) AS td
            FROM ibl_plr"
        );

        if ($result === null) {
            return [
                'fga' => 1, 'fgp' => 1, 'fta' => 1, 'ftp' => 1,
                'tga' => 1, 'tgp' => 1, 'orb' => 1, 'drb' => 1,
                'ast' => 1, 'stl' => 1, 'to' => 1, 'blk' => 1,
                'foul' => 1, 'oo' => 1, 'od' => 1, 'do' => 1,
                'dd' => 1, 'po' => 1, 'pd' => 1, 'td' => 1,
            ];
        }

        $ensurePositive = static function (int|null $val): int {
            return (is_int($val) && $val > 0) ? $val : 1;
        };

        return [
            'fga' => $ensurePositive($result['fga']),
            'fgp' => $ensurePositive($result['fgp']),
            'fta' => $ensurePositive($result['fta']),
            'ftp' => $ensurePositive($result['ftp']),
            'tga' => $ensurePositive($result['tga']),
            'tgp' => $ensurePositive($result['tgp']),
            'orb' => $ensurePositive($result['orb']),
            'drb' => $ensurePositive($result['drb']),
            'ast' => $ensurePositive($result['ast']),
            'stl' => $ensurePositive($result['stl']),
            'to' => $ensurePositive($result['r_to']),
            'blk' => $ensurePositive($result['blk']),
            'foul' => $ensurePositive($result['foul']),
            'oo' => $ensurePositive($result['oo']),
            'od' => $ensurePositive($result['od']),
            'do' => $ensurePositive($result['do']),
            'dd' => $ensurePositive($result['dd']),
            'po' => $ensurePositive($result['po']),
            'pd' => $ensurePositive($result['pd']),
            'td' => $ensurePositive($result['td']),
        ];
    }
}