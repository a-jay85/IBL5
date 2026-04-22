<?php

declare(strict_types=1);

namespace FreeAgencyPreview;

use FreeAgencyPreview\Contracts\FreeAgencyPreviewServiceInterface;
use FreeAgencyPreview\Contracts\FreeAgencyPreviewRepositoryInterface;

/**
 * FreeAgencyPreviewService - Business logic for free agency calculations
 *
 * Calculates which players will become free agents at the end of the season.
 *
 * @see FreeAgencyPreviewServiceInterface For the interface contract
 *
 * @phpstan-import-type ActivePlayerRow from Contracts\FreeAgencyPreviewRepositoryInterface
 * @phpstan-import-type FreeAgentRow from Contracts\FreeAgencyPreviewServiceInterface
 */
class FreeAgencyPreviewService implements FreeAgencyPreviewServiceInterface
{
    private FreeAgencyPreviewRepositoryInterface $repository;

    public function __construct(FreeAgencyPreviewRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see FreeAgencyPreviewServiceInterface::getUpcomingFreeAgents()
     *
     * @return list<FreeAgentRow>
     */
    public function getUpcomingFreeAgents(int $seasonEndingYear): array
    {
        $players = $this->repository->getActivePlayers();
        /** @var list<FreeAgentRow> $freeAgents */
        $freeAgents = [];

        foreach ($players as $player) {
            $nextYear = ($player['cy'] ?? 0) + 1;
            $nextYearSalary = match ($nextYear) {
                1 => $player['cy1'],
                2 => $player['cy2'],
                3 => $player['cy3'],
                4 => $player['cy4'],
                5 => $player['cy5'],
                6 => $player['cy6'],
                default => 0,
            };

            if ($nextYearSalary === 0) {
                $freeAgents[] = [
                    'pid' => $player['pid'],
                    'teamid' => $player['teamid'],
                    'name' => $player['name'],
                    'teamname' => $player['teamname'],
                    'team_city' => $player['team_city'] ?? '',
                    'color1' => $player['color1'] ?? 'FFFFFF',
                    'color2' => $player['color2'] ?? '000000',
                    'pos' => $player['pos'],
                    'age' => $player['age'],
                    'r_fga' => $player['r_fga'],
                    'r_fgp' => $player['r_fgp'],
                    'r_fta' => $player['r_fta'],
                    'r_ftp' => $player['r_ftp'],
                    'r_3ga' => $player['r_3ga'],
                    'r_3gp' => $player['r_3gp'],
                    'r_orb' => $player['r_orb'],
                    'r_drb' => $player['r_drb'],
                    'r_ast' => $player['r_ast'],
                    'r_stl' => $player['r_stl'],
                    'r_blk' => $player['r_blk'],
                    'r_tvr' => $player['r_tvr'],
                    'r_foul' => $player['r_foul'],
                    'oo' => $player['oo'],
                    'r_drive_off' => $player['r_drive_off'],
                    'po' => $player['po'],
                    'r_trans_off' => $player['r_trans_off'],
                    'od' => $player['od'],
                    'dd' => $player['dd'],
                    'pd' => $player['pd'],
                    'td' => $player['td'],
                    'loyalty' => $player['loyalty'],
                    'winner' => $player['winner'],
                    'playingTime' => $player['playingTime'],
                    'security' => $player['security'],
                    'tradition' => $player['tradition'],
                ];
            }
        }

        return $freeAgents;
    }
}
