<?php

declare(strict_types=1);

namespace Tests\Security;

use Api\Pagination\Paginator;
use Api\Repository\ApiGameRepository;
use Api\Repository\ApiLeadersRepository;
use AwardHistory\AwardHistoryRepository;
use BasketballStats\Tables\PeriodAverages;
use BugPipeline\BugReportClaimRepository;
use FranchiseRecordBook\FranchiseRecordBookRepository;
use LastSimRecap\LastSimRecapRepository;
use League\League;
use LeagueSchedule\LeagueScheduleRepository;
use Migration\SchemaAssertion;
use Migration\SchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Player\Stats\PlayerStatsRepository;
use PlayerSearch\PlayerSearchRepository;
use PlrParser\PlrBoxScoreRepository;
use PlrParser\PlrParserRepository;
use ProjectedDraftOrder\ProjectedDraftOrderRepository;
use Search\Contracts\SearchRepositoryInterface;
use Search\SearchRepository;
use SeasonLeaderboards\SeasonLeaderboardsRepository;
use Standings\StandingsRepository;
use Team\SplitStatsRepository;
use TeamOffDefStats\TeamOffDefStatsRepository;
use TeamSchedule\TeamScheduleRepository;
use Tests\WideUnit\Mocks\MockDatabase;
use Topics\TopicsRepository;
use TransactionHistory\TransactionHistoryRepository;
use Voting\VotingRepository;

/**
 * Golden-SQL characterization pins for the queries that splice a PHP string into SQL
 * text (Search, Standings, TeamOffDefStats). Each pin records the exact SQL the
 * repository sends today, so the later refactors that replace the concatenation are
 * green-to-green. Full-SQL assertions are allowed only in security tests
 * (.claude/rules/phpunit-tests.md), which is why the pins live here and not in the
 * module test files.
 *
 * Golden text is test-owned: the constants below are copied from the production
 * heredocs and whitespace-collapsed. The test never calls production code to obtain
 * its expected text.
 *
 * Search and Standings pin getPreparedQueries() (placeholders intact, backticks
 * intact). TeamOffDefStats pins getExecutedQueries() (bound values substituted,
 * backticks stripped by the mock), so the pin survives moving literals into
 * placeholders.
 */
final class SqlConcatGoldenSqlTest extends TestCase
{
    // ---- Standings (prepared text, backticks intact) ----

    private const STANDINGS_OFFENSE_BODY = 'SELECT fs.franchise_id AS teamid, fs.team_name AS name, bst.season_year, '
        . 'CAST(SUM(bst.game_2gm + bst.game_3gm) AS SIGNED) AS fgm, '
        . 'CAST(SUM(bst.game_ftm) AS SIGNED) AS ftm, '
        . 'CAST(SUM(bst.game_3gm) AS SIGNED) AS tgm '
        . 'FROM `ibl_box_scores_teams` bst '
        . 'JOIN `ibl_franchise_seasons` fs '
        . 'ON fs.team_name = bst.name AND fs.season_ending_year = bst.season_year '
        . 'WHERE bst.game_type = 1 AND ';

    private const STANDINGS_OFFENSE_GROUP = ' GROUP BY fs.franchise_id, fs.team_name, bst.season_year';

    private const STANDINGS_DEFENSE_BODY = 'SELECT fs.franchise_id AS teamid, fs.team_name AS name, my.season_year, '
        . 'CAST(SUM(opp.game_2gm + opp.game_3gm) AS SIGNED) AS fgm, '
        . 'CAST(SUM(opp.game_ftm) AS SIGNED) AS ftm, '
        . 'CAST(SUM(opp.game_3gm) AS SIGNED) AS tgm '
        . 'FROM `ibl_box_scores_teams` my '
        . 'JOIN `ibl_box_scores_teams` opp '
        . 'ON my.game_date = opp.game_date '
        . 'AND my.visitor_teamid = opp.visitor_teamid '
        . 'AND my.home_teamid = opp.home_teamid '
        . 'AND my.game_of_that_day = opp.game_of_that_day '
        . 'AND my.name <> opp.name '
        . 'JOIN `ibl_franchise_seasons` fs '
        . 'ON fs.team_name = my.name AND fs.season_ending_year = my.season_year '
        . 'WHERE my.game_type = 1 AND ';

    private const STANDINGS_DEFENSE_GROUP = ' GROUP BY fs.franchise_id, fs.team_name, my.season_year';

    private const STANDINGS_JOIN_ON = ' ON tos.teamid = tds.teamid AND tos.season_year = tds.season_year';

    // ---- TeamOffDefStats (executed text, backticks stripped), ending at "WHERE " ----

    private const TODS_OFFENSE_BODY = 'SELECT fs.franchise_id AS teamid, fs.team_name AS name, bst.season_year, '
        . 'CAST(COUNT(*) AS SIGNED) AS games, '
        . 'CAST(SUM(bst.game_2gm + bst.game_3gm) AS SIGNED) AS fgm, '
        . 'CAST(SUM(bst.game_2ga + bst.game_3ga) AS SIGNED) AS fga, '
        . 'CAST(SUM(bst.game_ftm) AS SIGNED) AS ftm, '
        . 'CAST(SUM(bst.game_fta) AS SIGNED) AS fta, '
        . 'CAST(SUM(bst.game_3gm) AS SIGNED) AS tgm, '
        . 'CAST(SUM(bst.game_3ga) AS SIGNED) AS tga, '
        . 'CAST(SUM(bst.game_orb) AS SIGNED) AS orb, '
        . 'CAST(SUM(bst.game_orb + bst.game_drb) AS SIGNED) AS reb, '
        . 'CAST(SUM(bst.game_ast) AS SIGNED) AS ast, '
        . 'CAST(SUM(bst.game_stl) AS SIGNED) AS stl, '
        . 'CAST(SUM(bst.game_tov) AS SIGNED) AS tvr, '
        . 'CAST(SUM(bst.game_blk) AS SIGNED) AS blk, '
        . 'CAST(SUM(bst.game_pf) AS SIGNED) AS pf '
        . 'FROM ibl_box_scores_teams bst '
        . 'JOIN ibl_franchise_seasons fs '
        . 'ON fs.team_name = bst.name AND fs.season_ending_year = bst.season_year '
        . 'WHERE ';

    private const TODS_OFFENSE_GROUP = ' GROUP BY fs.franchise_id, fs.team_name, bst.season_year';

    private const TODS_DEFENSE_BODY = 'SELECT fs.franchise_id AS teamid, fs.team_name AS name, my.season_year, '
        . 'CAST(COUNT(*) AS SIGNED) AS games, '
        . 'CAST(SUM(opp.game_2gm + opp.game_3gm) AS SIGNED) AS fgm, '
        . 'CAST(SUM(opp.game_2ga + opp.game_3ga) AS SIGNED) AS fga, '
        . 'CAST(SUM(opp.game_ftm) AS SIGNED) AS ftm, '
        . 'CAST(SUM(opp.game_fta) AS SIGNED) AS fta, '
        . 'CAST(SUM(opp.game_3gm) AS SIGNED) AS tgm, '
        . 'CAST(SUM(opp.game_3ga) AS SIGNED) AS tga, '
        . 'CAST(SUM(opp.game_orb) AS SIGNED) AS orb, '
        . 'CAST(SUM(opp.game_orb + opp.game_drb) AS SIGNED) AS reb, '
        . 'CAST(SUM(opp.game_ast) AS SIGNED) AS ast, '
        . 'CAST(SUM(opp.game_stl) AS SIGNED) AS stl, '
        . 'CAST(SUM(opp.game_tov) AS SIGNED) AS tvr, '
        . 'CAST(SUM(opp.game_blk) AS SIGNED) AS blk, '
        . 'CAST(SUM(opp.game_pf) AS SIGNED) AS pf '
        . 'FROM ibl_box_scores_teams my '
        . 'JOIN ibl_box_scores_teams opp '
        . 'ON my.game_date = opp.game_date '
        . 'AND my.visitor_teamid = opp.visitor_teamid '
        . 'AND my.home_teamid = opp.home_teamid '
        . 'AND my.game_of_that_day = opp.game_of_that_day '
        . 'AND my.name <> opp.name '
        . 'JOIN ibl_franchise_seasons fs '
        . 'ON fs.team_name = my.name AND fs.season_ending_year = my.season_year '
        . 'WHERE ';

    private const TODS_DEFENSE_GROUP = ' GROUP BY fs.franchise_id, fs.team_name, my.season_year';

    private const TODS_ALL_TEAMS_HEAD = 'SELECT ti.teamid, ti.team_city, ti.team_name, ti.color1, ti.color2, '
        . 'tos.games AS offense_games, tos.fgm AS offense_fgm, tos.fga AS offense_fga, '
        . 'tos.ftm AS offense_ftm, tos.fta AS offense_fta, tos.tgm AS offense_tgm, '
        . 'tos.tga AS offense_tga, tos.orb AS offense_orb, tos.reb AS offense_reb, '
        . 'tos.ast AS offense_ast, tos.stl AS offense_stl, tos.tvr AS offense_tvr, '
        . 'tos.blk AS offense_blk, tos.pf AS offense_pf, '
        . 'tds.games AS defense_games, tds.fgm AS defense_fgm, tds.fga AS defense_fga, '
        . 'tds.ftm AS defense_ftm, tds.fta AS defense_fta, tds.tgm AS defense_tgm, '
        . 'tds.tga AS defense_tga, tds.orb AS defense_orb, tds.reb AS defense_reb, '
        . 'tds.ast AS defense_ast, tds.stl AS defense_stl, tds.tvr AS defense_tvr, '
        . 'tds.blk AS defense_blk, tds.pf AS defense_pf '
        . 'FROM ibl_team_info ti '
        . 'LEFT JOIN (';

    private const TODS_ALL_TEAMS_TAIL = ' WHERE ti.teamid BETWEEN 1 AND 28 ORDER BY ti.team_city, ti.teamid ASC';

    private const TODS_BOTH_HEAD = 'SELECT tos.teamid AS tos_teamID, tos.name AS tos_name, '
        . 'tos.games AS tos_games, tos.fgm AS tos_fgm, tos.fga AS tos_fga, '
        . 'tos.ftm AS tos_ftm, tos.fta AS tos_fta, tos.tgm AS tos_tgm, tos.tga AS tos_tga, '
        . 'tos.orb AS tos_orb, tos.reb AS tos_reb, tos.ast AS tos_ast, tos.stl AS tos_stl, '
        . 'tos.tvr AS tos_tvr, tos.blk AS tos_blk, tos.pf AS tos_pf, '
        . 'tds.teamid AS tds_teamID, tds.name AS tds_name, '
        . 'tds.games AS tds_games, tds.fgm AS tds_fgm, tds.fga AS tds_fga, '
        . 'tds.ftm AS tds_ftm, tds.fta AS tds_fta, tds.tgm AS tds_tgm, tds.tga AS tds_tga, '
        . 'tds.orb AS tds_orb, tds.reb AS tds_reb, tds.ast AS tds_ast, tds.stl AS tds_stl, '
        . 'tds.tvr AS tds_tvr, tds.blk AS tds_blk, tds.pf AS tds_pf '
        . 'FROM (';

    private const SEARCH_TOPICS_SQL = 'SELECT topicid, topictext FROM nuke_topics ORDER BY topictext, id ASC';

