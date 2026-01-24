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
     */
    public function getUpcomingFreeAgents(int $seasonEndingYear): array
    {
        $players = $this->repository->getActivePlayers();
        $freeAgents = [];

        foreach ($players as $player) {
            $draftyear = (int) ($player['draftyear'] ?? 0);
            $exp = (int) ($player['exp'] ?? 0);
            $cy = (int) ($player['cy'] ?? 0);
            $cyt = (int) ($player['cyt'] ?? 0);

            // Calculate year of free agency
            $yearOfFreeAgency = $draftyear + $exp + $cyt - $cy;

            if ($yearOfFreeAgency === $seasonEndingYear) {
                $freeAgents[] = [
                    'pid' => (int) ($player['pid'] ?? 0),
                    'tid' => (int) ($player['tid'] ?? 0),
                    'name' => $player['name'] ?? '',
                    'teamname' => $player['teamname'] ?? '',
                    'pos' => $player['pos'] ?? '',
                    'age' => (int) ($player['age'] ?? 0),
                    'r_fga' => (int) ($player['r_fga'] ?? 0),
                    'r_fgp' => (int) ($player['r_fgp'] ?? 0),
                    'r_fta' => (int) ($player['r_fta'] ?? 0),
                    'r_ftp' => (int) ($player['r_ftp'] ?? 0),
                    'r_tga' => (int) ($player['r_tga'] ?? 0),
                    'r_tgp' => (int) ($player['r_tgp'] ?? 0),
                    'r_orb' => (int) ($player['r_orb'] ?? 0),
                    'r_drb' => (int) ($player['r_drb'] ?? 0),
                    'r_ast' => (int) ($player['r_ast'] ?? 0),
                    'r_stl' => (int) ($player['r_stl'] ?? 0),
                    'r_blk' => (int) ($player['r_blk'] ?? 0),
                    'r_to' => (int) ($player['r_to'] ?? 0),
                    'r_foul' => (int) ($player['r_foul'] ?? 0),
                    'oo' => (int) ($player['oo'] ?? 0),
                    'do' => (int) ($player['do'] ?? 0),
                    'po' => (int) ($player['po'] ?? 0),
                    'to' => (int) ($player['to'] ?? 0),
                    'od' => (int) ($player['od'] ?? 0),
                    'dd' => (int) ($player['dd'] ?? 0),
                    'pd' => (int) ($player['pd'] ?? 0),
                    'td' => (int) ($player['td'] ?? 0),
                    'loyalty' => (int) ($player['loyalty'] ?? 0),
                    'winner' => (int) ($player['winner'] ?? 0),
                    'playingTime' => (int) ($player['playingTime'] ?? 0),
                    'security' => (int) ($player['security'] ?? 0),
                    'tradition' => (int) ($player['tradition'] ?? 0),
                ];
            }
        }

        return $freeAgents;
    }
}
