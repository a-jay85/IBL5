<?php

declare(strict_types=1);

namespace HistArchiver;

use HistArchiver\Contracts\HistArchiverRepositoryInterface;
use HistArchiver\Contracts\HistArchiverServiceInterface;

/**
 * @see HistArchiverServiceInterface
 */
class HistArchiverService implements HistArchiverServiceInterface
{
    /** The 16 game stat columns compared during validation */
    private const STAT_COLUMNS = [
        'games', 'minutes', 'fgm', 'fga', 'ftm', 'fta', 'tgm', 'tga',
        'orb', 'reb', 'ast', 'stl', 'tvr', 'blk', 'pf', 'pts',
    ];

    public function __construct(
        private readonly HistArchiverRepositoryInterface $repository,
    ) {
    }

    /**
     * @see HistArchiverServiceInterface::archiveSeason()
     */
    public function archiveSeason(int $year): HistArchiveResult
    {
        if (!$this->repository->hasChampionForYear($year)) {
            return HistArchiveResult::skipped();
        }

        $seasonTotals = $this->repository->getRegularSeasonTotals($year);
        $messages = [];
        $rowsUpserted = 0;
        $playersArchived = 0;

        foreach ($seasonTotals as $playerRow) {
            $pid = is_int($playerRow['pid']) ? $playerRow['pid'] : (int) (is_string($playerRow['pid']) ? $playerRow['pid'] : 0);
            $playerName = is_string($playerRow['name']) ? $playerRow['name'] : '';
            $ratings = $this->repository->getPlayerRatingsAndContract($pid);

            if ($ratings === null) {
                $messages[] = 'WARNING: Player ID ' . $pid . ' (' . $playerName . ') not found in ibl_plr — skipped';
                continue;
            }

            $data = $this->assembleHistData($playerRow, $ratings, $year);
            $rowsUpserted += $this->repository->upsertHistRow($data);
            $playersArchived++;
        }

        return HistArchiveResult::completed($rowsUpserted, $playersArchived, $messages);
    }

    /**
     * @see HistArchiverServiceInterface::validatePlrVsBoxScores()
     */
    public function validatePlrVsBoxScores(int $year): PlrValidationReport
    {
        $rows = $this->repository->getValidationComparison($year);
        $discrepancies = [];
        $matchCount = 0;

        $intVal = static fn (mixed $v): int => is_int($v) ? $v : (int) (is_string($v) ? $v : 0);

        foreach ($rows as $row) {
            $playerHasDiscrepancy = false;
            $pid = $intVal($row['pid'] ?? 0);
            $name = is_string($row['name'] ?? null) ? $row['name'] : '';

            foreach (self::STAT_COLUMNS as $col) {
                $histValue = $intVal($row['hist_' . $col] ?? 0);
                $bsValue = $intVal($row['bs_' . $col] ?? 0);

                if ($histValue !== $bsValue) {
                    $discrepancies[] = [
                        'pid' => $pid,
                        'name' => $name,
                        'column' => $col,
                        'hist_value' => $histValue,
                        'box_score_value' => $bsValue,
                    ];
                    $playerHasDiscrepancy = true;
                }
            }

            if (!$playerHasDiscrepancy) {
                $matchCount++;
            }
        }

        return new PlrValidationReport(
            totalPlayers: count($rows),
            matchCount: $matchCount,
            discrepancies: $discrepancies,
        );
    }

    /**
     * Assemble the data array for upsertHistRow from box score stats and player ratings.
     *
     * @param array<string, mixed> $playerRow Box score aggregate row
     * @param array{tid: int, r_2ga: int, r_2gp: int, r_fta: int, r_ftp: int, r_3ga: int, r_3gp: int, r_orb: int, r_drb: int, r_ast: int, r_stl: int, r_blk: int, r_tvr: int, r_oo: int, r_od: int, r_do: int, r_dd: int, r_po: int, r_pd: int, r_to: int, r_td: int, salary: int} $ratings Player ratings and contract from ibl_plr
     * @return array<string, int|string>
     */
    private function assembleHistData(array $playerRow, array $ratings, int $year): array
    {
        $intVal = static fn (mixed $v): int => is_int($v) ? $v : (int) (is_string($v) ? $v : 0);
        $strVal = static fn (mixed $v): string => is_string($v) ? $v : '';

        return [
            'pid' => $intVal($playerRow['pid'] ?? 0),
            'name' => $strVal($playerRow['name'] ?? ''),
            'year' => $year,
            'team' => $strVal($playerRow['team'] ?? ''),
            'teamid' => $ratings['tid'],
            'games' => $intVal($playerRow['games'] ?? 0),
            'minutes' => $intVal($playerRow['minutes'] ?? 0),
            'fgm' => $intVal($playerRow['fgm'] ?? 0),
            'fga' => $intVal($playerRow['fga'] ?? 0),
            'ftm' => $intVal($playerRow['ftm'] ?? 0),
            'fta' => $intVal($playerRow['fta'] ?? 0),
            'tgm' => $intVal($playerRow['tgm'] ?? 0),
            'tga' => $intVal($playerRow['tga'] ?? 0),
            'orb' => $intVal($playerRow['orb'] ?? 0),
            'reb' => $intVal($playerRow['reb'] ?? 0),
            'ast' => $intVal($playerRow['ast'] ?? 0),
            'stl' => $intVal($playerRow['stl'] ?? 0),
            'blk' => $intVal($playerRow['blk'] ?? 0),
            'tvr' => $intVal($playerRow['tvr'] ?? 0),
            'pf' => $intVal($playerRow['pf'] ?? 0),
            'pts' => $intVal($playerRow['pts'] ?? 0),
            'r_2ga' => $ratings['r_2ga'],
            'r_2gp' => $ratings['r_2gp'],
            'r_fta' => $ratings['r_fta'],
            'r_ftp' => $ratings['r_ftp'],
            'r_3ga' => $ratings['r_3ga'],
            'r_3gp' => $ratings['r_3gp'],
            'r_orb' => $ratings['r_orb'],
            'r_drb' => $ratings['r_drb'],
            'r_ast' => $ratings['r_ast'],
            'r_stl' => $ratings['r_stl'],
            'r_blk' => $ratings['r_blk'],
            'r_tvr' => $ratings['r_tvr'],
            'r_oo' => $ratings['r_oo'],
            'r_od' => $ratings['r_od'],
            'r_do' => $ratings['r_do'],
            'r_dd' => $ratings['r_dd'],
            'r_po' => $ratings['r_po'],
            'r_pd' => $ratings['r_pd'],
            'r_to' => $ratings['r_to'],
            'r_td' => $ratings['r_td'],
            'salary' => $ratings['salary'],
        ];
    }
}
