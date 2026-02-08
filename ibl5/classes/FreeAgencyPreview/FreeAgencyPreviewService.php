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
            $draftyear = $player['draftyear'];
            $exp = $player['exp'];
            $cy = $player['cy'];
            $cyt = $player['cyt'];

            // Calculate year of free agency
            $yearOfFreeAgency = $draftyear + $exp + $cyt - $cy;

            if ($yearOfFreeAgency === $seasonEndingYear) {
                $freeAgents[] = [
                    'pid' => $player['pid'],
                    'tid' => $player['tid'],
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
                    'r_tga' => $player['r_tga'],
                    'r_tgp' => $player['r_tgp'],
                    'r_orb' => $player['r_orb'],
                    'r_drb' => $player['r_drb'],
                    'r_ast' => $player['r_ast'],
                    'r_stl' => $player['r_stl'],
                    'r_blk' => $player['r_blk'],
                    'r_to' => $player['r_to'],
                    'r_foul' => $player['r_foul'],
                    'oo' => $player['oo'],
                    'do' => $player['do'],
                    'po' => $player['po'],
                    'to' => $player['to'],
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
