<?php

declare(strict_types=1);

namespace PlrParser;

use League\LeagueContext;
use PlrParser\Contracts\PlrParserRepositoryInterface;

/**
 * Repository for PLR file database operations using prepared statements.
 *
 * Handles upserts into ibl_plr and ibl_hist tables.
 * League-aware: resolves table names through LeagueContext when provided.
 */
class PlrParserRepository extends \BaseMysqliRepository implements PlrParserRepositoryInterface
{
    private string $plrTable;
    private string $snapshotsTable;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->plrTable = $this->resolveTable('ibl_plr');
        $this->snapshotsTable = $this->resolveTable('ibl_plr_snapshots');
    }

    /**
     * @see PlrParserRepositoryInterface::upsertPlayer()
     *
     * @param array<string, int|string|float> $data
     */
    public function upsertPlayer(array $data): int
    {
        $query = "INSERT INTO {$this->plrTable}
            (`ordinal`, `name`, `age`, `pid`, `teamid`, `peak`, `pos`,
             `oo`, `od`, `r_drive_off`, `dd`, `po`, `pd`, `r_trans_off`, `td`,
             `Clutch`, `Consistency`,
             `PGDepth`, `SGDepth`, `SFDepth`, `PFDepth`, `CDepth`, `dc_canPlayInGame`,
             `stats_gs`, `stats_gm`, `stats_min`, `stats_fgm`, `stats_fga`,
             `stats_ftm`, `stats_fta`, `stats_3gm`, `stats_3ga`,
             `stats_orb`, `stats_drb`, `stats_ast`, `stats_stl`, `stats_tvr`, `stats_blk`, `stats_pf`,
             `talent`, `skill`, `intangibles`, `coach`, `loyalty`, `playingTime`, `winner`, `tradition`, `security`,
             `exp`, `bird`, `cy`, `cyt`,
             `cy1`, `cy2`, `cy3`, `cy4`, `cy5`, `cy6`, `fa_signing_flag`,
             `sh_pts`, `sh_reb`, `sh_ast`, `sh_stl`, `sh_blk`, `s_dd`, `s_td`,
             `sp_pts`, `sp_reb`, `sp_ast`, `sp_stl`, `sp_blk`,
             `ch_pts`, `ch_reb`, `ch_ast`, `ch_stl`, `ch_blk`, `c_dd`, `c_td`,
             `cp_pts`, `cp_reb`, `cp_ast`, `cp_stl`, `cp_blk`,
             `car_gm`, `car_min`, `car_fgm`, `car_fga`, `car_ftm`, `car_fta`,
             `car_tgm`, `car_tga`, `car_orb`, `car_drb`, `car_reb`,
             `car_ast`, `car_stl`, `car_to`, `car_blk`, `car_pf`, `car_pts`,
             `r_fga`, `r_fgp`, `r_fta`, `r_ftp`, `r_3ga`, `r_3gp`,
             `r_orb`, `r_drb`, `r_ast`, `r_stl`, `r_tvr`, `r_blk`,
             `draftround`, `draftpickno`, `injured`,
             `htft`, `htin`, `wt`, `draftyear`, `retired`, `r_foul`)
        VALUES
            (?, ?, ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?, ?, ?,
             ?, ?,
             ?, ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?,
             ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?, ?, ?, ?,
             ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?,
             ?, ?, ?,
             ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            `ordinal` = VALUES(`ordinal`),
            `name` = VALUES(`name`),
            `age` = VALUES(`age`),
            `teamid` = VALUES(`teamid`),
            `peak` = VALUES(`peak`),
            `pos` = VALUES(`pos`),
            `oo` = VALUES(`oo`),
            `od` = VALUES(`od`),
            `r_drive_off` = VALUES(`r_drive_off`),
            `dd` = VALUES(`dd`),
            `po` = VALUES(`po`),
            `pd` = VALUES(`pd`),
            `r_trans_off` = VALUES(`r_trans_off`),
            `td` = VALUES(`td`),
            `Clutch` = VALUES(`Clutch`),
            `Consistency` = VALUES(`Consistency`),
            `PGDepth` = VALUES(`PGDepth`),
            `SGDepth` = VALUES(`SGDepth`),
            `SFDepth` = VALUES(`SFDepth`),
            `PFDepth` = VALUES(`PFDepth`),
            `CDepth` = VALUES(`CDepth`),
            `dc_canPlayInGame` = VALUES(`dc_canPlayInGame`),
            `stats_gs` = VALUES(`stats_gs`),
            `stats_gm` = VALUES(`stats_gm`),
            `stats_min` = VALUES(`stats_min`),
            `stats_fgm` = VALUES(`stats_fgm`),
            `stats_fga` = VALUES(`stats_fga`),
            `stats_ftm` = VALUES(`stats_ftm`),
            `stats_fta` = VALUES(`stats_fta`),
            `stats_3gm` = VALUES(`stats_3gm`),
            `stats_3ga` = VALUES(`stats_3ga`),
            `stats_orb` = VALUES(`stats_orb`),
            `stats_drb` = VALUES(`stats_drb`),
            `stats_ast` = VALUES(`stats_ast`),
            `stats_stl` = VALUES(`stats_stl`),
            `stats_tvr` = VALUES(`stats_tvr`),
            `stats_blk` = VALUES(`stats_blk`),
            `stats_pf` = VALUES(`stats_pf`),
            `talent` = VALUES(`talent`),
            `skill` = VALUES(`skill`),
            `intangibles` = VALUES(`intangibles`),
            `coach` = VALUES(`coach`),
            `loyalty` = VALUES(`loyalty`),
            `playingTime` = VALUES(`playingTime`),
            `winner` = VALUES(`winner`),
            `tradition` = VALUES(`tradition`),
            `security` = VALUES(`security`),
            `exp` = VALUES(`exp`),
            `bird` = VALUES(`bird`),
            `cy` = VALUES(`cy`),
            `cyt` = VALUES(`cyt`),
            `cy1` = VALUES(`cy1`),
            `cy2` = VALUES(`cy2`),
            `cy3` = VALUES(`cy3`),
            `cy4` = VALUES(`cy4`),
            `cy5` = VALUES(`cy5`),
            `cy6` = VALUES(`cy6`),
            `fa_signing_flag` = VALUES(`fa_signing_flag`),
            `sh_pts` = VALUES(`sh_pts`),
            `sh_reb` = VALUES(`sh_reb`),
            `sh_ast` = VALUES(`sh_ast`),
            `sh_stl` = VALUES(`sh_stl`),
            `sh_blk` = VALUES(`sh_blk`),
            `s_dd` = VALUES(`s_dd`),
            `s_td` = VALUES(`s_td`),
            `sp_pts` = VALUES(`sp_pts`),
            `sp_reb` = VALUES(`sp_reb`),
            `sp_ast` = VALUES(`sp_ast`),
            `sp_stl` = VALUES(`sp_stl`),
            `sp_blk` = VALUES(`sp_blk`),
            `ch_pts` = VALUES(`ch_pts`),
            `ch_reb` = VALUES(`ch_reb`),
            `ch_ast` = VALUES(`ch_ast`),
            `ch_stl` = VALUES(`ch_stl`),
            `ch_blk` = VALUES(`ch_blk`),
            `c_dd` = VALUES(`c_dd`),
            `c_td` = VALUES(`c_td`),
            `cp_pts` = VALUES(`cp_pts`),
            `cp_reb` = VALUES(`cp_reb`),
            `cp_ast` = VALUES(`cp_ast`),
            `cp_stl` = VALUES(`cp_stl`),
            `cp_blk` = VALUES(`cp_blk`),
            `car_gm` = VALUES(`car_gm`),
            `car_min` = VALUES(`car_min`),
            `car_fgm` = VALUES(`car_fgm`),
            `car_fga` = VALUES(`car_fga`),
            `car_ftm` = VALUES(`car_ftm`),
            `car_fta` = VALUES(`car_fta`),
            `car_tgm` = VALUES(`car_tgm`),
            `car_tga` = VALUES(`car_tga`),
            `car_orb` = VALUES(`car_orb`),
            `car_drb` = VALUES(`car_drb`),
            `car_reb` = VALUES(`car_reb`),
            `car_ast` = VALUES(`car_ast`),
            `car_stl` = VALUES(`car_stl`),
            `car_to` = VALUES(`car_to`),
            `car_blk` = VALUES(`car_blk`),
            `car_pf` = VALUES(`car_pf`),
            `car_pts` = VALUES(`car_pts`),
            `r_fga` = VALUES(`r_fga`),
            `r_fgp` = VALUES(`r_fgp`),
            `r_fta` = VALUES(`r_fta`),
            `r_ftp` = VALUES(`r_ftp`),
            `r_3ga` = VALUES(`r_3ga`),
            `r_3gp` = VALUES(`r_3gp`),
            `r_orb` = VALUES(`r_orb`),
            `r_drb` = VALUES(`r_drb`),
            `r_ast` = VALUES(`r_ast`),
            `r_stl` = VALUES(`r_stl`),
            `r_tvr` = VALUES(`r_tvr`),
            `r_blk` = VALUES(`r_blk`),
            `draftround` = VALUES(`draftround`),
            `draftpickno` = VALUES(`draftpickno`),
            `injured` = VALUES(`injured`),
            `htft` = VALUES(`htft`),
            `htin` = VALUES(`htin`),
            `wt` = VALUES(`wt`),
            `draftyear` = VALUES(`draftyear`),
            `retired` = VALUES(`retired`),
            `r_foul` = VALUES(`r_foul`)";

        // Build types: ordinal(i) name(s) age(i) pid(i) teamid(i) peak(i) pos(s)
        // + remaining int columns, then at the end retired(i) r_foul(i)
        // Total: 121 params — 2 strings (name, pos) and 119 ints
        $types = 'isiiiis'   // ordinal, name, age, pid, teamid, peak, pos
            . 'iiiiiiii'     // oo, od, r_drive_off, dd, po, pd, r_trans_off, td
            . 'ii'           // Clutch, Consistency
            . 'iiiiii'       // PGDepth..CDepth, canPlayInGame → dc_canPlayInGame
            . 'iiiii'        // stats_gs..stats_fga
            . 'iiii'         // stats_ftm..stats_3ga
            . 'iiiiiii'      // stats_orb..stats_pf
            . 'iiiiiiiii'    // talent..security
            . 'iiii'         // exp, bird, cy, cyt
            . 'iiiiiii'      // cy1..cy6, fa_signing_flag
            . 'iiiiiii'      // sh_pts..s_td
            . 'iiiii'        // sp_pts..sp_blk
            . 'iiiiiii'      // ch_pts..c_td
            . 'iiiii'        // cp_pts..cp_blk
            . 'iiiiii'       // car_gm..car_fta
            . 'iiiii'        // car_tgm..car_reb
            . 'iiiiii'       // car_ast..car_pts
            . 'iiiiii'       // r_fga..r_3gp
            . 'iiiiii'       // r_orb..r_blk
            . 'iii'          // draftround, draftpickno, injured
            . 'iiiiii';      // htft, htin, wt, draftyear, retired, r_foul

        return $this->execute(
            $query,
            $types,
            (int) $data['ordinal'],
            (string) $data['name'],
            (int) $data['age'],
            (int) $data['pid'],
            (int) $data['teamid'],
            (int) $data['peak'],
            (string) $data['pos'],
            (int) $data['ratingOO'],
            (int) $data['ratingOD'],
            (int) $data['ratingDO'],
            (int) $data['ratingDD'],
            (int) $data['ratingPO'],
            (int) $data['ratingPD'],
            (int) $data['ratingTO'],
            (int) $data['ratingTD'],
            (int) $data['clutch'],
            (int) $data['consistency'],
            (int) $data['PGDepth'],
            (int) $data['SGDepth'],
            (int) $data['SFDepth'],
            (int) $data['PFDepth'],
            (int) $data['CDepth'],
            (int) $data['canPlayInGame'],
            (int) $data['seasonGamesStarted'],
            (int) $data['seasonGamesPlayed'],
            (int) $data['seasonMIN'],
            (int) $data['seasonFGM'],
            (int) $data['seasonFGA'],
            (int) $data['seasonFTM'],
            (int) $data['seasonFTA'],
            (int) $data['season3GM'],
            (int) $data['season3GA'],
            (int) $data['seasonORB'],
            (int) $data['seasonDRB'],
            (int) $data['seasonAST'],
            (int) $data['seasonSTL'],
            (int) $data['seasonTVR'],
            (int) $data['seasonBLK'],
            (int) $data['seasonPF'],
            (int) $data['talent'],
            (int) $data['skill'],
            (int) $data['intangibles'],
            (int) $data['coach'],
            (int) $data['loyalty'],
            (int) $data['playingTime'],
            (int) $data['playForWinner'],
            (int) $data['tradition'],
            (int) $data['security'],
            (int) $data['exp'],
            (int) $data['bird'],
            (int) $data['currentContractYear'],
            (int) $data['totalContractYears'],
            (int) $data['contractYear1'],
            (int) $data['contractYear2'],
            (int) $data['contractYear3'],
            (int) $data['contractYear4'],
            (int) $data['contractYear5'],
            (int) $data['contractYear6'],
            (int) $data['freeAgentSigningFlag'],
            (int) $data['seasonHighPTS'],
            (int) $data['seasonHighREB'],
            (int) $data['seasonHighAST'],
            (int) $data['seasonHighSTL'],
            (int) $data['seasonHighBLK'],
            (int) $data['seasonHighDoubleDoubles'],
            (int) $data['seasonHighTripleDoubles'],
            (int) $data['seasonPlayoffHighPTS'],
            (int) $data['seasonPlayoffHighREB'],
            (int) $data['seasonPlayoffHighAST'],
            (int) $data['seasonPlayoffHighSTL'],
            (int) $data['seasonPlayoffHighBLK'],
            (int) $data['careerSeasonHighPTS'],
            (int) $data['careerSeasonHighREB'],
            (int) $data['careerSeasonHighAST'],
            (int) $data['careerSeasonHighSTL'],
            (int) $data['careerSeasonHighBLK'],
            (int) $data['careerSeasonHighDoubleDoubles'],
            (int) $data['careerSeasonHighTripleDoubles'],
            (int) $data['careerPlayoffHighPTS'],
            (int) $data['careerPlayoffHighREB'],
            (int) $data['careerPlayoffHighAST'],
            (int) $data['careerPlayoffHighSTL'],
            (int) $data['careerPlayoffHighBLK'],
            (int) $data['careerGP'],
            (int) $data['careerMIN'],
            (int) $data['careerFGM'],
            (int) $data['careerFGA'],
            (int) $data['careerFTM'],
            (int) $data['careerFTA'],
            (int) $data['career3GM'],
            (int) $data['career3GA'],
            (int) $data['careerORB'],
            (int) $data['careerDRB'],
            (int) $data['careerREB'],
            (int) $data['careerAST'],
            (int) $data['careerSTL'],
            (int) $data['careerTVR'],
            (int) $data['careerBLK'],
            (int) $data['careerPF'],
            (int) $data['careerPTS'],
            (int) $data['rating2GA'],
            (int) $data['rating2GP'],
            (int) $data['ratingFTA'],
            (int) $data['ratingFTP'],
            (int) $data['rating3GA'],
            (int) $data['rating3GP'],
            (int) $data['ratingORB'],
            (int) $data['ratingDRB'],
            (int) $data['ratingAST'],
            (int) $data['ratingSTL'],
            (int) $data['ratingTVR'],
            (int) $data['ratingBLK'],
            (int) $data['draftRound'],
            (int) $data['draftPickNumber'],
            (int) $data['injuryDaysLeft'],
            (int) $data['heightFT'],
            (int) $data['heightIN'],
            (int) $data['weight'],
            (int) $data['draftYear'],
            0, // retired
            (int) $data['ratingFOUL'],
        );
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

        $query = "INSERT INTO {$this->snapshotsTable} ({$colList})
            VALUES ({$placeholders})
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
        'cy1', 'cy2', 'cy3', 'cy4', 'cy5', 'cy6',
        // Depth chart
        'PGDepth', 'SGDepth', 'SFDepth', 'PFDepth', 'CDepth',
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
        'car_tgm', 'car_tga', 'car_orb', 'car_drb', 'car_reb',
        'car_ast', 'car_stl', 'car_to', 'car_blk', 'car_pf', 'car_pts',
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
        'coach', 'loyalty', 'playingTime', 'winner', 'tradition', 'security',
        // Draft info
        'draftround', 'draftpickno', 'fa_signing_flag',
        // Other
        'dc_canPlayInGame', 'injured',
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