    private static function normalizeSql(string $sql): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $sql));
    }

    // ---- Cleared-site pins: queries rebuilt from constant fragments in the sql-concat burn-down ----
    //
    // Each pin records the SQL the site sent before its refactor. Expected text and bound
    // values are test-owned literals (captured once from the pre-refactor classes), never
    // produced by production code at assertion time. Prepared pins keep placeholders and
    // backticks; executed pins (getExecutedQueries) substitute bound values and strip
    // backticks, so they survive a literal moving into a placeholder.

    /** Test-owned copy of the ibl_plr_snapshots write set, in insertion order. */
    private const SNAPSHOT_COLUMNS = [
        'pid',
        'name',
        'season_year',
        'snapshot_phase',
        'source_archive',
        'ordinal',
        'teamid',
        'age',
        'pos',
        'peak',
        'htft',
        'htin',
        'wt',
        'oo',
        'od',
        'r_drive_off',
        'dd',
        'po',
        'pd',
        'r_trans_off',
        'td',
        'r_fga',
        'r_fgp',
        'r_fta',
        'r_ftp',
        'r_3ga',
        'r_3gp',
        'r_orb',
        'r_drb',
        'r_ast',
        'r_stl',
        'r_tvr',
        'r_blk',
        'r_foul',
        'talent',
        'skill',
        'intangibles',
        'clutch',
        'consistency',
        'exp',
        'bird',
        'cy',
        'cyt',
        'salary_yr1',
        'salary_yr2',
        'salary_yr3',
        'salary_yr4',
        'salary_yr5',
        'salary_yr6',
        'pg_depth',
        'sg_depth',
        'sf_depth',
        'pf_depth',
        'c_depth',
        'stats_gs',
        'stats_gm',
        'stats_min',
        'stats_fgm',
        'stats_fga',
        'stats_ftm',
        'stats_fta',
        'stats_3gm',
        'stats_3ga',
        'stats_orb',
        'stats_drb',
        'stats_ast',
        'stats_stl',
        'stats_tvr',
        'stats_blk',
        'stats_pf',
        'stats_reb',
        'stats_pts',
        'po_stats_gm',
        'po_stats_min',
        'po_stats_2gm',
        'po_stats_2ga',
        'po_stats_ftm',
        'po_stats_fta',
        'po_stats_3gm',
        'po_stats_3ga',
        'po_stats_orb',
        'po_stats_drb',
        'po_stats_ast',
        'po_stats_stl',
        'po_stats_tvr',
        'po_stats_blk',
        'po_stats_pf',
        'car_gm',
        'car_min',
        'car_fgm',
        'car_fga',
        'car_ftm',
        'car_fta',
        'car_3gm',
        'car_3ga',
        'car_orb',
        'car_drb',
        'car_reb',
        'car_ast',
        'car_stl',
        'car_tvr',
        'car_blk',
        'car_pf',
        'car_pts',
        'sh_pts',
        'sh_reb',
        'sh_ast',
        'sh_stl',
        'sh_blk',
        's_dd',
        's_td',
        'sp_pts',
        'sp_reb',
        'sp_ast',
        'sp_stl',
        'sp_blk',
        'ch_pts',
        'ch_reb',
        'ch_ast',
        'ch_stl',
        'ch_blk',
        'c_dd',
        'c_td',
        'cp_pts',
        'cp_reb',
        'cp_ast',
        'cp_stl',
        'cp_blk',
        'rl_gp',
        'rl_min',
        'rl_fgm',
        'rl_fga',
        'rl_ftm',
        'rl_fta',
        'rl_3gm',
        'rl_3ga',
        'rl_orb',
        'rl_drb',
        'rl_ast',
        'rl_stl',
        'rl_tvr',
        'rl_blk',
        'rl_pf',
        'coach',
        'loyalty',
        'playing_time',
        'winner',
        'tradition',
        'security',
        'draftround',
        'draftpickno',
        'fa_signing_flag',
        'dc_can_play_in_game',
        'injured',
        'draftyear',
        'salary',
        'unk_112',
        'unk_114',
        'unk_116',
        'unk_118',
        'unk_120',
        'unk_122',
        'unk_124',
        'unk_126',
        'unk_138',
        'unk_294',
        'unk_296',
        'unk_322',
        'unk_324',
        'unk_331',
        'unk_333',
        'unk_335',
        'unk_337',
        'unk_339',
        'unk_512',
        'unk_514',
        'unk_516',
        'unk_518',
        'unk_520',
        'unk_522',
        'unk_524',
        'unk_526',
        'unk_528',
        'unk_530',
        'unk_532',
        'unk_534',
        'unk_536',
        'unk_538',
        'unk_540',
        'unk_542',
        'unk_544',
        'unk_546',
        'unk_548',
    ];

    private const SPLIT_STATS_PREPARED_HEAD = 'SELECT p.name, bs.pos, bs.pid, COUNT(DISTINCT bs.`game_date`) as games, '
        . 'ROUND(SUM(bs.game_min)/COUNT(DISTINCT bs.`game_date`), 1) as gameMINavg, '
        . 'ROUND(SUM(bs.game_2gm + bs.game_3gm)/COUNT(DISTINCT bs.`game_date`), 2) as '
        . 'gameFGMavg, ROUND(SUM(bs.game_2ga + bs.game_3ga)/COUNT(DISTINCT bs.`game_date`), 2) '
        . 'as gameFGAavg, ROUND((SUM(bs.game_2gm) + SUM(bs.game_3gm)) / '
        . 'NULLIF(SUM(bs.game_2ga) + SUM(bs.game_3ga), 0), 3) as gameFGPavg, '
        . 'ROUND(SUM(bs.game_ftm)/COUNT(DISTINCT bs.`game_date`), 2) as gameFTMavg, '
        . 'ROUND(SUM(bs.game_fta)/COUNT(DISTINCT bs.`game_date`), 2) as gameFTAavg, '
        . 'ROUND(SUM(bs.game_ftm) / NULLIF(SUM(bs.game_fta), 0), 3) as gameFTPavg, '
        . 'ROUND(SUM(bs.game_3gm)/COUNT(DISTINCT bs.`game_date`), 2) as game3GMavg, '
        . 'ROUND(SUM(bs.game_3ga)/COUNT(DISTINCT bs.`game_date`), 2) as game3GAavg, '
        . 'ROUND(SUM(bs.game_3gm) / NULLIF(SUM(bs.game_3ga), 0), 3) as game3GPavg, '
        . 'ROUND(SUM(bs.game_orb)/COUNT(DISTINCT bs.`game_date`), 1) as gameORBavg, '
        . 'ROUND((SUM(bs.game_orb) + SUM(bs.game_drb))/COUNT(DISTINCT bs.`game_date`), 1) as '
        . 'gameREBavg, ROUND(SUM(bs.game_ast)/COUNT(DISTINCT bs.`game_date`), 1) as '
        . 'gameASTavg, ROUND(SUM(bs.game_stl)/COUNT(DISTINCT bs.`game_date`), 1) as '
        . 'gameSTLavg, ROUND(SUM(bs.game_tov)/COUNT(DISTINCT bs.`game_date`), 1) as '
        . 'gameTOVavg, ROUND(SUM(bs.game_blk)/COUNT(DISTINCT bs.`game_date`), 1) as '
        . 'gameBLKavg, ROUND(SUM(bs.game_pf)/COUNT(DISTINCT bs.`game_date`), 1) as gamePFavg, '
        . 'ROUND(((2 * SUM(bs.game_2gm)) + SUM(bs.game_ftm) + (3 * '
        . 'SUM(bs.game_3gm)))/COUNT(DISTINCT bs.`game_date`), 1) as gamePTSavg FROM '
        . '`ibl_box_scores` bs JOIN `ibl_plr` p ON bs.pid = p.pid ';

    private const SPLIT_STATS_PREPARED_TAIL = ' GROUP BY p.name, bs.pos, bs.pid ORDER BY p.name ASC';

    private const SPLIT_STATS_EXECUTED_HEAD = 'SELECT p.name, bs.pos, bs.pid, COUNT(DISTINCT bs.game_date) as games, '
        . 'ROUND(SUM(bs.game_min)/COUNT(DISTINCT bs.game_date), 1) as gameMINavg, '
        . 'ROUND(SUM(bs.game_2gm + bs.game_3gm)/COUNT(DISTINCT bs.game_date), 2) as '
        . 'gameFGMavg, ROUND(SUM(bs.game_2ga + bs.game_3ga)/COUNT(DISTINCT bs.game_date), 2) '
        . 'as gameFGAavg, ROUND((SUM(bs.game_2gm) + SUM(bs.game_3gm)) / '
        . 'NULLIF(SUM(bs.game_2ga) + SUM(bs.game_3ga), 0), 3) as gameFGPavg, '
        . 'ROUND(SUM(bs.game_ftm)/COUNT(DISTINCT bs.game_date), 2) as gameFTMavg, '
        . 'ROUND(SUM(bs.game_fta)/COUNT(DISTINCT bs.game_date), 2) as gameFTAavg, '
        . 'ROUND(SUM(bs.game_ftm) / NULLIF(SUM(bs.game_fta), 0), 3) as gameFTPavg, '
        . 'ROUND(SUM(bs.game_3gm)/COUNT(DISTINCT bs.game_date), 2) as game3GMavg, '
        . 'ROUND(SUM(bs.game_3ga)/COUNT(DISTINCT bs.game_date), 2) as game3GAavg, '
        . 'ROUND(SUM(bs.game_3gm) / NULLIF(SUM(bs.game_3ga), 0), 3) as game3GPavg, '
        . 'ROUND(SUM(bs.game_orb)/COUNT(DISTINCT bs.game_date), 1) as gameORBavg, '
        . 'ROUND((SUM(bs.game_orb) + SUM(bs.game_drb))/COUNT(DISTINCT bs.game_date), 1) as '
        . 'gameREBavg, ROUND(SUM(bs.game_ast)/COUNT(DISTINCT bs.game_date), 1) as gameASTavg, '
        . 'ROUND(SUM(bs.game_stl)/COUNT(DISTINCT bs.game_date), 1) as gameSTLavg, '
        . 'ROUND(SUM(bs.game_tov)/COUNT(DISTINCT bs.game_date), 1) as gameTOVavg, '
        . 'ROUND(SUM(bs.game_blk)/COUNT(DISTINCT bs.game_date), 1) as gameBLKavg, '
        . 'ROUND(SUM(bs.game_pf)/COUNT(DISTINCT bs.game_date), 1) as gamePFavg, ROUND(((2 * '
        . 'SUM(bs.game_2gm)) + SUM(bs.game_ftm) + (3 * SUM(bs.game_3gm)))/COUNT(DISTINCT '
        . 'bs.game_date), 1) as gamePTSavg FROM ibl_box_scores bs JOIN ibl_plr p ON bs.pid = '
        . 'p.pid JOIN ibl_standings opp_s ON opp_s.teamid = CASE WHEN bs.home_teamid = 5 THEN '
        . 'bs.visitor_teamid ELSE bs.home_teamid END WHERE bs.season_year = 2026 AND '
        . '(bs.home_teamid = 5 OR bs.visitor_teamid = 5) AND bs.game_min > 0 AND p.teamid = 5 '
        . 'AND p.retired = 0 AND bs.game_type = 1 AND ';

    private const SPLIT_STATS_EXECUTED_TAIL = ' GROUP BY p.name, bs.pos, bs.pid ORDER BY p.name ASC';

    private const API_GAMES_HEAD = 'SELECT * FROM vw_schedule_upcoming WHERE (? = 0 OR season_year = ?) AND (? = 0 OR '
        . 'game_status = ?) AND (? = 0 OR (visitor_uuid = ? OR home_uuid = ?)) AND (? = 0 OR '
        . 'game_date = ?) AND (? = 0 OR game_date >= ?) AND (? = 0 OR game_date <= ?) ORDER BY ';

    private const API_GAMES_TAIL = ' LIMIT ? OFFSET ?';

    private const SEASON_LEADERS_HEAD = 'SELECT h.pid, h.name, h.year, h.team, h.teamid, h.games, h.minutes, h.fgm, h.fga, '
        . 'h.ftm, h.fta, h.tgm, h.tga, h.orb, h.reb, h.ast, h.stl, h.tvr, h.blk, h.pf, '
        . 't.team_city, t.color1, t.color2 FROM ibl_hist h LEFT JOIN ibl_team_info t ON '
        . 'h.teamid = t.teamid WHERE h.name IS NOT NULL ';

    private const PLAYER_SEARCH_HEAD = 'SELECT `ibl_plr`.*, `ibl_team_info`.`team_name` AS teamname, '
        . '`ibl_team_info`.`color1`, `ibl_team_info`.`color2` FROM `ibl_plr` LEFT JOIN '
        . '`ibl_team_info` ON `ibl_plr`.`teamid` = `ibl_team_info`.`teamid` WHERE '
        . '`ibl_plr`.`pid` > 0 AND (? = 0 OR `ibl_plr`.`retired` = 0) AND (? = 0 OR '
        . '`ibl_plr`.`name` LIKE ?) AND (? = 0 OR `ibl_plr`.`college` LIKE ?) AND (? = 0 OR '
        . '`ibl_plr`.`pos` = ?) AND (? = 0 OR `ibl_plr`.`age` <= ?) AND (? = 0 OR '
        . '`ibl_plr`.`exp` >= ?) AND (? = 0 OR `ibl_plr`.`exp` <= ?) AND (? = 0 OR '
        . '`ibl_plr`.`bird` >= ?) AND (? = 0 OR `ibl_plr`.`bird` <= ?) AND (? = 0 OR '
        . '`ibl_plr`.`clutch` >= ?) AND (? = 0 OR `ibl_plr`.`consistency` >= ?) AND (? = 0 OR '
        . '`ibl_plr`.`talent` >= ?) AND (? = 0 OR `ibl_plr`.`skill` >= ?) AND (? = 0 OR '
        . '`ibl_plr`.`intangibles` >= ?) AND (? = 0 OR `ibl_plr`.`oo` >= ?) AND (? = 0 OR '
        . '`ibl_plr`.`r_drive_off` >= ?) AND (? = 0 OR `ibl_plr`.`po` >= ?) AND (? = 0 OR '
        . '`ibl_plr`.`r_trans_off` >= ?) AND (? = 0 OR `ibl_plr`.`od` >= ?) AND (? = 0 OR '
        . '`ibl_plr`.`dd` >= ?) AND (? = 0 OR `ibl_plr`.`pd` >= ?) AND (? = 0 OR '
        . '`ibl_plr`.`td` >= ?) AND (? = 0 OR `ibl_plr`.`r_fga` >= ?) AND (? = 0 OR '
        . '`ibl_plr`.`r_fgp` >= ?) AND (? = 0 OR `ibl_plr`.`r_fta` >= ?) AND (? = 0 OR '
        . '`ibl_plr`.`r_ftp` >= ?) AND (? = 0 OR `ibl_plr`.`r_3ga` >= ?) AND (? = 0 OR '
        . '`ibl_plr`.`r_3gp` >= ?) AND (? = 0 OR `ibl_plr`.`r_orb` >= ?) AND (? = 0 OR '
        . '`ibl_plr`.`r_drb` >= ?) AND (? = 0 OR `ibl_plr`.`r_ast` >= ?) AND (? = 0 OR '
        . '`ibl_plr`.`r_stl` >= ?) AND (? = 0 OR `ibl_plr`.`r_blk` >= ?) AND (? = 0 OR '
        . '`ibl_plr`.`r_tvr` >= ?) AND (? = 0 OR `ibl_plr`.`r_foul` >= ?) ORDER BY '
        . '`ibl_plr`.`retired` ASC, `ibl_plr`.`ordinal` ASC, `ibl_plr`.`pid` ';

    private const PERIOD_AVERAGES_HEAD = 'SELECT p.name, bs.pos, bs.pid, COUNT(DISTINCT bs.game_date) as games, '
        . 'ROUND(SUM(bs.game_min)/COUNT(DISTINCT bs.game_date), 1) as gameMINavg, '
        . 'ROUND(SUM(bs.game_2gm + bs.game_3gm)/COUNT(DISTINCT bs.game_date), 2) as '
        . 'gameFGMavg, ROUND(SUM(bs.game_2ga + bs.game_3ga)/COUNT(DISTINCT bs.game_date), 2) '
        . 'as gameFGAavg, ROUND((SUM(bs.game_2gm) + SUM(bs.game_3gm)) / (SUM(bs.game_2ga) + '
        . 'SUM(bs.game_3ga)), 3) as gameFGPavg, ROUND(SUM(bs.game_ftm)/COUNT(DISTINCT '
        . 'bs.game_date), 2) as gameFTMavg, ROUND(SUM(bs.game_fta)/COUNT(DISTINCT '
        . 'bs.game_date), 2) as gameFTAavg, ROUND((SUM(bs.game_ftm)) / (SUM(bs.game_fta)), 3) '
        . 'as gameFTPavg, ROUND(SUM(bs.game_3gm)/COUNT(DISTINCT bs.game_date), 2) as '
        . 'game3GMavg, ROUND(SUM(bs.game_3ga)/COUNT(DISTINCT bs.game_date), 2) as game3GAavg, '
        . 'ROUND((SUM(bs.game_3gm)) / (SUM(bs.game_3ga)), 3) as game3GPavg, '
        . 'ROUND(SUM(bs.game_orb)/COUNT(DISTINCT bs.game_date), 1) as gameORBavg, '
        . 'ROUND((SUM(bs.game_orb) + SUM(bs.game_drb))/COUNT(DISTINCT bs.game_date), 1) as '
        . 'gameREBavg, ROUND(SUM(bs.game_ast)/COUNT(DISTINCT bs.game_date), 1) as gameASTavg, '
        . 'ROUND(SUM(bs.game_stl)/COUNT(DISTINCT bs.game_date), 1) as gameSTLavg, '
        . 'ROUND(SUM(bs.game_tov)/COUNT(DISTINCT bs.game_date), 1) as gameTOVavg, '
        . 'ROUND(SUM(bs.game_blk)/COUNT(DISTINCT bs.game_date), 1) as gameBLKavg, '
        . 'ROUND(SUM(bs.game_pf)/COUNT(DISTINCT bs.game_date) , 1) as gamePFavg, ROUND(((2 * '
        . 'SUM(bs.game_2gm)) + SUM(bs.game_ftm) + (3 * SUM(bs.game_3gm)))/COUNT(DISTINCT '
        . 'bs.game_date) , 1) as gamePTSavg FROM `ibl_box_scores` bs JOIN `ibl_plr` p ON '
        . 'bs.pid = p.pid WHERE bs.game_date BETWEEN ? AND ? AND ( bs.home_teamid = ? OR '
        . 'bs.visitor_teamid = ? ) AND bs.game_min > 0 AND p.teamid = ? AND p.retired = 0 ';

    /**
     * @return array<string, int|string>
     */
    private static function snapshotRow(): array
    {
        $row = [];
        foreach (self::SNAPSHOT_COLUMNS as $index => $column) {
            $row[$column] = match ($column) {
                'name' => 'Test Player',
                'snapshot_phase' => 'mid-season',
                'source_archive' => 'ibl5-2026.zip',
                'pos' => 'PG',
                default => $index + 1,
            };
        }

        return $row;
    }

    private static function snapshotUpsertSql(): string
    {
        $quote = static fn (string $column): string => '`' . $column . '`';
        $updatable = array_values(array_filter(
            self::SNAPSHOT_COLUMNS,
            static fn (string $column): bool => !in_array($column, ['pid', 'season_year', 'snapshot_phase'], true)
        ));

        return 'INSERT INTO `ibl_plr_snapshots` (' . implode(', ', array_map($quote, self::SNAPSHOT_COLUMNS)) . ') '
            . 'VALUES (' . implode(', ', array_fill(0, count(self::SNAPSHOT_COLUMNS), '?')) . ') '
            . 'ON DUPLICATE KEY UPDATE '
            . implode(', ', array_map(
                static fn (string $column): string => $quote($column) . ' = VALUES(' . $quote($column) . ')',
                $updatable
            ));
    }

    private static function periodTeam(): \Team\Team
    {
        $team = self::createStub(\Team\Team::class);
        $team->color1 = 'FF0000';
        $team->color2 = '0000FF';
        $team->teamid = 7;
        $team->name = 'Test Team';

        return $team;
    }

    private static function periodSeason(): \Season\Season
    {
        $season = self::createStub(\Season\Season::class);
        $season->lastSimStartDate = '2025-01-10';
        $season->lastSimEndDate = '2025-01-20';

        return $season;
    }

    /**
     * @param list<string> $queries
     * @return list<string>
     */
    private static function clearedSiteQueries(array $queries, ?string $filter): array
    {
        $normalized = array_map(self::normalizeSql(...), $queries);
        if ($filter === null) {
            return $normalized;
        }

        return array_values(array_filter(
            $normalized,
            static fn (string $sql): bool => str_contains($sql, $filter)
        ));
    }

    /**
     * Rows: [run, filter, expected prepared SQL, expected last bound params]. A non-null
     * filter keeps only the prepared queries containing it (the site under test shares its
     * call with an unrelated query). One string key per site so a later phase updates the
     * row for the site it rewrites.
     *
     * @return iterable<string, array{\Closure(MockDatabase): void, ?string, list<string>, list<mixed>}>
     */
    public static function clearedSitePreparedSqlProvider(): iterable
    {
        yield 'PlayerStats getPlayoffStats' => [
            static function (MockDatabase $db): void {
                (new PlayerStatsRepository($db))->getPlayoffStats('Test Player');
            },
            null,
            [
                'SELECT bs.season_year AS year, MIN(bs.pos) AS pos, bs.pid, p.name, fs.team_name AS '
                    . 'team, CAST(COUNT(*) AS SIGNED) AS games, CAST(SUM(bs.game_min) AS SIGNED) AS '
                    . 'minutes, CAST(SUM(bs.calc_fg_made) AS SIGNED) AS fgm, CAST(SUM(bs.game_2ga + '
                    . 'bs.game_3ga) AS SIGNED) AS fga, CAST(SUM(bs.game_ftm) AS SIGNED) AS ftm, '
                    . 'CAST(SUM(bs.game_fta) AS SIGNED) AS fta, CAST(SUM(bs.game_3gm) AS SIGNED) AS tgm, '
                    . 'CAST(SUM(bs.game_3ga) AS SIGNED) AS tga, CAST(SUM(bs.game_orb) AS SIGNED) AS orb, '
                    . 'CAST(SUM(bs.calc_rebounds) AS SIGNED) AS reb, CAST(SUM(bs.game_ast) AS SIGNED) AS '
                    . 'ast, CAST(SUM(bs.game_stl) AS SIGNED) AS stl, CAST(SUM(bs.game_tov) AS SIGNED) AS '
                    . 'tvr, CAST(SUM(bs.game_blk) AS SIGNED) AS blk, CAST(SUM(bs.game_pf) AS SIGNED) AS '
                    . 'pf, CAST(SUM(bs.calc_points) AS SIGNED) AS pts FROM `ibl_box_scores` bs JOIN '
                    . '`ibl_plr` p ON bs.pid = p.pid JOIN `ibl_franchise_seasons` fs ON bs.teamid = '
                    . 'fs.franchise_id AND bs.season_year = fs.season_ending_year WHERE bs.game_type = 2 '
                    . 'AND bs.game_min > 0 AND p.name = ? GROUP BY bs.pid, p.name, bs.season_year, '
                    . 'fs.team_name ORDER BY year ASC',
            ],
            ['Test Player'],
        ];
        yield 'PlayerStats getPlayoffCareerAverages' => [
            static function (MockDatabase $db): void {
                (new PlayerStatsRepository($db))->getPlayoffCareerAverages('Test Player');
            },
            null,
            [
                'SELECT bs.pid, p.name, CAST(COUNT(*) AS SIGNED) AS games, ROUND(AVG(bs.game_min), '
                    . '2) AS minutes, ROUND(AVG(bs.calc_fg_made), 2) AS fgm, ROUND(AVG(bs.game_2ga + '
                    . 'bs.game_3ga), 2) AS fga, CASE WHEN SUM(bs.game_2ga + bs.game_3ga) > 0 THEN '
                    . 'ROUND(SUM(bs.calc_fg_made) / SUM(bs.game_2ga + bs.game_3ga), 3) ELSE 0.000 END AS '
                    . 'fgpct, ROUND(AVG(bs.game_ftm), 2) AS ftm, ROUND(AVG(bs.game_fta), 2) AS fta, CASE '
                    . 'WHEN SUM(bs.game_fta) > 0 THEN ROUND(SUM(bs.game_ftm) / SUM(bs.game_fta), 3) ELSE '
                    . '0.000 END AS ftpct, ROUND(AVG(bs.game_3gm), 2) AS tgm, ROUND(AVG(bs.game_3ga), 2) '
                    . 'AS tga, CASE WHEN SUM(bs.game_3ga) > 0 THEN ROUND(SUM(bs.game_3gm) / '
                    . 'SUM(bs.game_3ga), 3) ELSE 0.000 END AS tpct, ROUND(AVG(bs.game_orb), 2) AS orb, '
                    . 'ROUND(AVG(bs.calc_rebounds), 2) AS reb, ROUND(AVG(bs.game_ast), 2) AS ast, '
                    . 'ROUND(AVG(bs.game_stl), 2) AS stl, ROUND(AVG(bs.game_tov), 2) AS tvr, '
                    . 'ROUND(AVG(bs.game_blk), 2) AS blk, ROUND(AVG(bs.game_pf), 2) AS pf, '
                    . 'ROUND(AVG(bs.calc_points), 2) AS pts, p.retired FROM `ibl_box_scores` bs JOIN '
                    . '`ibl_plr` p ON bs.pid = p.pid WHERE bs.game_type = 2 AND bs.game_min > 0 AND p.name '
                    . '= ? GROUP BY bs.pid, p.name, p.retired',
            ],
            ['Test Player'],
        ];
        yield 'PlayerStats getHeatStats' => [
            static function (MockDatabase $db): void {
                (new PlayerStatsRepository($db))->getHeatStats('Test Player');
            },
            null,
            [
                'SELECT bs.season_year AS year, MIN(bs.pos) AS pos, bs.pid, p.name, fs.team_name AS '
                    . 'team, CAST(COUNT(*) AS SIGNED) AS games, CAST(SUM(bs.game_min) AS SIGNED) AS '
                    . 'minutes, CAST(SUM(bs.calc_fg_made) AS SIGNED) AS fgm, CAST(SUM(bs.game_2ga + '
                    . 'bs.game_3ga) AS SIGNED) AS fga, CAST(SUM(bs.game_ftm) AS SIGNED) AS ftm, '
                    . 'CAST(SUM(bs.game_fta) AS SIGNED) AS fta, CAST(SUM(bs.game_3gm) AS SIGNED) AS tgm, '
                    . 'CAST(SUM(bs.game_3ga) AS SIGNED) AS tga, CAST(SUM(bs.game_orb) AS SIGNED) AS orb, '
                    . 'CAST(SUM(bs.calc_rebounds) AS SIGNED) AS reb, CAST(SUM(bs.game_ast) AS SIGNED) AS '
                    . 'ast, CAST(SUM(bs.game_stl) AS SIGNED) AS stl, CAST(SUM(bs.game_tov) AS SIGNED) AS '
                    . 'tvr, CAST(SUM(bs.game_blk) AS SIGNED) AS blk, CAST(SUM(bs.game_pf) AS SIGNED) AS '
                    . 'pf, CAST(SUM(bs.calc_points) AS SIGNED) AS pts FROM `ibl_box_scores` bs JOIN '
                    . '`ibl_plr` p ON bs.pid = p.pid JOIN `ibl_franchise_seasons` fs ON bs.teamid = '
                    . 'fs.franchise_id AND bs.season_year = fs.season_ending_year WHERE bs.game_type = 3 '
                    . 'AND bs.game_min > 0 AND p.name = ? GROUP BY bs.pid, p.name, bs.season_year, '
                    . 'fs.team_name ORDER BY year ASC',
            ],
            ['Test Player'],
        ];
        yield 'PlayerStats getHeatCareerAverages' => [
            static function (MockDatabase $db): void {
                (new PlayerStatsRepository($db))->getHeatCareerAverages('Test Player');
            },
            null,
            [
                'SELECT bs.pid, p.name, CAST(COUNT(*) AS SIGNED) AS games, ROUND(AVG(bs.game_min), '
                    . '2) AS minutes, ROUND(AVG(bs.calc_fg_made), 2) AS fgm, ROUND(AVG(bs.game_2ga + '
                    . 'bs.game_3ga), 2) AS fga, CASE WHEN SUM(bs.game_2ga + bs.game_3ga) > 0 THEN '
                    . 'ROUND(SUM(bs.calc_fg_made) / SUM(bs.game_2ga + bs.game_3ga), 3) ELSE 0.000 END AS '
                    . 'fgpct, ROUND(AVG(bs.game_ftm), 2) AS ftm, ROUND(AVG(bs.game_fta), 2) AS fta, CASE '
                    . 'WHEN SUM(bs.game_fta) > 0 THEN ROUND(SUM(bs.game_ftm) / SUM(bs.game_fta), 3) ELSE '
                    . '0.000 END AS ftpct, ROUND(AVG(bs.game_3gm), 2) AS tgm, ROUND(AVG(bs.game_3ga), 2) '
                    . 'AS tga, CASE WHEN SUM(bs.game_3ga) > 0 THEN ROUND(SUM(bs.game_3gm) / '
                    . 'SUM(bs.game_3ga), 3) ELSE 0.000 END AS tpct, ROUND(AVG(bs.game_orb), 2) AS orb, '
                    . 'ROUND(AVG(bs.calc_rebounds), 2) AS reb, ROUND(AVG(bs.game_ast), 2) AS ast, '
                    . 'ROUND(AVG(bs.game_stl), 2) AS stl, ROUND(AVG(bs.game_tov), 2) AS tvr, '
                    . 'ROUND(AVG(bs.game_blk), 2) AS blk, ROUND(AVG(bs.game_pf), 2) AS pf, '
                    . 'ROUND(AVG(bs.calc_points), 2) AS pts, p.retired FROM `ibl_box_scores` bs JOIN '
                    . '`ibl_plr` p ON bs.pid = p.pid WHERE bs.game_type = 3 AND bs.game_min > 0 AND p.name '
                    . '= ? GROUP BY bs.pid, p.name, p.retired',
            ],
            ['Test Player'],
        ];
        yield 'PlayerStats getSeasonCareerAverages' => [
            static function (MockDatabase $db): void {
                (new PlayerStatsRepository($db))->getSeasonCareerAverages('Test Player');
            },
            null,
            [
                'SELECT h.pid, p.name, CAST(SUM(h.games) AS SIGNED) AS games, ROUND(SUM(h.minutes) / '
                    . 'SUM(h.games), 2) AS minutes, ROUND(SUM(h.fgm) / SUM(h.games), 2) AS fgm, '
                    . 'ROUND(SUM(h.fga) / SUM(h.games), 2) AS fga, CASE WHEN SUM(h.fga) > 0 THEN '
                    . 'ROUND(SUM(h.fgm) / SUM(h.fga), 3) ELSE 0.000 END AS fgpct, ROUND(SUM(h.ftm) / '
                    . 'SUM(h.games), 2) AS ftm, ROUND(SUM(h.fta) / SUM(h.games), 2) AS fta, CASE WHEN '
                    . 'SUM(h.fta) > 0 THEN ROUND(SUM(h.ftm) / SUM(h.fta), 3) ELSE 0.000 END AS ftpct, '
                    . 'ROUND(SUM(h.tgm) / SUM(h.games), 2) AS tgm, ROUND(SUM(h.tga) / SUM(h.games), 2) AS '
                    . 'tga, CASE WHEN SUM(h.tga) > 0 THEN ROUND(SUM(h.tgm) / SUM(h.tga), 3) ELSE 0.000 END '
                    . 'AS tpct, ROUND(SUM(h.orb) / SUM(h.games), 2) AS orb, ROUND(SUM(h.reb) / '
                    . 'SUM(h.games), 2) AS reb, ROUND(SUM(h.ast) / SUM(h.games), 2) AS ast, '
                    . 'ROUND(SUM(h.stl) / SUM(h.games), 2) AS stl, ROUND(SUM(h.tvr) / SUM(h.games), 2) AS '
                    . 'tvr, ROUND(SUM(h.blk) / SUM(h.games), 2) AS blk, ROUND(SUM(h.pf) / SUM(h.games), 2) '
                    . 'AS pf, ROUND(SUM(h.pts) / SUM(h.games), 2) AS pts, p.retired FROM `ibl_hist` h JOIN '
                    . '`ibl_plr` p ON h.pid = p.pid WHERE h.games > 0 AND p.name = ? GROUP BY h.pid, '
                    . 'p.name, p.retired',
            ],
            ['Test Player'],
        ];
        yield 'PlayerStats getSeasonCareerAveragesById' => [
            static function (MockDatabase $db): void {
                (new PlayerStatsRepository($db))->getSeasonCareerAveragesById(42);
            },
            null,
            [
                'SELECT h.pid, p.name, CAST(SUM(h.games) AS SIGNED) AS games, ROUND(SUM(h.minutes) / '
                    . 'SUM(h.games), 2) AS minutes, ROUND(SUM(h.fgm) / SUM(h.games), 2) AS fgm, '
                    . 'ROUND(SUM(h.fga) / SUM(h.games), 2) AS fga, CASE WHEN SUM(h.fga) > 0 THEN '
                    . 'ROUND(SUM(h.fgm) / SUM(h.fga), 3) ELSE 0.000 END AS fgpct, ROUND(SUM(h.ftm) / '
                    . 'SUM(h.games), 2) AS ftm, ROUND(SUM(h.fta) / SUM(h.games), 2) AS fta, CASE WHEN '
                    . 'SUM(h.fta) > 0 THEN ROUND(SUM(h.ftm) / SUM(h.fta), 3) ELSE 0.000 END AS ftpct, '
                    . 'ROUND(SUM(h.tgm) / SUM(h.games), 2) AS tgm, ROUND(SUM(h.tga) / SUM(h.games), 2) AS '
                    . 'tga, CASE WHEN SUM(h.tga) > 0 THEN ROUND(SUM(h.tgm) / SUM(h.tga), 3) ELSE 0.000 END '
                    . 'AS tpct, ROUND(SUM(h.orb) / SUM(h.games), 2) AS orb, ROUND(SUM(h.reb) / '
                    . 'SUM(h.games), 2) AS reb, ROUND(SUM(h.ast) / SUM(h.games), 2) AS ast, '
                    . 'ROUND(SUM(h.stl) / SUM(h.games), 2) AS stl, ROUND(SUM(h.tvr) / SUM(h.games), 2) AS '
                    . 'tvr, ROUND(SUM(h.blk) / SUM(h.games), 2) AS blk, ROUND(SUM(h.pf) / SUM(h.games), 2) '
                    . 'AS pf, ROUND(SUM(h.pts) / SUM(h.games), 2) AS pts, p.retired FROM `ibl_hist` h JOIN '
                    . '`ibl_plr` p ON h.pid = p.pid WHERE h.games > 0 AND h.pid = ? GROUP BY h.pid, '
                    . 'p.name, p.retired',
            ],
            [42],
        ];
        yield 'PlrBoxScore sumStatsByGameTypeThroughDate' => [
            static function (MockDatabase $db): void {
                (new PlrBoxScoreRepository($db))->sumStatsByGameTypeThroughDate(2026, 1, '2026-01-15');
            },
            null,
            [
                'SELECT pid, SUM(CASE WHEN game_min > 0 THEN 1 ELSE 0 END) AS gp, SUM(game_min) AS '
                    . 'min, SUM(game_2gm) AS two_gm, SUM(game_2ga) AS two_ga, SUM(game_ftm) AS ftm, '
                    . 'SUM(game_fta) AS fta, SUM(game_3gm) AS three_gm, SUM(game_3ga) AS three_ga, '
                    . 'SUM(game_orb) AS orb, SUM(game_drb) AS drb, SUM(game_ast) AS ast, SUM(game_stl) AS '
                    . 'stl, SUM(game_tov) AS tov, SUM(game_blk) AS blk, SUM(game_pf) AS pf FROM '
                    . '`ibl_box_scores` WHERE season_year = ? AND game_type = ? AND game_date <= ? AND pid '
                    . 'IS NOT NULL AND teamid NOT IN (40, 41, 50, 51) GROUP BY pid',
            ],
            [2026, 1, '2026-01-15'],
        ];
        yield 'PlrBoxScore getSingleGameMaximumsThroughDate' => [
            static function (MockDatabase $db): void {
                (new PlrBoxScoreRepository($db))->getSingleGameMaximumsThroughDate(2026, 1, '2026-01-15');
            },
            null,
            [
                'SELECT pid, MAX(calc_points) AS high_pts, MAX(calc_rebounds) AS high_reb, '
                    . 'MAX(game_ast) AS high_ast, MAX(game_stl) AS high_stl, MAX(game_blk) AS high_blk, '
                    . 'SUM(CASE WHEN ( (calc_points >= 10) + (calc_rebounds >= 10) + (game_ast >= 10) + '
                    . '(game_stl >= 10) + (game_blk >= 10) ) = 2 THEN 1 ELSE 0 END) AS doubles, SUM(CASE '
                    . 'WHEN ( (calc_points >= 10) + (calc_rebounds >= 10) + (game_ast >= 10) + (game_stl '
                    . '>= 10) + (game_blk >= 10) ) >= 3 THEN 1 ELSE 0 END) AS triples FROM '
                    . '`ibl_box_scores` WHERE season_year = ? AND game_type = ? AND game_date <= ? AND pid '
                    . 'IS NOT NULL AND game_min > 0 AND teamid NOT IN (40, 41, 50, 51) GROUP BY pid',
            ],
            [2026, 1, '2026-01-15'],
        ];
        yield 'PlrBoxScore cumulativeRegularSeasonStatsByDate' => [
            static function (MockDatabase $db): void {
                (new PlrBoxScoreRepository($db))->cumulativeRegularSeasonStatsByDate(42, 2026);
            },
            null,
            [
                'SELECT game_date AS date, SUM(CASE WHEN game_min > 0 THEN 1 ELSE 0 END) AS gp, '
                    . 'SUM(game_min) AS min, SUM(game_2gm) AS two_gm, SUM(game_2ga) AS two_ga, '
                    . 'SUM(game_ftm) AS ftm, SUM(game_fta) AS fta, SUM(game_3gm) AS three_gm, '
                    . 'SUM(game_3ga) AS three_ga, SUM(game_orb) AS orb, SUM(game_drb) AS drb, '
                    . 'SUM(game_ast) AS ast, SUM(game_stl) AS stl, SUM(game_tov) AS tov, SUM(game_blk) AS '
                    . 'blk, SUM(game_pf) AS pf FROM `ibl_box_scores` WHERE pid = ? AND season_year = ? AND '
                    . 'game_type = 1 AND teamid NOT IN (40, 41, 50, 51) GROUP BY game_date ORDER BY '
                    . 'game_date',
            ],
            [42, 2026],
        ];
        yield 'SplitStats home' => [
            static function (MockDatabase $db): void {
                (new SplitStatsRepository($db))->getSplitStats(5, 2026, 'home');
            },
            null,
            [
                self::SPLIT_STATS_PREPARED_HEAD
                    . 'WHERE bs.season_year = ? AND (bs.home_teamid = ? OR bs.visitor_teamid = ?) AND '
                    . 'bs.game_min > 0 AND p.teamid = ? AND p.retired = 0 AND bs.game_type = 1 AND '
                    . 'bs.home_teamid = ?'
                    . self::SPLIT_STATS_PREPARED_TAIL,
            ],
            [2026, 5, 5, 5, 5],
        ];
        yield 'SplitStats wins' => [
            static function (MockDatabase $db): void {
                (new SplitStatsRepository($db))->getSplitStats(5, 2026, 'wins');
            },
            null,
            [
                self::SPLIT_STATS_PREPARED_HEAD
                    . 'JOIN `ibl_schedule` sch ON bs.`game_date` = sch.`game_date` AND ((sch.home_teamid = '
                    . 'bs.home_teamid AND sch.visitor_teamid = bs.visitor_teamid) OR (sch.home_teamid = '
                    . 'bs.visitor_teamid AND sch.visitor_teamid = bs.home_teamid)) WHERE bs.season_year = '
                    . '? AND (bs.home_teamid = ? OR bs.visitor_teamid = ?) AND bs.game_min > 0 AND '
                    . 'p.teamid = ? AND p.retired = 0 AND bs.game_type = 1 AND ((sch.home_teamid = ? AND '
                    . 'sch.home_score > sch.visitor_score) OR (sch.visitor_teamid = ? AND '
                    . 'sch.visitor_score > sch.home_score))'
                    . self::SPLIT_STATS_PREPARED_TAIL,
            ],
            [2026, 5, 5, 5, 5, 5],
        ];
        yield 'SplitStats vs_7' => [
            static function (MockDatabase $db): void {
                (new SplitStatsRepository($db))->getSplitStats(5, 2026, 'vs_7');
            },
            null,
            [
                self::SPLIT_STATS_PREPARED_HEAD
                    . 'WHERE bs.season_year = ? AND (bs.home_teamid = ? OR bs.visitor_teamid = ?) AND '
                    . 'bs.game_min > 0 AND p.teamid = ? AND p.retired = 0 AND bs.game_type = 1 AND CASE '
                    . 'WHEN bs.home_teamid = ? THEN bs.visitor_teamid ELSE bs.home_teamid END = ?'
                    . self::SPLIT_STATS_PREPARED_TAIL,
            ],
            [2026, 5, 5, 5, 5, 7],
        ];
        yield 'SplitStats unknown' => [
            static function (MockDatabase $db): void {
                (new SplitStatsRepository($db))->getSplitStats(5, 2026, 'unknown');
            },
            null,
            [
                self::SPLIT_STATS_PREPARED_HEAD
                    . 'WHERE bs.season_year = ? AND (bs.home_teamid = ? OR bs.visitor_teamid = ?) AND '
                    . 'bs.game_min > 0 AND p.teamid = ? AND p.retired = 0 AND bs.game_type = 1'
                    . self::SPLIT_STATS_PREPARED_TAIL,
            ],
            [2026, 5, 5, 5],
        ];
        yield 'LastSimRecap getActiveInjuriesForPlayers' => [
            static function (MockDatabase $db): void {
                (new LastSimRecapRepository($db))->getActiveInjuriesForPlayers([1, 2, 3], '2026-01-15');
            },
            null,
            [
                'SELECT t.id, t.pid, p.name, p.pos, STR_TO_DATE(CONCAT(CASE WHEN t.transaction_month >= 9 '
                    . 'THEN t.season_year - 1 ELSE t.season_year END, \'-\', t.transaction_month, \'-\', '
                    . 't.transaction_day), \'%Y-%c-%e\') AS injury_date, t.injury_description, '
                    . 't.injury_games_missed, DATEDIFF(DATE_ADD(STR_TO_DATE(CONCAT(CASE WHEN '
                    . 't.transaction_month >= 9 THEN t.season_year - 1 ELSE t.season_year END, \'-\', '
                    . 't.transaction_month, \'-\', t.transaction_day), \'%Y-%c-%e\'), INTERVAL '
                    . 't.injury_games_missed DAY), ?) AS days_remaining, '
                    . 'DATE_FORMAT(DATE_ADD(STR_TO_DATE(CONCAT(CASE WHEN t.transaction_month >= 9 THEN '
                    . 't.season_year - 1 ELSE t.season_year END, \'-\', t.transaction_month, \'-\', '
                    . 't.transaction_day), \'%Y-%c-%e\'), INTERVAL t.injury_games_missed DAY), \'%Y-%m-%d\') '
                    . 'AS return_date, (STR_TO_DATE(CONCAT(CASE WHEN t.transaction_month >= 9 THEN '
                    . 't.season_year - 1 ELSE t.season_year END, \'-\', t.transaction_month, \'-\', '
                    . 't.transaction_day), \'%Y-%c-%e\') = ?) AS is_new FROM `ibl_jsb_transactions` t JOIN '
                    . '`ibl_plr` p ON p.pid = t.pid WHERE t.transaction_type = 1 AND t.pid IN '
                    . '(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) '
                    . 'AND t.injury_games_missed IS NOT NULL AND STR_TO_DATE(CONCAT(CASE WHEN '
                    . 't.transaction_month >= 9 THEN t.season_year - 1 ELSE t.season_year END, \'-\', '
                    . 't.transaction_month, \'-\', t.transaction_day), \'%Y-%c-%e\') <= ? AND '
                    . 'DATE_ADD(STR_TO_DATE(CONCAT(CASE WHEN t.transaction_month >= 9 THEN t.season_year - '
                    . '1 ELSE t.season_year END, \'-\', t.transaction_month, \'-\', t.transaction_day), '
                    . '\'%Y-%c-%e\'), INTERVAL t.injury_games_missed DAY) > ? ORDER BY is_new DESC, '
                    . 'injury_date DESC, t.id DESC',
            ],
            ['2026-01-15', '2026-01-15', 1, 2, 3, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, '2026-01-15', '2026-01-15'],
        ];
        yield 'LastSimRecap getStarterPidsFromBoxScores' => [
            static function (MockDatabase $db): void {
                $db->onQuery('ibl_schedule.? WHERE id = 7',[['game_date' => '2026-01-15', 'visitor_teamid' => 1, 'home_teamid' => 2]]);
                (new LastSimRecapRepository($db))->getStarterPidsFromBoxScores(7, 3);
            },
            null,
            [
                'SELECT game_date, visitor_teamid, home_teamid FROM `ibl_schedule` WHERE id = ? '
                    . 'LIMIT 1',
                'SELECT pid, pos, game_min FROM `ibl_box_scores` WHERE game_date = ? AND '
                    . 'visitor_teamid = ? AND home_teamid = ? AND teamid = ? AND game_min > 0 ORDER BY '
                    . 'game_min DESC, pid ASC',
            ],
            ['2026-01-15', 1, 2, 3],
        ];
        yield 'TeamSchedule getSchedule' => [
            static function (MockDatabase $db): void {
                (new TeamScheduleRepository($db))->getSchedule(5, 2026);
            },
            null,
            [
                'SELECT s.*, bst.game_of_that_day FROM `ibl_schedule` s LEFT JOIN (SELECT game_date, '
                    . 'visitor_teamid, home_teamid, MIN(game_of_that_day) AS game_of_that_day FROM '
                    . '`ibl_box_scores_teams` GROUP BY game_date, visitor_teamid, home_teamid) bst ON '
                    . 'bst.game_date = s.game_date AND bst.visitor_teamid = s.visitor_teamid AND '
                    . 'bst.home_teamid = s.home_teamid WHERE s.season_year = ? AND (s.visitor_teamid = ? '
                    . 'OR s.home_teamid = ?) ORDER BY s.game_date ASC, s.id ASC',
            ],
            [2026, 5, 5],
        ];
        yield 'LeagueSchedule getAllGamesWithBoxScoreInfo' => [
            static function (MockDatabase $db): void {
                (new LeagueScheduleRepository($db))->getAllGamesWithBoxScoreInfo(2026);
            },
            null,
            [
                'SELECT s.id, s.game_date, s.visitor_teamid, s.visitor_score, s.home_teamid, '
                    . 's.home_score, s.box_id, bst.game_of_that_day FROM `ibl_schedule` s LEFT JOIN '
                    . '(SELECT game_date, visitor_teamid, home_teamid, MIN(game_of_that_day) AS '
                    . 'game_of_that_day FROM `ibl_box_scores_teams` GROUP BY game_date, visitor_teamid, '
                    . 'home_teamid) bst ON bst.game_date = s.game_date AND bst.visitor_teamid = '
                    . 's.visitor_teamid AND bst.home_teamid = s.home_teamid WHERE s.season_year = ? ORDER '
                    . 'BY s.game_date ASC, s.id ASC',
            ],
            [2026],
        ];
        yield 'FranchiseRecordBook getTeamSingleSeasonRecords' => [
            static function (MockDatabase $db): void {
                (new FranchiseRecordBookRepository($db))->getTeamSingleSeasonRecords(5);
            },
            null,
            [
                'SELECT r.id, r.scope, r.teamid, r.record_type, r.stat_category, r.ranking, '
                    . 'r.player_name, r.car_block_id, COALESCE(r.pid, plr.pid) AS pid, r.stat_value, '
                    . 'r.stat_raw, r.team_of_record, r.season_year, r.career_total FROM '
                    . '`ibl_rcb_alltime_records` r LEFT JOIN ( SELECT REPLACE(name, \'\'\'\', \'\') AS '
                    . 'clean_name, MAX(pid) AS pid FROM `ibl_plr` GROUP BY clean_name ) plr ON '
                    . 'plr.clean_name = r.player_name WHERE r.scope = \'team\' AND r.teamid = ? AND '
                    . 'r.record_type = \'single_season\' AND r.ranking <= ? ORDER BY r.stat_category, '
                    . 'r.ranking, r.id ASC',
            ],
            [5, 10],
        ];
        yield 'FranchiseRecordBook getLeagueCareerRecords' => [
            static function (MockDatabase $db): void {
                (new FranchiseRecordBookRepository($db))->getLeagueCareerRecords();
            },
            null,
            [
                'SELECT r.id, r.scope, r.teamid, r.record_type, r.stat_category, r.ranking, '
                    . 'r.player_name, r.car_block_id, COALESCE(r.pid, plr.pid) AS pid, r.stat_value, '
                    . 'r.stat_raw, r.team_of_record, r.season_year, r.career_total FROM '
                    . '`ibl_rcb_alltime_records` r LEFT JOIN ( SELECT REPLACE(name, \'\'\'\', \'\') AS '
                    . 'clean_name, MAX(pid) AS pid FROM `ibl_plr` GROUP BY clean_name ) plr ON '
                    . 'plr.clean_name = r.player_name WHERE r.scope = \'league\' AND r.record_type = '
                    . '\'career\' AND r.ranking <= ? ORDER BY r.stat_category, r.ranking, r.id ASC',
            ],
            [10],
        ];
        yield 'FranchiseRecordBook getLeagueSingleSeasonRecords' => [
            static function (MockDatabase $db): void {
                (new FranchiseRecordBookRepository($db))->getLeagueSingleSeasonRecords();
            },
            null,
            [
                'SELECT r.id, r.scope, r.teamid, r.record_type, r.stat_category, r.ranking, '
                    . 'r.player_name, r.car_block_id, COALESCE(r.pid, plr.pid) AS pid, r.stat_value, '
                    . 'r.stat_raw, r.team_of_record, r.season_year, r.career_total FROM '
                    . '`ibl_rcb_alltime_records` r LEFT JOIN ( SELECT REPLACE(name, \'\'\'\', \'\') AS '
                    . 'clean_name, MAX(pid) AS pid FROM `ibl_plr` GROUP BY clean_name ) plr ON '
                    . 'plr.clean_name = r.player_name WHERE r.scope = \'league\' AND r.record_type = '
                    . '\'single_season\' AND r.ranking <= ? ORDER BY r.stat_category, r.ranking, r.id ASC',
            ],
            [10],
        ];
        yield 'Topics default prefix' => [
            static function (MockDatabase $db): void {
                $db->onQuery('nuke_topics t', [['topicid' => 1, 'topicname' => 'topic', 'topicimage' => 'topic.gif', 'topictext' => 'Topic Text', 'stories' => 1, 'total_reads' => 0]]);
                (new TopicsRepository($db))->getTopicsWithArticles();
            },
            null,
            [
                'SELECT t.topicid, t.topicname, t.topicimage, t.topictext, COUNT(s.sid) AS stories, '
                    . 'COALESCE(SUM(s.counter), 0) AS total_reads FROM nuke_topics t LEFT JOIN '
                    . 'nuke_stories s ON (s.topic = t.topicid) GROUP BY t.topicid, t.topicname, '
                    . 't.topicimage, t.topictext ORDER BY t.topictext',
                'SELECT s.sid, s.catid, s.title, COALESCE(c.title, \'\') AS cat_title FROM '
                    . 'nuke_stories s LEFT JOIN nuke_stories_cat c ON s.catid = c.catid WHERE s.topic = ? '
                    . 'ORDER BY s.sid DESC LIMIT 10',
            ],
            [1],
        ];
        yield 'Topics explicit nuke' => [
            static function (MockDatabase $db): void {
                $db->onQuery('nuke_topics t', [['topicid' => 1, 'topicname' => 'topic', 'topicimage' => 'topic.gif', 'topictext' => 'Topic Text', 'stories' => 1, 'total_reads' => 0]]);
                (new TopicsRepository($db, 'nuke'))->getTopicsWithArticles();
            },
            null,
            [
                'SELECT t.topicid, t.topicname, t.topicimage, t.topictext, COUNT(s.sid) AS stories, '
                    . 'COALESCE(SUM(s.counter), 0) AS total_reads FROM nuke_topics t LEFT JOIN '
                    . 'nuke_stories s ON (s.topic = t.topicid) GROUP BY t.topicid, t.topicname, '
                    . 't.topicimage, t.topictext ORDER BY t.topictext',
                'SELECT s.sid, s.catid, s.title, COALESCE(c.title, \'\') AS cat_title FROM '
                    . 'nuke_stories s LEFT JOIN nuke_stories_cat c ON s.catid = c.catid WHERE s.topic = ? '
                    . 'ORDER BY s.sid DESC LIMIT 10',
            ],
            [1],
        ];
        yield 'Topics hostile prefix' => [
            static function (MockDatabase $db): void {
                $db->onQuery('nuke_topics t', [['topicid' => 1, 'topicname' => 'topic', 'topicimage' => 'topic.gif', 'topictext' => 'Topic Text', 'stories' => 1, 'total_reads' => 0]]);
                (new TopicsRepository($db, 'bad prefix; DROP TABLE nuke_topics'))->getTopicsWithArticles();
            },
            null,
            [
                'SELECT t.topicid, t.topicname, t.topicimage, t.topictext, COUNT(s.sid) AS stories, '
                    . 'COALESCE(SUM(s.counter), 0) AS total_reads FROM nuke_topics t LEFT JOIN '
                    . 'nuke_stories s ON (s.topic = t.topicid) GROUP BY t.topicid, t.topicname, '
                    . 't.topicimage, t.topictext ORDER BY t.topictext',
                'SELECT s.sid, s.catid, s.title, COALESCE(c.title, \'\') AS cat_title FROM '
                    . 'nuke_stories s LEFT JOIN nuke_stories_cat c ON s.catid = c.catid WHERE s.topic = ? '
                    . 'ORDER BY s.sid DESC LIMIT 10',
            ],
            [1],
        ];
        yield 'ApiGame getGames no filters' => [
            static function (MockDatabase $db): void {
                (new ApiGameRepository($db))->getGames(new Paginator([], 'game_date', ['game_date', 'visitor_score', 'home_score']));
            },
            null,
            [
                self::API_GAMES_HEAD
                    . 'game_date ASC'
                    . self::API_GAMES_TAIL,
            ],
            [0, 0, 0, '', 0, '', '', 0, '', 0, '', 0, '', 25, 0],
        ];
        yield 'ApiGame getGames all filters' => [
            static function (MockDatabase $db): void {
                (new ApiGameRepository($db))->getGames(new Paginator(['page' => '2', 'per_page' => '10', 'sort' => 'home_score', 'order' => 'desc'], 'game_date', ['game_date', 'visitor_score', 'home_score']), ['season' => '2026', 'status' => 'Scheduled', 'team' => 'team-uuid', 'date' => '2026-01-15', 'date_start' => '2026-01-01', 'date_end' => '2026-01-31']);
            },
            null,
            [
                self::API_GAMES_HEAD
                    . 'home_score DESC'
                    . self::API_GAMES_TAIL,
            ],
            [1, 2026, 1, 'Scheduled', 1, 'team-uuid', 'team-uuid', 1, '2026-01-15', 1, '2026-01-01', 1, '2026-01-31', 10, 10],
        ];
        yield 'ApiGame countGames no filters' => [
            static function (MockDatabase $db): void {
                (new ApiGameRepository($db))->countGames();
            },
            null,
            [
                'SELECT COUNT(*) AS total FROM vw_schedule_upcoming WHERE (? = 0 OR season_year = ?) '
                    . 'AND (? = 0 OR game_status = ?) AND (? = 0 OR (visitor_uuid = ? OR home_uuid = ?)) '
                    . 'AND (? = 0 OR game_date = ?) AND (? = 0 OR game_date >= ?) AND (? = 0 OR game_date '
                    . '<= ?)',
            ],
            [0, 0, 0, '', 0, '', '', 0, '', 0, '', 0, ''],
        ];
        yield 'ApiGame countGames all filters' => [
            static function (MockDatabase $db): void {
                (new ApiGameRepository($db))->countGames(['season' => '2026', 'status' => 'Scheduled', 'team' => 'team-uuid', 'date' => '2026-01-15', 'date_start' => '2026-01-01', 'date_end' => '2026-01-31']);
            },
            null,
            [
                'SELECT COUNT(*) AS total FROM vw_schedule_upcoming WHERE (? = 0 OR season_year = ?) '
                    . 'AND (? = 0 OR game_status = ?) AND (? = 0 OR (visitor_uuid = ? OR home_uuid = ?)) '
                    . 'AND (? = 0 OR game_date = ?) AND (? = 0 OR game_date >= ?) AND (? = 0 OR game_date '
                    . '<= ?)',
            ],
            [1, 2026, 1, 'Scheduled', 1, 'team-uuid', 'team-uuid', 1, '2026-01-15', 1, '2026-01-01', 1, '2026-01-31'],
        ];
        yield 'ApiLeaders getLeaders no filters' => [
            static function (MockDatabase $db): void {
                (new ApiLeadersRepository($db))->getLeaders(new Paginator([], 'ppg', ['ppg']));
            },
            null,
            [
                'SELECT h.*, p.uuid AS player_uuid, t.uuid AS team_uuid, t.team_city, t.team_name '
                    . 'FROM `ibl_hist` h JOIN `ibl_plr` p ON h.pid = p.pid LEFT JOIN `ibl_team_info` t ON '
                    . 'h.teamid = t.teamid WHERE h.games > 0 ORDER BY ((2*`fgm`+`ftm`+`tgm`)/`games`) DESC '
                    . 'LIMIT ? OFFSET ?',
            ],
            [25, 0],
        ];
        yield 'ApiLeaders getLeaders season and min_games' => [
            static function (MockDatabase $db): void {
                (new ApiLeadersRepository($db))->getLeaders(new Paginator(['page' => '3', 'per_page' => '5'], 'ppg', ['ppg']), ['season' => '2026', 'min_games' => '10']);
            },
            null,
            [
                'SELECT h.*, p.uuid AS player_uuid, t.uuid AS team_uuid, t.team_city, t.team_name '
                    . 'FROM `ibl_hist` h JOIN `ibl_plr` p ON h.pid = p.pid LEFT JOIN `ibl_team_info` t ON '
                    . 'h.teamid = t.teamid WHERE h.games > 0 AND h.year = ? AND h.games >= ? ORDER BY '
                    . '((2*`fgm`+`ftm`+`tgm`)/`games`) DESC LIMIT ? OFFSET ?',
            ],
            [2026, 10, 5, 10],
        ];
        yield 'ApiLeaders countLeaders no filters' => [
            static function (MockDatabase $db): void {
                (new ApiLeadersRepository($db))->countLeaders();
            },
            null,
            [
                'SELECT COUNT(*) AS total FROM `ibl_hist` h WHERE h.games > 0',
            ],
            [],
        ];
        yield 'ApiLeaders countLeaders season and min_games' => [
            static function (MockDatabase $db): void {
                (new ApiLeadersRepository($db))->countLeaders(['season' => '2026', 'min_games' => '10']);
            },
            null,
            [
                'SELECT COUNT(*) AS total FROM `ibl_hist` h WHERE h.games > 0 AND h.year = ? AND '
                    . 'h.games >= ?',
            ],
            [2026, 10],
        ];
        yield 'TransactionHistory no filters' => [
            static function (MockDatabase $db): void {
                (new TransactionHistoryRepository($db))->getTransactions(null, null, null);
            },
            null,
            [
                'SELECT sid, catid, title, time FROM nuke_stories WHERE catid IN (1, 2, 3, 8, 10, '
                    . '14) ORDER BY time DESC, sid DESC LIMIT 500',
            ],
            [],
        ];
        yield 'TransactionHistory category year month' => [
            static function (MockDatabase $db): void {
                (new TransactionHistoryRepository($db))->getTransactions(3, 2026, 2);
            },
            null,
            [
                'SELECT sid, catid, title, time FROM nuke_stories WHERE catid IN (1, 2, 3, 8, 10, '
                    . '14) AND catid = ? AND time >= ? AND time < ? ORDER BY time DESC, sid DESC LIMIT 500',
            ],
            [3, '2026-02-01 00:00:00', '2026-03-01 00:00:00'],
        ];
        yield 'PlayerSearch no filters' => [
            static function (MockDatabase $db): void {
                (new PlayerSearchRepository($db))->searchPlayers(['active' => 0, 'search_name' => null, 'college' => null, 'pos' => null, 'age' => null, 'exp' => null, 'exp_max' => null, 'bird' => null, 'bird_max' => null]);
            },
            null,
            [
                self::PLAYER_SEARCH_HEAD
                    . 'ASC',
            ],
            [1, 0, '', 0, '', 0, '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        ];
        yield 'PlayerSearch pos age rating' => [
            static function (MockDatabase $db): void {
                (new PlayerSearchRepository($db))->searchPlayers(['active' => 0, 'search_name' => null, 'college' => null, 'pos' => 'PG', 'age' => 30, 'exp' => null, 'exp_max' => null, 'bird' => null, 'bird_max' => null, 'r_fga' => 50]);
            },
            null,
            [
                self::PLAYER_SEARCH_HEAD
                    . 'ASC',
            ],
            [1, 0, '', 0, '', 1, 'PG', 1, 30, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 50, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        ];
        yield 'AwardHistory no filters' => [
            static function (MockDatabase $db): void {
                (new AwardHistoryRepository($db))->searchAwards(['year' => null, 'award' => null, 'name' => null, 'sortby' => 1]);
            },
            null,
            [
                'SELECT a.year, a.award, a.name, a.table_id, p.pid FROM `ibl_awards` a LEFT JOIN '
                    . '`ibl_plr` p ON a.name = p.name WHERE 1=1 ORDER BY a.name ASC',
            ],
            [],
        ];
        yield 'AwardHistory year and name' => [
            static function (MockDatabase $db): void {
                (new AwardHistoryRepository($db))->searchAwards(['year' => 2026, 'award' => null, 'name' => 'Smith', 'sortby' => 3]);
            },
            null,
            [
                'SELECT a.year, a.award, a.name, a.table_id, p.pid FROM `ibl_awards` a LEFT JOIN '
                    . '`ibl_plr` p ON a.name = p.name WHERE 1=1 AND a.year = ? AND a.name LIKE ? ORDER BY '
                    . 'a.year ASC',
            ],
            [2026, '%Smith%'],
        ];
        yield 'PlrParser upsertSnapshot' => [
            static function (MockDatabase $db): void {
                (new PlrParserRepository($db))->upsertSnapshot(self::snapshotRow());
            },
            null,
            [
                self::snapshotUpsertSql(),
            ],
            array_values(self::snapshotRow()),
        ];
        yield 'ProjectedDraftOrder saveFinalDraftOrder' => [
            static function (MockDatabase $db): void {
                (new ProjectedDraftOrderRepository($db))->saveFinalDraftOrder(2026, [['round' => 1, 'pick' => 1, 'team' => 'Team A', 'teamid' => 1], ['round' => 1, 'pick' => 2, 'team' => 'Team B', 'teamid' => 2]]);
            },
            null,
            [
                'DELETE FROM `ibl_draft` WHERE year < ?',
                'DELETE FROM `ibl_draft` WHERE year = ? AND player = \'\'',
                'INSERT INTO `ibl_draft` (`year`, `round`, `pick`, `team`, `teamid`, `player`, '
                    . '`uuid`) VALUES (?, ?, ?, ?, ?, \'\', UUID())',
                'INSERT INTO `ibl_draft` (`year`, `round`, `pick`, `team`, `teamid`, `player`, '
                    . '`uuid`) VALUES (?, ?, ?, ?, ?, \'\', UUID())',
                'UPDATE `ibl_settings` SET setting_value = \'Yes\' WHERE setting_key = \'Draft Order '
                    . 'Finalized\' AND league = \'ibl\'',
            ],
            [2026, 1, 2, 'Team B', 2],
        ];
        yield 'BugReportClaim transition status only' => [
            static function (MockDatabase $db): void {
                (new BugReportClaimRepository($db))->transition(7, 'claimed');
            },
            null,
            [
                'UPDATE `ibl_bug_reports` SET status = ?, class = IF(?, ?, class), pr_number = IF(?, '
                    . '?, pr_number), issue_number = IF(?, ?, issue_number), thread_id = IF(?, ?, '
                    . 'thread_id), approval_message_id = IF(?, ?, approval_message_id), blocked_until = '
                    . 'IF(?, ?, blocked_until), hunt_attempts = IF(?, ?, hunt_attempts), lease_owner = '
                    . 'IF(?, NULL, lease_owner), lease_expires = IF(?, NULL, lease_expires), updated_at = '
                    . 'NOW() WHERE id = ?',
            ],
            ['claimed', 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, 0, 7],
        ];
        yield 'BugReportClaim transition options and lease release' => [
            static function (MockDatabase $db): void {
                (new BugReportClaimRepository($db))->transition(7, 'blocked', ['class' => 'x', 'pr_number' => 12], true);
            },
            null,
            [
                'UPDATE `ibl_bug_reports` SET status = ?, class = IF(?, ?, class), pr_number = IF(?, '
                    . '?, pr_number), issue_number = IF(?, ?, issue_number), thread_id = IF(?, ?, '
                    . 'thread_id), approval_message_id = IF(?, ?, approval_message_id), blocked_until = '
                    . 'IF(?, ?, blocked_until), hunt_attempts = IF(?, ?, hunt_attempts), lease_owner = '
                    . 'IF(?, NULL, lease_owner), lease_expires = IF(?, NULL, lease_expires), updated_at = '
                    . 'NOW() WHERE id = ?',
            ],
            ['blocked', 1, 'x', 1, 12, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 1, 1, 7],
        ];
        yield 'SchemaValidator two assertions' => [
            static function (MockDatabase $db): void {
                (new SchemaValidator($db))->validate([new SchemaAssertion('ibl_plr', 'pid'), new SchemaAssertion('ibl_team_info', 'teamid')]);
            },
            null,
            [
                'SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '
                    . 'DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                'SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '
                    . 'DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ],
            ['ibl_team_info', 'teamid'],
        ];
        yield 'PeriodAverages no pid filter' => [
            static function (MockDatabase $db): void {
                PeriodAverages::render($db, self::periodTeam(), self::periodSeason(), '2026-01-01', '2026-01-15');
            },
            'gameMINavg',
            [
                self::PERIOD_AVERAGES_HEAD
                    . 'GROUP BY p.name, bs.pos, bs.pid ORDER BY p.name ASC',
            ],
            ['2026-01-01', '2026-01-15', 7, 7, 7],
        ];
        yield 'PeriodAverages pid filter' => [
            static function (MockDatabase $db): void {
                PeriodAverages::render($db, self::periodTeam(), self::periodSeason(), '2026-01-01', '2026-01-15', [], [10, 11]);
            },
            'gameMINavg',
            [
                self::PERIOD_AVERAGES_HEAD
                    . 'AND bs.pid IN '
                    . '(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) '
                    . 'GROUP BY p.name, bs.pos, bs.pid ORDER BY p.name ASC',
            ],
            ['2026-01-01', '2026-01-15', 7, 7, 7, 10, 11, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1],
        ];
    }

    /**
     * @param \Closure(MockDatabase): void $run
     * @param list<string> $expectedSql
     * @param list<mixed> $expectedBound
     */
    #[DataProvider('clearedSitePreparedSqlProvider')]
    public function testClearedSitePreparedSqlIsPinned(
        \Closure $run,
        ?string $filter,
        array $expectedSql,
        array $expectedBound
    ): void {
        $mockDb = new MockDatabase();

        $run($mockDb);

        self::assertSame($expectedSql, self::clearedSiteQueries($mockDb->getPreparedQueries(), $filter));
        self::assertSame($expectedBound, $mockDb->getLastBoundParams());
    }

    /**
     * Rows: [run, filter, expected executed SQL]. Executed text has bound values substituted
     * and backticks stripped, which is what these sites are pinned on.
     *
     * @return iterable<string, array{\Closure(MockDatabase): void, ?string, list<string>}>
     */
    public static function clearedSiteExecutedSqlProvider(): iterable
    {
        yield 'SplitStats div_atlantic' => [
            static function (MockDatabase $db): void {
                (new SplitStatsRepository($db))->getSplitStats(5, 2026, 'div_atlantic');
            },
            null,
            [
                self::SPLIT_STATS_EXECUTED_HEAD
                    . 'opp_s.division = \'Atlantic\''
                    . self::SPLIT_STATS_EXECUTED_TAIL,
            ],
        ];
        yield 'SplitStats conf_eastern' => [
            static function (MockDatabase $db): void {
                (new SplitStatsRepository($db))->getSplitStats(5, 2026, 'conf_eastern');
            },
            null,
            [
                self::SPLIT_STATS_EXECUTED_HEAD
                    . 'opp_s.conference = \'Eastern\''
                    . self::SPLIT_STATS_EXECUTED_TAIL,
            ],
        ];
        yield 'Voting fetchAllStarTotals' => [
            static function (MockDatabase $db): void {
                (new VotingRepository($db))->fetchAllStarTotals(['east_f1', 'east_f2']);
            },
            null,
            [
                'SELECT COUNT(name) AS votes, name FROM (SELECT east_f1 AS name FROM ibl_votes_ASG '
                    . 'WHERE 1 = 1 UNION ALL SELECT east_f2 AS name FROM ibl_votes_ASG WHERE 1 = 1 UNION '
                    . 'ALL SELECT east_f3 AS name FROM ibl_votes_ASG WHERE 0 = 1 UNION ALL SELECT east_f4 '
                    . 'AS name FROM ibl_votes_ASG WHERE 0 = 1 UNION ALL SELECT east_b1 AS name FROM '
                    . 'ibl_votes_ASG WHERE 0 = 1 UNION ALL SELECT east_b2 AS name FROM ibl_votes_ASG WHERE '
                    . '0 = 1 UNION ALL SELECT east_b3 AS name FROM ibl_votes_ASG WHERE 0 = 1 UNION ALL '
                    . 'SELECT east_b4 AS name FROM ibl_votes_ASG WHERE 0 = 1 UNION ALL SELECT west_f1 AS '
                    . 'name FROM ibl_votes_ASG WHERE 0 = 1 UNION ALL SELECT west_f2 AS name FROM '
                    . 'ibl_votes_ASG WHERE 0 = 1 UNION ALL SELECT west_f3 AS name FROM ibl_votes_ASG WHERE '
                    . '0 = 1 UNION ALL SELECT west_f4 AS name FROM ibl_votes_ASG WHERE 0 = 1 UNION ALL '
                    . 'SELECT west_b1 AS name FROM ibl_votes_ASG WHERE 0 = 1 UNION ALL SELECT west_b2 AS '
                    . 'name FROM ibl_votes_ASG WHERE 0 = 1 UNION ALL SELECT west_b3 AS name FROM '
                    . 'ibl_votes_ASG WHERE 0 = 1 UNION ALL SELECT west_b4 AS name FROM ibl_votes_ASG WHERE '
                    . '0 = 1) AS ballot GROUP BY name HAVING COUNT(name) > 0 ORDER BY votes DESC, name ASC',
            ],
        ];
        yield 'Voting fetchEndOfYearTotals' => [
            static function (MockDatabase $db): void {
                (new VotingRepository($db))->fetchEndOfYearTotals(['mvp_1' => 5, 'mvp_2' => 3]);
            },
            null,
            [
                'SELECT SUM(score) AS votes, name FROM (SELECT mvp_1 AS name, CAST(5 AS SIGNED) AS '
                    . 'score FROM ibl_votes_EOY WHERE 1 = 1 UNION ALL SELECT mvp_2 AS name, CAST(3 AS '
                    . 'SIGNED) AS score FROM ibl_votes_EOY WHERE 1 = 1 UNION ALL SELECT mvp_3 AS name, '
                    . 'CAST(0 AS SIGNED) AS score FROM ibl_votes_EOY WHERE 0 = 1 UNION ALL SELECT six_1 AS '
                    . 'name, CAST(0 AS SIGNED) AS score FROM ibl_votes_EOY WHERE 0 = 1 UNION ALL SELECT '
                    . 'six_2 AS name, CAST(0 AS SIGNED) AS score FROM ibl_votes_EOY WHERE 0 = 1 UNION ALL '
                    . 'SELECT six_3 AS name, CAST(0 AS SIGNED) AS score FROM ibl_votes_EOY WHERE 0 = 1 '
                    . 'UNION ALL SELECT roy_1 AS name, CAST(0 AS SIGNED) AS score FROM ibl_votes_EOY WHERE '
                    . '0 = 1 UNION ALL SELECT roy_2 AS name, CAST(0 AS SIGNED) AS score FROM ibl_votes_EOY '
                    . 'WHERE 0 = 1 UNION ALL SELECT roy_3 AS name, CAST(0 AS SIGNED) AS score FROM '
                    . 'ibl_votes_EOY WHERE 0 = 1 UNION ALL SELECT gm_1 AS name, CAST(0 AS SIGNED) AS score '
                    . 'FROM ibl_votes_EOY WHERE 0 = 1 UNION ALL SELECT gm_2 AS name, CAST(0 AS SIGNED) AS '
                    . 'score FROM ibl_votes_EOY WHERE 0 = 1 UNION ALL SELECT gm_3 AS name, CAST(0 AS '
                    . 'SIGNED) AS score FROM ibl_votes_EOY WHERE 0 = 1) AS ballot GROUP BY name HAVING '
                    . 'SUM(score) > 0 ORDER BY votes DESC, name ASC',
            ],
        ];
        yield 'SeasonLeaderboards no filters' => [
            static function (MockDatabase $db): void {
                (new SeasonLeaderboardsRepository($db))->getSeasonLeaders([], 0);
            },
            null,
            [
                self::SEASON_LEADERS_HEAD
                    . 'ORDER BY ((2*fgm+ftm+tgm)/games) DESC, h.pid ASC',
            ],
        ];
        yield 'SeasonLeaderboards year and team' => [
            static function (MockDatabase $db): void {
                (new SeasonLeaderboardsRepository($db))->getSeasonLeaders(['year' => '2026', 'team' => 5], 0);
            },
            null,
            [
                self::SEASON_LEADERS_HEAD
                    . 'AND h.year = \'2026\' AND h.teamid = 5 ORDER BY ((2*fgm+ftm+tgm)/games) DESC, h.pid '
                    . 'ASC',
            ],
        ];
        yield 'SeasonLeaderboards limit 25' => [
            static function (MockDatabase $db): void {
                (new SeasonLeaderboardsRepository($db))->getSeasonLeaders([], 25);
            },
            null,
            [
                self::SEASON_LEADERS_HEAD
                    . 'ORDER BY ((2*fgm+ftm+tgm)/games) DESC, h.pid ASC LIMIT 25',
            ],
        ];
        yield 'League getAllStarCandidatesResult ECF' => [
            static function (MockDatabase $db): void {
                (new League($db))->getAllStarCandidatesResult('ECF');
            },
            null,
            [
                'SELECT p.*, t.team_name AS teamname, t.team_city, t.color1, t.color2 FROM ibl_plr p '
                    . 'JOIN ibl_team_info t ON p.teamid = t.teamid WHERE p.pos IN (\'C\', \'SF\', \'PF\') AND '
                    . 'p.teamid IN (1,2,3,4,5,7,8,9,10,11,12,22,25,27) AND p.retired = 0 AND p.stats_gm > '
                    . '\'14\' ORDER BY p.name, p.pid ASC',
            ],
        ];
    }

    /**
     * @param \Closure(MockDatabase): void $run
     * @param list<string> $expectedSql
     */
    #[DataProvider('clearedSiteExecutedSqlProvider')]
    public function testClearedSiteExecutedSqlIsPinned(\Closure $run, ?string $filter, array $expectedSql): void
    {
        $mockDb = new MockDatabase();

        $run($mockDb);

        self::assertSame($expectedSql, self::clearedSiteQueries($mockDb->getExecutedQueries(), $filter));
    }

    /**
     * @return iterable<string, array{\Closure(VotingRepository): mixed}>
     */
    public static function unknownVotingColumnProvider(): iterable
    {
        yield 'all-star totals' => [
            static fn (VotingRepository $repository): mixed => $repository->fetchAllStarTotals(['east_f1', 'bad_col; DROP TABLE ibl_votes_ASG']),
        ];
        yield 'end-of-year totals' => [
            static fn (VotingRepository $repository): mixed => $repository->fetchEndOfYearTotals(['mvp_1' => 5, 'bad_col; DROP TABLE ibl_votes_EOY' => 3]),
        ];
    }

    /**
     * @param \Closure(VotingRepository): mixed $call
     */
    #[DataProvider('unknownVotingColumnProvider')]
    public function testVotingRejectsUnknownColumn(\Closure $call): void
    {
        $mockDb = new MockDatabase();
        $repository = new VotingRepository($mockDb);

        try {
            $call($repository);
            self::fail('An unknown ballot column must be rejected');
        } catch (\InvalidArgumentException) {
            self::assertSame([], $mockDb->getExecutedQueries());
            self::assertSame([], $mockDb->getPreparedQueries());
        }
    }

    public function testTopicsHostilePrefixFallsBackToNuke(): void
    {
        $topicRows = [['topicid' => 1, 'topicname' => 'topic', 'topicimage' => 'topic.gif', 'topictext' => 'Topic Text', 'stories' => 1, 'total_reads' => 0]];

        $defaultDb = new MockDatabase();
        $defaultDb->onQuery('nuke_topics t', $topicRows);
        (new TopicsRepository($defaultDb))->getTopicsWithArticles();

        $hostileDb = new MockDatabase();
        $hostileDb->onQuery('nuke_topics t', $topicRows);
        (new TopicsRepository($hostileDb, 'bad prefix; DROP TABLE nuke_topics'))->getTopicsWithArticles();

        $prepared = array_map(self::normalizeSql(...), $hostileDb->getPreparedQueries());
        self::assertCount(2, $prepared);
        self::assertSame(array_map(self::normalizeSql(...), $defaultDb->getPreparedQueries()), $prepared);
        foreach ($prepared as $sql) {
            self::assertStringNotContainsString('DROP', $sql);
            self::assertStringNotContainsString('bad prefix', $sql);
        }
    }

    public function testSplitStatsUnknownKeyYieldsEmptyCondition(): void
    {
        $unknownDb = new MockDatabase();
        (new SplitStatsRepository($unknownDb))->getSplitStats(5, 2026, 'unknown');

        $hostileDb = new MockDatabase();
        (new SplitStatsRepository($hostileDb))->getSplitStats(5, 2026, 'home OR 1=1');

        self::assertSame(
            array_map(self::normalizeSql(...), $unknownDb->getPreparedQueries()),
            array_map(self::normalizeSql(...), $hostileDb->getPreparedQueries())
        );
        self::assertSame([2026, 5, 5, 5], $hostileDb->getLastBoundParams());
        $executed = $hostileDb->getExecutedQueries();
        self::assertCount(1, $executed);
        self::assertStringNotContainsString('OR 1=1', $executed[0]);
    }

    /**
     * @param list<string> $prepared
     */
    private static function assertEveryQueryContainsStoriesJoin(array $prepared): void
    {
        self::assertGreaterThan(0, count($prepared));
        foreach ($prepared as $sql) {
            self::assertStringContainsString(
                'FROM nuke_stories s LEFT JOIN nuke_topics t ON s.topic = t.topicid',
                self::normalizeSql($sql)
            );
        }
    }

    // ---- Search ----

    public function testSearchStoriesPreparedSqlUsesNukeTables(): void
    {
        $mockDb = new MockDatabase();
        $repository = new SearchRepository($mockDb);

        $repository->searchStories('transaction report');

        self::assertEveryQueryContainsStoriesJoin($mockDb->getPreparedQueries());
    }

    public function testSearchStoriesByPresetPreparedSqlUsesNukeTables(): void
    {
        $mockDb = new MockDatabase();
        $repository = new SearchRepository($mockDb);

        $repository->searchStoriesByPreset(SearchRepositoryInterface::PRESET_TRANSACTIONS);

        self::assertEveryQueryContainsStoriesJoin($mockDb->getPreparedQueries());
    }

    public function testGetTopicsPreparedSqlIsPinned(): void
    {
        $mockDb = new MockDatabase();
        $repository = new SearchRepository($mockDb);

        $repository->getTopics();

        $prepared = $mockDb->getPreparedQueries();
        self::assertCount(1, $prepared);
        self::assertSame(self::SEARCH_TOPICS_SQL, self::normalizeSql($prepared[0]));
    }

    public function testGetCategoriesPreparedSqlIsPinned(): void
    {
        $mockDb = new MockDatabase();
        $repository = new SearchRepository($mockDb);

        $repository->getCategories();

        $prepared = $mockDb->getPreparedQueries();
        self::assertCount(1, $prepared);
        self::assertSame(
            'SELECT catid, title FROM nuke_stories_cat ORDER BY title, catid ASC',
            self::normalizeSql($prepared[0])
        );
    }

    public function testGetTopicInfoPreparedSqlIsPinned(): void
    {
        $mockDb = new MockDatabase();
        $repository = new SearchRepository($mockDb);

        $repository->getTopicInfo(7);

        $prepared = $mockDb->getPreparedQueries();
        self::assertCount(1, $prepared);
        self::assertSame(
            'SELECT topicimage, topictext FROM nuke_topics WHERE topicid = ?',
            self::normalizeSql($prepared[0])
        );
        self::assertSame([7], $mockDb->getLastBoundParams());
    }

    public function testExplicitNukePrefixProducesSameSqlAsDefault(): void
    {
        $mockDb = new MockDatabase();
        $repository = new SearchRepository($mockDb, 'nuke');

        $repository->getTopics();

        $prepared = $mockDb->getPreparedQueries();
        self::assertCount(1, $prepared);
        self::assertSame(self::SEARCH_TOPICS_SQL, self::normalizeSql($prepared[0]));
    }

    public function testMalformedPrefixFallsBackToNukeTables(): void
    {
        $mockDb = new MockDatabase();
        $repository = new SearchRepository($mockDb, 'bad prefix; DROP TABLE nuke_topics');

        $repository->getTopics();

        $prepared = $mockDb->getPreparedQueries();
        self::assertCount(1, $prepared);
        $normalized = self::normalizeSql($prepared[0]);
        self::assertSame(self::SEARCH_TOPICS_SQL, $normalized);
        self::assertStringNotContainsString('DROP', $normalized);
    }

    // ---- Standings ----

    public function testGetTeamPythagoreanStatsPreparedSqlIsPinned(): void
    {
        $mockDb = new MockDatabase();
        $repository = new StandingsRepository($mockDb);

        $repository->getTeamPythagoreanStats(1, 2025);

        $prepared = $mockDb->getPreparedQueries();
        self::assertCount(1, $prepared);

        $expected = 'SELECT tos.fgm AS off_fgm, tos.ftm AS off_ftm, tos.tgm AS off_tgm, '
            . 'tds.fgm AS def_fgm, tds.ftm AS def_ftm, tds.tgm AS def_tgm '
            . 'FROM ('
            . self::STANDINGS_OFFENSE_BODY . 'bst.season_year = ? AND fs.franchise_id = ?' . self::STANDINGS_OFFENSE_GROUP
            . ') tos JOIN ('
            . self::STANDINGS_DEFENSE_BODY . 'my.season_year = ? AND fs.franchise_id = ?' . self::STANDINGS_DEFENSE_GROUP
            . ') tds' . self::STANDINGS_JOIN_ON;

        self::assertSame($expected, self::normalizeSql($prepared[0]));
        self::assertSame([2025, 1, 2025, 1], $mockDb->getLastBoundParams());
    }

    public function testGetAllPythagoreanStatsPreparedSqlIsPinned(): void
    {
        $mockDb = new MockDatabase();
        $repository = new StandingsRepository($mockDb);

        $repository->getAllPythagoreanStats(2025);

        $prepared = $mockDb->getPreparedQueries();
        self::assertCount(1, $prepared);

        $expected = 'SELECT tos.teamid, '
            . 'tos.fgm AS off_fgm, tos.ftm AS off_ftm, tos.tgm AS off_tgm, '
            . 'tds.fgm AS def_fgm, tds.ftm AS def_ftm, tds.tgm AS def_tgm '
            . 'FROM ('
            . self::STANDINGS_OFFENSE_BODY . 'bst.season_year = ?' . self::STANDINGS_OFFENSE_GROUP
            . ') tos JOIN ('
            . self::STANDINGS_DEFENSE_BODY . 'my.season_year = ?' . self::STANDINGS_DEFENSE_GROUP
            . ') tds' . self::STANDINGS_JOIN_ON;

        self::assertSame($expected, self::normalizeSql($prepared[0]));
        self::assertSame([2025, 2025], $mockDb->getLastBoundParams());
    }

    // ---- TeamOffDefStats ----

    /**
     * @param string $offenseFilter text after "WHERE " in the offense subquery
     * @param string $defenseFilter text after "WHERE " in the defense subquery
     */
    private static function allTeamStatsGolden(string $offenseFilter, string $defenseFilter): string
    {
        return self::TODS_ALL_TEAMS_HEAD
            . self::TODS_OFFENSE_BODY . $offenseFilter . self::TODS_OFFENSE_GROUP
            . ') tos ON ti.teamid = tos.teamid LEFT JOIN ('
            . self::TODS_DEFENSE_BODY . $defenseFilter . self::TODS_DEFENSE_GROUP
            . ') tds ON ti.teamid = tds.teamid'
            . self::TODS_ALL_TEAMS_TAIL;
    }

    public function testGetAllTeamStatsExecutedSqlIsPinnedForRegularSeason(): void
    {
        $mockDb = new MockDatabase();
        $repository = new TeamOffDefStatsRepository($mockDb);

        $repository->getAllTeamStats(2025);

        $executed = $mockDb->getExecutedQueries();
        self::assertCount(1, $executed);
        self::assertSame(
            self::allTeamStatsGolden(
                'bst.game_type = 1 AND bst.season_year = 2025',
                'my.game_type = 1 AND my.season_year = 2025'
            ),
            self::normalizeSql($executed[0])
        );
    }

    public function testGetAllTeamStatsExecutedSqlIsPinnedForPlayoffPhase(): void
    {
        $mockDb = new MockDatabase();
        $repository = new TeamOffDefStatsRepository($mockDb);

        $repository->getAllTeamStats(2025, [1, 2]);

        $executed = $mockDb->getExecutedQueries();
        self::assertCount(1, $executed);
        self::assertSame(
            self::allTeamStatsGolden(
                'bst.game_type IN (1, 2) AND bst.season_year = 2025',
                'my.game_type IN (1, 2) AND my.season_year = 2025'
            ),
            self::normalizeSql($executed[0])
        );
    }

    public function testGetAllTeamStatsExecutedSqlIsPinnedForNoGameTypeFilter(): void
    {
        $mockDb = new MockDatabase();
        $repository = new TeamOffDefStatsRepository($mockDb);

        $repository->getAllTeamStats(2025, []);

        $executed = $mockDb->getExecutedQueries();
        self::assertCount(1, $executed);
        $normalized = self::normalizeSql($executed[0]);
        self::assertSame(
            self::allTeamStatsGolden('bst.season_year = 2025', 'my.season_year = 2025'),
            $normalized
        );
        self::assertStringNotContainsString('game_type', $normalized);
    }

    public function testGetTeamOffenseStatsExecutedSqlIsPinned(): void
    {
        $mockDb = new MockDatabase();
        $repository = new TeamOffDefStatsRepository($mockDb);

        $repository->getTeamOffenseStats('Metros', 2025);

        $executed = $mockDb->getExecutedQueries();
        self::assertCount(1, $executed);
        self::assertSame(
            'SELECT * FROM ('
            . self::TODS_OFFENSE_BODY
            . "bst.game_type = 1 AND bst.season_year = 2025 AND fs.team_name = 'Metros'"
            . self::TODS_OFFENSE_GROUP
            . ') t LIMIT 1',
            self::normalizeSql($executed[0])
        );
    }

    public function testGetTeamDefenseStatsExecutedSqlIsPinned(): void
    {
        $mockDb = new MockDatabase();
        $repository = new TeamOffDefStatsRepository($mockDb);

        $repository->getTeamDefenseStats('Metros', 2025);

        $executed = $mockDb->getExecutedQueries();
        self::assertCount(1, $executed);
        self::assertSame(
            'SELECT * FROM ('
            . self::TODS_DEFENSE_BODY
            . "my.game_type = 1 AND my.season_year = 2025 AND fs.team_name = 'Metros'"
            . self::TODS_DEFENSE_GROUP
            . ') t LIMIT 1',
            self::normalizeSql($executed[0])
        );
    }

    private static function bothStatsGolden(string $offenseFilter, string $defenseFilter, string $joinOn): string
    {
        return self::TODS_BOTH_HEAD
            . self::TODS_OFFENSE_BODY . $offenseFilter . self::TODS_OFFENSE_GROUP
            . ') tos JOIN ('
            . self::TODS_DEFENSE_BODY . $defenseFilter . self::TODS_DEFENSE_GROUP
            . ') tds ON ' . $joinOn . ' LIMIT 1';
    }

    public function testGetTeamBothStatsExecutedSqlIsPinnedForRegularSeasonOnly(): void
    {
        $mockDb = new MockDatabase();
        $repository = new TeamOffDefStatsRepository($mockDb);

        $repository->getTeamBothStats('Metros', 2025);

        // One fetchOne call: the offense and defense subqueries share a single query.
        $executed = $mockDb->getExecutedQueries();
        self::assertCount(1, $executed);
        self::assertSame(
            self::bothStatsGolden(
                "bst.game_type = 1 AND bst.season_year = 2025 AND fs.team_name = 'Metros'",
                "my.game_type = 1 AND my.season_year = 2025 AND fs.team_name = 'Metros'",
                'tos.teamid = tds.teamid AND tos.season_year = tds.season_year'
            ),
            self::normalizeSql($executed[0])
        );
    }

    public function testGetTeamBothStatsExecutedSqlIsPinnedForAllGameTypes(): void
    {
        $mockDb = new MockDatabase();
        $repository = new TeamOffDefStatsRepository($mockDb);

        $repository->getTeamBothStats('Metros', 2025, false);

        $executed = $mockDb->getExecutedQueries();
        self::assertCount(1, $executed);
        $normalized = self::normalizeSql($executed[0]);
        self::assertSame(
            self::bothStatsGolden(
                "bst.season_year = 2025 AND fs.team_name = 'Metros'",
                "my.season_year = 2025 AND fs.team_name = 'Metros'",
                'tos.teamid = tds.teamid AND tos.season_year = tds.season_year'
            ),
            $normalized
        );
        self::assertStringNotContainsString('game_type', $normalized);
    }

    public function testGetTeamBothStatsForDateRangeExecutedSqlIsPinned(): void
    {
        $mockDb = new MockDatabase();
        $repository = new TeamOffDefStatsRepository($mockDb);

        $repository->getTeamBothStatsForDateRange('Metros', '2025-01-01', '2025-01-31');

        $executed = $mockDb->getExecutedQueries();
        self::assertCount(1, $executed);
        $normalized = self::normalizeSql($executed[0]);
        self::assertSame(
            self::bothStatsGolden(
                "bst.game_date BETWEEN '2025-01-01' AND '2025-01-31' AND fs.team_name = 'Metros'",
                "my.game_date BETWEEN '2025-01-01' AND '2025-01-31' AND fs.team_name = 'Metros'",
                'tos.teamid = tds.teamid'
            ),
            $normalized
        );
        self::assertStringNotContainsString('game_type', $normalized);
    }

    // ---- Phase 5: dynamic WHERE sites rebuilt as conditional literal appends ----

    private const HOSTILE_FILTER_VALUE = "' OR 1=1 --";

    /**
     * @return list<string>
     */
    private static function normalizedPrepared(MockDatabase $mockDb): array
    {
        return array_map(self::normalizeSql(...), $mockDb->getPreparedQueries());
    }

    private static function paginator(string $defaultSort, string ...$allowed): \Api\Pagination\Paginator
    {
        return new \Api\Pagination\Paginator([], $defaultSort, array_values($allowed));
    }

    /**
     * @param array<string, int|string|null> $overrides
     * @return array<string, int|string|null>
     */
    private static function playerSearchParams(array $overrides = []): array
    {
        $keys = [
            'pos', 'age', 'search_name', 'college', 'exp', 'exp_max', 'bird', 'bird_max', 'active',
            'r_fga', 'r_fgp', 'r_fta', 'r_ftp', 'r_3ga', 'r_3gp', 'r_orb', 'r_drb', 'r_ast',
            'r_stl', 'r_blk', 'r_to', 'r_foul', 'Clutch', 'Consistency', 'talent', 'skill',
            'intangibles', 'oo', 'do', 'po', 'to', 'od', 'dd', 'pd', 'td',
        ];

        return array_merge(array_fill_keys($keys, null), $overrides);
    }

    public function testGetSeasonLeadersOmitsLimitWhenZero(): void
    {
        $mockDb = new MockDatabase();
        $repository = new \SeasonLeaderboards\SeasonLeaderboardsRepository($mockDb);

        $repository->getSeasonLeaders(['year' => '2025'], 0);

        $prepared = self::normalizedPrepared($mockDb);
        self::assertCount(1, $prepared);
        self::assertStringNotContainsString('LIMIT', $prepared[0]);
        self::assertSame(['2025'], $mockDb->getLastBoundParams());
    }

    public function testGetSeasonLeadersBindsLimitAsLastInteger(): void
    {
        $mockDb = new MockDatabase();
        $repository = new \SeasonLeaderboards\SeasonLeaderboardsRepository($mockDb);

        $repository->getSeasonLeaders(['year' => '2025', 'team' => 7], 10);

        $prepared = self::normalizedPrepared($mockDb);
        self::assertCount(1, $prepared);
        self::assertStringEndsWith('LIMIT ?', $prepared[0]);
        self::assertSame(['2025', 7, 10], $mockDb->getLastBoundParams());
    }

    public function testApiLeadersMinGamesZeroOmitsClause(): void
    {
        $mockDb = new MockDatabase();
        $repository = new \Api\Repository\ApiLeadersRepository($mockDb);
        $paginator = self::paginator('pts', 'pts');

        $repository->getLeaders($paginator, ['season' => '2025', 'min_games' => '0']);

        $prepared = self::normalizedPrepared($mockDb);
        self::assertCount(1, $prepared);
        self::assertStringNotContainsString('h.games >= ?', $prepared[0]);
        self::assertSame([2025, $paginator->getLimit(), $paginator->getOffset()], $mockDb->getLastBoundParams());
    }

    public function testTransactionHistoryYearAndMonthBindRangeInOrder(): void
    {
        $mockDb = new MockDatabase();
        $repository = new \TransactionHistory\TransactionHistoryRepository($mockDb);

        $repository->getTransactions(3, 2025, 12);

        $prepared = self::normalizedPrepared($mockDb);
        self::assertCount(1, $prepared);
        self::assertStringContainsString('AND catid = ? AND time >= ? AND time < ? ORDER BY', $prepared[0]);
        self::assertSame(
            [3, '2025-12-01 00:00:00', '2026-01-01 00:00:00'],
            $mockDb->getLastBoundParams()
        );
    }

    public function testAwardHistoryNoFiltersUsesAnchorOnly(): void
    {
        $mockDb = new MockDatabase();
        $repository = new \AwardHistory\AwardHistoryRepository($mockDb);

        $repository->searchAwards(['name' => null, 'award' => null, 'year' => null, 'sortby' => 1]);

        $prepared = self::normalizedPrepared($mockDb);
        self::assertCount(1, $prepared);
        self::assertStringContainsString('WHERE 1=1 ORDER BY', $prepared[0]);
    }

    public function testAwardHistoryYearFilterAppendsAfterAnchor(): void
    {
        $mockDb = new MockDatabase();
        $repository = new \AwardHistory\AwardHistoryRepository($mockDb);

        $repository->searchAwards(['name' => null, 'award' => null, 'year' => 2025, 'sortby' => 1]);

        $prepared = self::normalizedPrepared($mockDb);
        self::assertCount(1, $prepared);
        self::assertStringContainsString('WHERE 1=1 AND a.year = ?', $prepared[0]);
        self::assertSame([2025], $mockDb->getLastBoundParams());
    }

    public function testGetGamesWithNoFiltersBindsZeroFlags(): void
    {
        $mockDb = new MockDatabase();
        $repository = new \Api\Repository\ApiGameRepository($mockDb);

        $repository->getGames(self::paginator('game_date', 'game_date'), []);

        $params = $mockDb->getLastBoundParams();
        self::assertCount(15, $params);
        foreach ([0, 2, 4, 7, 9, 11] as $flagSlot) {
            self::assertSame(0, $params[$flagSlot], 'flag slot ' . $flagSlot);
        }
    }

    public function testGetGamesTeamFilterBindsUuidTwice(): void
    {
        $mockDb = new MockDatabase();
        $repository = new \Api\Repository\ApiGameRepository($mockDb);

        $repository->getGames(self::paginator('game_date', 'game_date'), ['team' => 'team-uuid-1']);

        $params = $mockDb->getLastBoundParams();
        self::assertCount(15, $params);
        self::assertSame(1, $params[4]);
        self::assertSame('team-uuid-1', $params[5]);
        self::assertSame('team-uuid-1', $params[6]);
    }

    public function testPlayerSearchBindsOneFlagAndValuePerPredicate(): void
    {
        $mockDb = new MockDatabase();
        $repository = new \PlayerSearch\PlayerSearchRepository($mockDb);

        $repository->searchPlayers(self::playerSearchParams());

        self::assertCount(69, $mockDb->getLastBoundParams());
    }

    public function testPlayerSearchActiveOnlySetsRetiredFlag(): void
    {
        $mockDb = new MockDatabase();
        $repository = new \PlayerSearch\PlayerSearchRepository($mockDb);

        $repository->searchPlayers(self::playerSearchParams(['active' => 0]));
        self::assertSame(1, $mockDb->getLastBoundParams()[0]);

        $repository->searchPlayers(self::playerSearchParams(['active' => 1]));
        self::assertSame(0, $mockDb->getLastBoundParams()[0]);

        $repository->searchPlayers(self::playerSearchParams());
        self::assertSame(0, $mockDb->getLastBoundParams()[0]);
    }

    /**
     * Each row runs one repository with a hostile (or, for the integer-only
     * TransactionHistory, a distinctive) filter value. Fourth column: whether the
     * value must reach the bind list.
     *
     * @return array<string, array{\Closure(MockDatabase): void, string, bool}>
     */
    public static function hostileFilterProvider(): array
    {
        $hostile = self::HOSTILE_FILTER_VALUE;

        return [
            'SeasonLeaders year' => [
                static function (MockDatabase $db) use ($hostile): void {
                    (new \SeasonLeaderboards\SeasonLeaderboardsRepository($db))
                        ->getSeasonLeaders(['year' => $hostile, 'team' => 7, 'sortby' => $hostile], 10);
                },
                $hostile,
                true,
            ],
            'ApiLeaders season, min_games, category' => [
                static function (MockDatabase $db) use ($hostile): void {
                    (new \Api\Repository\ApiLeadersRepository($db))->getLeaders(
                        self::paginator('pts', 'pts'),
                        ['season' => $hostile, 'min_games' => $hostile, 'category' => $hostile]
                    );
                },
                $hostile,
                false,
            ],
            'TransactionHistory integer filters' => [
                static function (MockDatabase $db): void {
                    (new \TransactionHistory\TransactionHistoryRepository($db))->getTransactions(7777, 2025, 3);
                },
                '7777',
                true,
            ],
            'AwardHistory name and award' => [
                static function (MockDatabase $db) use ($hostile): void {
                    (new \AwardHistory\AwardHistoryRepository($db))
                        ->searchAwards(['name' => $hostile, 'award' => $hostile, 'year' => 2025, 'sortby' => 1]);
                },
                $hostile,
                true,
            ],
            'ApiGame status, team and dates' => [
                static function (MockDatabase $db) use ($hostile): void {
                    (new \Api\Repository\ApiGameRepository($db))->getGames(
                        self::paginator('game_date', 'game_date'),
                        [
                            'status' => $hostile,
                            'team' => $hostile,
                            'date' => $hostile,
                            'date_start' => $hostile,
                            'date_end' => $hostile,
                        ]
                    );
                },
                $hostile,
                true,
            ],
            'PlayerSearch name, college and pos' => [
                static function (MockDatabase $db) use ($hostile): void {
                    (new \PlayerSearch\PlayerSearchRepository($db))->searchPlayers(
                        self::playerSearchParams(['search_name' => $hostile, 'college' => $hostile, 'pos' => $hostile])
                    );
                },
                $hostile,
                true,
            ],
        ];
    }

    /**
     * @param \Closure(MockDatabase): void $run
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('hostileFilterProvider')]
    public function testHostileFilterValuesNeverReachSqlText(\Closure $run, string $needle, bool $expectBound): void
    {
        $mockDb = new MockDatabase();

        $run($mockDb);

        $prepared = $mockDb->getPreparedQueries();
        self::assertNotSame([], $prepared);
        foreach ($prepared as $sql) {
            self::assertStringNotContainsString($needle, $sql);
        }

        $reachedBinds = false;
        foreach ($mockDb->getLastBoundParams() as $bound) {
            if (is_scalar($bound) && str_contains((string) $bound, $needle)) {
                $reachedBinds = true;
            }
        }
        self::assertSame($expectBound, $reachedBinds);
    }

    public function testBugReportTransitionBindsFlagPerOptionalColumn(): void
    {
        $mockDb = new MockDatabase();
        $repository = new \BugPipeline\BugReportClaimRepository($mockDb);

        $repository->transition(7, 'queued', ['pr_number' => 42]);

        $params = $mockDb->getLastBoundParams();
        $columns = array_keys(\BugPipeline\BugReportClaimRepository::OPTIONAL_TRANSITION_COLUMNS);
        self::assertCount(1 + 2 * count($columns) + 3, $params);
        self::assertCount(strlen(\BugPipeline\BugReportClaimRepository::TRANSITION_TYPES), $params);
        self::assertSame(count($params), substr_count($mockDb->getPreparedQueries()[0], '?'));
        self::assertSame('queued', $params[0]);
        foreach ($columns as $i => $col) {
            $flagSlot = 1 + 2 * $i;
            self::assertSame($col === 'pr_number' ? 1 : 0, $params[$flagSlot], $col . ' flag');
            self::assertSame($col === 'pr_number' ? 42 : null, $params[$flagSlot + 1], $col . ' value');
        }
        self::assertSame([0, 0, 7], array_slice($params, -3));
    }

    public function testBugReportTransitionReleaseLeaseBindsBothFlags(): void
    {
        $mockDb = new MockDatabase();
        $repository = new \BugPipeline\BugReportClaimRepository($mockDb);

        $repository->transition(9, 'needs_human', [], true);
        self::assertSame([1, 1, 9], array_slice($mockDb->getLastBoundParams(), -3));
        self::assertStringContainsString('lease_owner = IF(?, NULL, lease_owner), lease_expires = IF(?, NULL, lease_expires)', $mockDb->getPreparedQueries()[0]);

        $repository->transition(9, 'needs_human');
        self::assertSame([0, 0, 9], array_slice($mockDb->getLastBoundParams(), -3));
    }
}
