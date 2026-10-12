<?php

declare(strict_types=1);

namespace PlrParser;

use League\LeagueContext;
use PlrParser\Contracts\PlrParserRepositoryInterface;

/**
 * Repository for PLR file database operations using prepared statements.
 *
 * Handles upserts into `ibl_plr` and ibl_hist tables.
 * League-aware: resolves table names through LeagueContext when provided.
 */
class PlrParserRepository extends \Database\BaseMysqliRepository implements PlrParserRepositoryInterface
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @see PlrParserRepositoryInterface::upsertPlayer()
     *
     * @param array<string, int|string|float> $data
     */
    public function upsertPlayer(array $data): int
    {
        $updateClauses = [];
        $types = '';
        $values = [];
        foreach (self::PLAYER_COLUMNS as $column => $key) {
            if ($column !== 'pid') {
                $updateClauses[] = '`' . $column . '` = VALUES(`' . $column . '`)';
            }
            if ($column === 'name' || $column === 'pos') {
                $types .= 's';
                $values[] = (string) $data[$key];
            } else {
                $types .= 'i';
                $values[] = (int) $data[$key];
            }
        }

        $colList = implode(', ', array_map(
            static fn (string $c): string => '`' . $c . '`',
            array_keys(self::PLAYER_COLUMNS),
        ));
        $placeholders = implode(', ', array_fill(0, count(self::PLAYER_COLUMNS), '?'));

        // $colList / $placeholders derive from the fixed PLAYER_COLUMNS constant
        // (backticked identifiers / '?' placeholders) — concatenate, not interpolate.
        // `retired` is inserted as 0 and left out of the update, so a re-parse never
        // un-retires a player.
        $query = 'INSERT INTO `ibl_plr` (' . $colList . ', `retired`) VALUES (' . $placeholders . ', 0)' // @phpstan-ignore ibl.sqlStringConcatenation (identifiers come from the PLAYER_COLUMNS constant)
            . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updateClauses); // @phpstan-ignore ibl.sqlStringConcatenation (identifiers come from the PLAYER_COLUMNS constant)

        return $this->execute($query, $types, ...$values);
    }

    /**
     * @see PlrParserRepositoryInterface::upsertSnapshot()
     *
     * @param array<string, int|string> $data Full PLR snapshot data (column names as keys)
     */
    public function upsertSnapshot(array $data): int
    {
        // Column names in insertion order.
        $columns = self::SNAPSHOT_COLUMNS;
        $stringColumns = ['name', 'snapshot_phase', 'source_archive', 'pos'];
        $uniqueKeyColumns = ['pid', 'season_year', 'snapshot_phase'];

        $quotedCols = array_map(static fn (string $c): string => '`' . $c . '`', $columns);
        $colList = implode(', ', $quotedCols);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $updateClauses = [];
        foreach ($columns as $col) {
            if (in_array($col, $uniqueKeyColumns, true)) {
                continue;
            }
            $updateClauses[] = '`' . $col . '` = VALUES(`' . $col . '`)';
        }

        // $colList / $placeholders derive from the fixed SNAPSHOT_COLUMNS constant
        // (backticked identifiers / '?' placeholders) — concatenate, not interpolate.
        $query = "INSERT INTO `ibl_plr_snapshots` (" . $colList . ")
            VALUES (" . $placeholders . ")
            ON DUPLICATE KEY UPDATE " . implode(', ', $updateClauses);

        $types = '';
        $values = [];
        foreach ($columns as $col) {
            if (in_array($col, $stringColumns, true)) {
                $types .= 's';
                $values[] = (string) $data[$col];
            } else {
                $types .= 'i';
                $values[] = (int) $data[$col];
            }
        }

        return $this->execute($query, $types, ...$values);
    }

    /**
     * @see PlrParserRepositoryInterface::promotePriorSeasonSnapshots()
     */
    public function promotePriorSeasonSnapshots(int $priorYear): int
    {
        // Promotion copies more columns than upsertSnapshot() writes. SNAPSHOT_COLUMNS is
        // the parser's write set: upsertSnapshot() reads $data[$col] for each entry, so a
        // column the parser never produces cannot live there. phantom_games, po_phantom_games,
        // and created_at all exist on the row and must survive the copy, so they are appended
        // here only. Each append is guarded: were any later added to SNAPSHOT_COLUMNS, an
        // unconditional append would name it twice and the INSERT would error.
        $columns = self::SNAPSHOT_COLUMNS;
        foreach (['phantom_games', 'po_phantom_games', 'created_at'] as $extraColumn) {
            if (!in_array($extraColumn, $columns, true)) {
                $columns[] = $extraColumn;
            }
        }
        $quoted    = array_map(static fn (string $c): string => '`' . $c . '`', $columns);
        $colList = implode(', ', $quoted);
        $selectList = implode(', ', array_map(
            static fn (string $c): string => $c === 'snapshot_phase'
                ? "'end-of-season'"
                : 'src.`' . $c . '`',
            $columns,
        ));

        $query = "INSERT IGNORE INTO `ibl_plr_snapshots` ({$colList})
            SELECT {$selectList}
            FROM (
                SELECT s.*
                FROM `ibl_plr_snapshots` s
                LEFT JOIN `ibl_plr_snapshots` e
                       ON e.pid = s.pid
                      AND e.season_year = s.season_year
                      AND e.snapshot_phase = 'end-of-season'
                WHERE s.season_year = ?
                  AND s.snapshot_phase = 'mid-season'
                  AND e.id IS NULL
            ) AS src";

        return $this->execute($query, 'i', $priorYear);
    }

    /**
     * @see PlrParserRepositoryInterface::getSnapshotsByPhase()
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSnapshotsByPhase(int $seasonYear, string $phase): array
    {
        $columns = implode(', ', array_map(
            static fn (string $column): string => '`' . $column . '`',
            self::SNAPSHOT_COLUMNS,
        ));
        // $columns is built from the validated SNAPSHOT_COLUMNS constant, not user input
        $sql = "SELECT {$columns} FROM ibl_plr_snapshots WHERE season_year = ? AND snapshot_phase = ?"; // @phpstan-ignore ibl.sqlStringInterpolation
        $rows = $this->fetchAll($sql, 'is', $seasonYear, $phase);

        $byPid = [];
        foreach ($rows as $row) {
            if (is_numeric($row['pid'])) {
                $byPid[(int) $row['pid']] = $row;
            }
        }

        return $byPid;
    }

    /**
     * `ibl_plr` columns written by upsertPlayer(), mapped to their key in the parsed
     * PLR data array. `name` and `pos` bind as strings; every other column binds as an int.
     *
     * @var array<string, string>
     */
    private const PLAYER_COLUMNS = [
        // Identity & position
        'ordinal' => 'ordinal', 'name' => 'name', 'age' => 'age', 'pid' => 'pid',
        'teamid' => 'teamid', 'peak' => 'peak', 'pos' => 'pos',
        // Ratings
        'oo' => 'ratingOO', 'od' => 'ratingOD', 'r_drive_off' => 'ratingDO', 'dd' => 'ratingDD',
        'po' => 'ratingPO', 'pd' => 'ratingPD', 'r_trans_off' => 'ratingTO', 'td' => 'ratingTD',
        'clutch' => 'clutch', 'consistency' => 'consistency',
        // Depth chart
        'pg_depth' => 'PGDepth', 'sg_depth' => 'SGDepth', 'sf_depth' => 'SFDepth',
        'pf_depth' => 'PFDepth', 'c_depth' => 'CDepth', 'dc_can_play_in_game' => 'canPlayInGame',
        // Season stats
        'stats_gs' => 'seasonGamesStarted', 'stats_gm' => 'seasonGamesPlayed',
        'stats_min' => 'seasonMIN', 'stats_fgm' => 'seasonFGM', 'stats_fga' => 'seasonFGA',
        'stats_ftm' => 'seasonFTM', 'stats_fta' => 'seasonFTA',
        'stats_3gm' => 'season3GM', 'stats_3ga' => 'season3GA',
        'stats_orb' => 'seasonORB', 'stats_drb' => 'seasonDRB', 'stats_ast' => 'seasonAST',
        'stats_stl' => 'seasonSTL', 'stats_tvr' => 'seasonTVR', 'stats_blk' => 'seasonBLK',
        'stats_pf' => 'seasonPF',
        // Free-agency preferences
        'talent' => 'talent', 'skill' => 'skill', 'intangibles' => 'intangibles', 'coach' => 'coach',
        'loyalty' => 'loyalty', 'playing_time' => 'playingTime', 'winner' => 'playForWinner',
        'tradition' => 'tradition', 'security' => 'security',
        // Contract
        'exp' => 'exp', 'bird' => 'bird', 'cy' => 'currentContractYear', 'cyt' => 'totalContractYears',
        'salary_yr1' => 'contractYear1', 'salary_yr2' => 'contractYear2', 'salary_yr3' => 'contractYear3',
        'salary_yr4' => 'contractYear4', 'salary_yr5' => 'contractYear5', 'salary_yr6' => 'contractYear6',
        'fa_signing_flag' => 'freeAgentSigningFlag',
        // Season, season-playoff, career and career-playoff highs
        'sh_pts' => 'seasonHighPTS', 'sh_reb' => 'seasonHighREB', 'sh_ast' => 'seasonHighAST',
        'sh_stl' => 'seasonHighSTL', 'sh_blk' => 'seasonHighBLK',
        's_dd' => 'seasonHighDoubleDoubles', 's_td' => 'seasonHighTripleDoubles',
        'sp_pts' => 'seasonPlayoffHighPTS', 'sp_reb' => 'seasonPlayoffHighREB',
        'sp_ast' => 'seasonPlayoffHighAST', 'sp_stl' => 'seasonPlayoffHighSTL',
        'sp_blk' => 'seasonPlayoffHighBLK',
        'ch_pts' => 'careerSeasonHighPTS', 'ch_reb' => 'careerSeasonHighREB',
        'ch_ast' => 'careerSeasonHighAST', 'ch_stl' => 'careerSeasonHighSTL',
        'ch_blk' => 'careerSeasonHighBLK',
        'c_dd' => 'careerSeasonHighDoubleDoubles', 'c_td' => 'careerSeasonHighTripleDoubles',
        'cp_pts' => 'careerPlayoffHighPTS', 'cp_reb' => 'careerPlayoffHighREB',
        'cp_ast' => 'careerPlayoffHighAST', 'cp_stl' => 'careerPlayoffHighSTL',
        'cp_blk' => 'careerPlayoffHighBLK',
        // Career totals
        'car_gm' => 'careerGP', 'car_min' => 'careerMIN', 'car_fgm' => 'careerFGM', 'car_fga' => 'careerFGA',
        'car_ftm' => 'careerFTM', 'car_fta' => 'careerFTA', 'car_3gm' => 'career3GM', 'car_3ga' => 'career3GA',
        'car_orb' => 'careerORB', 'car_drb' => 'careerDRB', 'car_reb' => 'careerREB',
        'car_ast' => 'careerAST', 'car_stl' => 'careerSTL', 'car_tvr' => 'careerTVR',
        'car_blk' => 'careerBLK', 'car_pf' => 'careerPF', 'car_pts' => 'careerPTS',
        // Shooting and stat ratings
        'r_fga' => 'rating2GA', 'r_fgp' => 'rating2GP', 'r_fta' => 'ratingFTA', 'r_ftp' => 'ratingFTP',
        'r_3ga' => 'rating3GA', 'r_3gp' => 'rating3GP',
        'r_orb' => 'ratingORB', 'r_drb' => 'ratingDRB', 'r_ast' => 'ratingAST',
        'r_stl' => 'ratingSTL', 'r_tvr' => 'ratingTVR', 'r_blk' => 'ratingBLK',
        // Draft, injury and physical
        'draftround' => 'draftRound', 'draftpickno' => 'draftPickNumber', 'injured' => 'injuryDaysLeft',
        'htft' => 'heightFT', 'htin' => 'heightIN', 'wt' => 'weight', 'draftyear' => 'draftYear',
        'r_foul' => 'ratingFOUL',
    ];

    /**
     * Column names for ibl_plr_snapshots upsert, in insertion order.
     *
     * @var list<string>
     */
    private const SNAPSHOT_COLUMNS = [
        // Identity & metadata
        'pid', 'name', 'season_year', 'snapshot_phase', 'source_archive',
        'ordinal',
        // Physical & position
        'teamid', 'age', 'pos', 'peak', 'htft', 'htin', 'wt',
        // Positional ratings (1-9)
        'oo', 'od', 'r_drive_off', 'dd', 'po', 'pd', 'r_trans_off', 'td',
        // Stat ratings (0-99)
        'r_fga', 'r_fgp', 'r_fta', 'r_ftp', 'r_3ga', 'r_3gp',
        'r_orb', 'r_drb', 'r_ast', 'r_stl', 'r_tvr', 'r_blk', 'r_foul',
        // TSI attributes
        'talent', 'skill', 'intangibles', 'clutch', 'consistency',
        // Contract
        'exp', 'bird', 'cy', 'cyt',
        'salary_yr1', 'salary_yr2', 'salary_yr3', 'salary_yr4', 'salary_yr5', 'salary_yr6',
        // Depth chart
        'pg_depth', 'sg_depth', 'sf_depth', 'pf_depth', 'c_depth',
        // Season stats (regular season)
        'stats_gs', 'stats_gm', 'stats_min', 'stats_fgm', 'stats_fga',
        'stats_ftm', 'stats_fta', 'stats_3gm', 'stats_3ga',
        'stats_orb', 'stats_drb', 'stats_ast', 'stats_stl', 'stats_tvr', 'stats_blk', 'stats_pf',
        'stats_reb', 'stats_pts',
        // Playoff season stats (speculative — offset gap 208-267)
        'po_stats_gm', 'po_stats_min', 'po_stats_2gm', 'po_stats_2ga',
        'po_stats_ftm', 'po_stats_fta', 'po_stats_3gm', 'po_stats_3ga',
        'po_stats_orb', 'po_stats_drb', 'po_stats_ast', 'po_stats_stl',
        'po_stats_tvr', 'po_stats_blk', 'po_stats_pf',
        // Career stats
        'car_gm', 'car_min', 'car_fgm', 'car_fga', 'car_ftm', 'car_fta',
        'car_3gm', 'car_3ga', 'car_orb', 'car_drb', 'car_reb',
        'car_ast', 'car_stl', 'car_tvr', 'car_blk', 'car_pf', 'car_pts',
        // Season highs
        'sh_pts', 'sh_reb', 'sh_ast', 'sh_stl', 'sh_blk', 's_dd', 's_td',
        // Playoff highs
        'sp_pts', 'sp_reb', 'sp_ast', 'sp_stl', 'sp_blk',
        // Career season highs
        'ch_pts', 'ch_reb', 'ch_ast', 'ch_stl', 'ch_blk', 'c_dd', 'c_td',
        // Career playoff highs
        'cp_pts', 'cp_reb', 'cp_ast', 'cp_stl', 'cp_blk',
        // Real-life stats
        'rl_gp', 'rl_min', 'rl_fgm', 'rl_fga', 'rl_ftm', 'rl_fta',
        'rl_3gm', 'rl_3ga', 'rl_orb', 'rl_drb', 'rl_ast', 'rl_stl',
        'rl_tvr', 'rl_blk', 'rl_pf',
        // Preferences
        'coach', 'loyalty', 'playing_time', 'winner', 'tradition', 'security',
        // Draft info
        'draftround', 'draftpickno', 'fa_signing_flag',
        // Other
        'dc_can_play_in_game', 'injured',
        // Derived
        'draftyear', 'salary',
        // Unknown gaps
        'unk_112', 'unk_114', 'unk_116', 'unk_118',
        'unk_120', 'unk_122', 'unk_124', 'unk_126',
        'unk_138',
        'unk_294', 'unk_296',
        'unk_322', 'unk_324',
        'unk_331', 'unk_333', 'unk_335', 'unk_337', 'unk_339',
        'unk_512', 'unk_514', 'unk_516', 'unk_518',
        'unk_520', 'unk_522', 'unk_524', 'unk_526',
        'unk_528', 'unk_530', 'unk_532', 'unk_534',
        'unk_536', 'unk_538', 'unk_540', 'unk_542',
        'unk_544', 'unk_546', 'unk_548',
    ];
}
