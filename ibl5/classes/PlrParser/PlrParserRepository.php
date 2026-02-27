<?php

declare(strict_types=1);

namespace PlrParser;

use PlrParser\Contracts\PlrParserRepositoryInterface;

/**
 * Repository for PLR file database operations using prepared statements.
 *
 * Handles upserts into ibl_plr and ibl_hist tables.
 */
class PlrParserRepository extends \BaseMysqliRepository implements PlrParserRepositoryInterface
{
    /**
     * @see PlrParserRepositoryInterface::upsertPlayer()
     *
     * @param array<string, int|string|float> $data
     */
    public function upsertPlayer(array $data): int
    {
        $query = "INSERT INTO ibl_plr
            (`ordinal`, `name`, `age`, `pid`, `tid`, `peak`, `pos`,
             `oo`, `od`, `do`, `dd`, `po`, `pd`, `to`, `td`,
             `Clutch`, `Consistency`,
             `PGDepth`, `SGDepth`, `SFDepth`, `PFDepth`, `CDepth`, `active`,
             `stats_gs`, `stats_gm`, `stats_min`, `stats_fgm`, `stats_fga`,
             `stats_ftm`, `stats_fta`, `stats_3gm`, `stats_3ga`,
             `stats_orb`, `stats_drb`, `stats_ast`, `stats_stl`, `stats_to`, `stats_blk`, `stats_pf`,
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
             `r_fga`, `r_fgp`, `r_fta`, `r_ftp`, `r_tga`, `r_tgp`,
             `r_orb`, `r_drb`, `r_ast`, `r_stl`, `r_to`, `r_blk`,
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
            `tid` = VALUES(`tid`),
            `peak` = VALUES(`peak`),
            `pos` = VALUES(`pos`),
            `oo` = VALUES(`oo`),
            `od` = VALUES(`od`),
            `do` = VALUES(`do`),
            `dd` = VALUES(`dd`),
            `po` = VALUES(`po`),
            `pd` = VALUES(`pd`),
            `to` = VALUES(`to`),
            `td` = VALUES(`td`),
            `Clutch` = VALUES(`Clutch`),
            `Consistency` = VALUES(`Consistency`),
            `PGDepth` = VALUES(`PGDepth`),
            `SGDepth` = VALUES(`SGDepth`),
            `SFDepth` = VALUES(`SFDepth`),
            `PFDepth` = VALUES(`PFDepth`),
            `CDepth` = VALUES(`CDepth`),
            `active` = VALUES(`active`),
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
            `stats_to` = VALUES(`stats_to`),
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
            `r_tga` = VALUES(`r_tga`),
            `r_tgp` = VALUES(`r_tgp`),
            `r_orb` = VALUES(`r_orb`),
            `r_drb` = VALUES(`r_drb`),
            `r_ast` = VALUES(`r_ast`),
            `r_stl` = VALUES(`r_stl`),
            `r_to` = VALUES(`r_to`),
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

        // Build types: ordinal(i) name(s) age(i) pid(i) tid(i) peak(i) pos(s)
        // + remaining int columns, then at the end retired(i) r_foul(i)
        // Total: 121 params — 2 strings (name, pos) and 119 ints
        $types = 'isiiiis'   // ordinal, name, age, pid, tid, peak, pos
            . 'iiiiiiii'     // oo, od, do, dd, po, pd, to, td
            . 'ii'           // Clutch, Consistency
            . 'iiiiii'       // PGDepth..CDepth, active
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
            . 'iiiiii'       // r_fga..r_tgp
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
            (int) $data['tid'],
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
            (int) $data['active'],
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
     * @see PlrParserRepositoryInterface::upsertHistoricalStats()
     *
     * @param array<string, int|string|float> $data
     */
    public function upsertHistoricalStats(array $data): int
    {
        $query = "INSERT INTO ibl_hist
            (`pid`, `name`, `year`, `team`, `teamid`,
             `games`, `minutes`, `fgm`, `fga`, `ftm`, `fta`, `tgm`, `tga`,
             `orb`, `reb`, `ast`, `stl`, `blk`, `tvr`, `pf`, `pts`,
             `r_2ga`, `r_2gp`, `r_fta`, `r_ftp`, `r_3ga`, `r_3gp`,
             `r_orb`, `r_drb`, `r_ast`, `r_stl`, `r_blk`, `r_tvr`,
             `r_oo`, `r_od`, `r_do`, `r_dd`, `r_po`, `r_pd`, `r_to`, `r_td`,
             `salary`)
        VALUES
            (?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?, ?, ?,
             ?)
        ON DUPLICATE KEY UPDATE
            `team` = VALUES(`team`),
            `teamid` = VALUES(`teamid`),
            `games` = VALUES(`games`),
            `minutes` = VALUES(`minutes`),
            `fgm` = VALUES(`fgm`),
            `fga` = VALUES(`fga`),
            `ftm` = VALUES(`ftm`),
            `fta` = VALUES(`fta`),
            `tgm` = VALUES(`tgm`),
            `tga` = VALUES(`tga`),
            `orb` = VALUES(`orb`),
            `reb` = VALUES(`reb`),
            `ast` = VALUES(`ast`),
            `stl` = VALUES(`stl`),
            `blk` = VALUES(`blk`),
            `tvr` = VALUES(`tvr`),
            `pf` = VALUES(`pf`),
            `pts` = VALUES(`pts`),
            `r_2ga` = VALUES(`r_2ga`),
            `r_2gp` = VALUES(`r_2gp`),
            `r_fta` = VALUES(`r_fta`),
            `r_ftp` = VALUES(`r_ftp`),
            `r_3ga` = VALUES(`r_3ga`),
            `r_3gp` = VALUES(`r_3gp`),
            `r_orb` = VALUES(`r_orb`),
            `r_drb` = VALUES(`r_drb`),
            `r_ast` = VALUES(`r_ast`),
            `r_stl` = VALUES(`r_stl`),
            `r_blk` = VALUES(`r_blk`),
            `r_tvr` = VALUES(`r_tvr`),
            `r_oo` = VALUES(`r_oo`),
            `r_od` = VALUES(`r_od`),
            `r_do` = VALUES(`r_do`),
            `r_dd` = VALUES(`r_dd`),
            `r_po` = VALUES(`r_po`),
            `r_pd` = VALUES(`r_pd`),
            `r_to` = VALUES(`r_to`),
            `r_td` = VALUES(`r_td`),
            `salary` = VALUES(`salary`)";

        // 42 params: pid(i) name(s) year(i) team(s) teamid(i) + 37 ints
        $types = 'isisi'     // pid, name, year, team, teamid
            . 'iiiiiiii'     // games, minutes, fgm, fga, ftm, fta, tgm, tga (8)
            . 'iiiiiiii'     // orb, reb, ast, stl, blk, tvr, pf, pts (8)
            . 'iiiiii'       // r_2ga..r_3gp
            . 'iiiiii'       // r_orb..r_tvr
            . 'iiiiiiii'     // r_oo..r_td
            . 'i';           // salary

        return $this->execute(
            $query,
            $types,
            (int) $data['pid'],
            (string) $data['name'],
            (int) $data['year'],
            (string) $data['team'],
            (int) $data['tid'],
            (int) $data['seasonGamesPlayed'],
            (int) $data['seasonMIN'],
            (int) $data['seasonFGM'],
            (int) $data['seasonFGA'],
            (int) $data['seasonFTM'],
            (int) $data['seasonFTA'],
            (int) $data['season3GM'],
            (int) $data['season3GA'],
            (int) $data['seasonORB'],
            (int) $data['seasonREB'],
            (int) $data['seasonAST'],
            (int) $data['seasonSTL'],
            (int) $data['seasonBLK'],
            (int) $data['seasonTVR'],
            (int) $data['seasonPF'],
            (int) $data['seasonPTS'],
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
            (int) $data['ratingBLK'],
            (int) $data['ratingTVR'],
            (int) $data['ratingOO'],
            (int) $data['ratingOD'],
            (int) $data['ratingDO'],
            (int) $data['ratingDD'],
            (int) $data['ratingPO'],
            (int) $data['ratingPD'],
            (int) $data['ratingTO'],
            (int) $data['ratingTD'],
            (int) $data['currentSeasonSalary'],
        );
    }

    /**
     * @see PlrParserRepositoryInterface::assignTeamNames()
     *
     * @param list<array{teamid: int, team_name: string}> $teamData
     */
    public function assignTeamNames(array $teamData): int
    {
        $assignedCount = 0;
        foreach ($teamData as $teamRow) {
            $affected = $this->execute(
                "UPDATE ibl_plr SET teamname = ? WHERE tid = ?",
                "si",
                $teamRow['team_name'],
                $teamRow['teamid'],
            );
            if ($affected > 0) {
                $assignedCount++;
            }
        }
        return $assignedCount;
    }

    /**
     * @see PlrParserRepositoryInterface::getAllTeamData()
     *
     * @return list<array{teamid: int, team_name: string}>
     */
    public function getAllTeamData(): array
    {
        /** @var list<array{teamid: int, team_name: string}> $rows */
        $rows = $this->fetchAll(
            "SELECT teamid, team_name FROM ibl_team_info WHERE teamid BETWEEN 1 AND " . \League::MAX_REAL_TEAMID . " ORDER BY teamid ASC",
        );
        return $rows;
    }
}
