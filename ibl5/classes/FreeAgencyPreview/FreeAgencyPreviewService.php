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
    public function getUpcomingFreeAgents(int $targetEndingYear, int $currentEndingYear): array
    {
        $players = $this->repository->getActivePlayers();
        /** @var list<FreeAgentRow> $freeAgents */
        $freeAgents = [];

        $offset = $targetEndingYear - $currentEndingYear;

        foreach ($players as $player) {
            $targetContractYear = ($player['cy'] ?? 0) + 1 + $offset;
            $targetSalary = match ($targetContractYear) {
                1 => $player['salary_yr1'],
                2 => $player['salary_yr2'],
                3 => $player['salary_yr3'],
                4 => $player['salary_yr4'],
                5 => $player['salary_yr5'],
                6 => $player['salary_yr6'],
                default => 0,
            };

            if ($targetSalary === 0) {
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
                    'playing_time' => $player['playing_time'],
                    'security' => $player['security'],
                    'tradition' => $player['tradition'],
                ];
            }
        }

        return $freeAgents;
    }

    /**
     * Resolve and clamp a raw request value to a valid target ending year.
     *
     * Clamps to [$currentEndingYear, $currentEndingYear + 5] (the salary_yr1..6
     * horizon supports offsets 0..5). Missing, non-numeric, or below-range input
     * resolves to the current year; above-range clamps to the max horizon.
     *
     * @param string|null $raw The raw request value (e.g. $_GET['year']), already narrowed to ?string
     * @param int $currentEndingYear The current season ending year
     * @return int A target ending year within [$currentEndingYear, $currentEndingYear + 5]
     */
    public static function resolveRequestedYear(?string $raw, int $currentEndingYear): int
    {
        if ($raw === null || !is_numeric($raw)) {
            return $currentEndingYear;
        }

        $requested = (int) $raw;
        $maxYear = $currentEndingYear + 5;

        if ($requested < $currentEndingYear) {
            return $currentEndingYear;
        }
        if ($requested > $maxYear) {
            return $maxYear;
        }

        return $requested;
    }
}
