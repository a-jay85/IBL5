<?php

declare(strict_types=1);

namespace Tests\League;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Characterization test pinning the SQL each player-to-team JOIN site emits
 * (backlog 13.12). Each site's executed query is whitespace-normalized and
 * compared to a full golden string, so a change to join type (`JOIN` vs
 * `LEFT JOIN`), column list, WHERE, ORDER BY, or a lost space that fuses two
 * tokens fails the case.
 *
 * The existing DB-backed repository tests cannot tell `JOIN` from `LEFT JOIN`
 * (every seeded player's teamid has a team row), so this is the only check that
 * discriminates join type.
 *
 * One permitted difference: TrainingCampRatingsDiff writes the ON operands
 * reversed (`t.teamid = p.teamid`). The normalizer canonicalizes that to the
 * shared fragment's `p.teamid = t.teamid`; equality is symmetric.
 *
 * Mutations each golden catches:
 * - A dropped newline before a fragment call fuses `p` and `LEFT` into pLEFT
 *   (trait prefix, ApiInjuries, and the TrainingCampRatingsDiff nowdoc split).
 * - Calling playerTeamInnerJoin() in ComparePlayersRepository::getPlayerByName()
 *   drops `LEFT` from the join.
 * - A dropped leading newline on a reopened string fuses `t.teamid` and `WHERE`.
 *
 * Observed join-query counts: every case captures exactly one query (the
 * SeasonHighs batch case is one UNION ALL query holding two player branches).
 */
class PlayerTeamJoinSitesSqlCharacterizationTest extends TestCase
{
    /**
     * @param \Closure(MockDatabase): void $invoke
     * @param list<string> $expectedSql
     */
    #[DataProvider('joinSiteProvider')]
    public function testJoinSiteEmitsPinnedSql(string $label, \Closure $invoke, array $expectedSql): void
    {
        $db = new MockDatabase();
        $invoke($db);

        $actual = [];
        foreach ($db->getExecutedQueries() as $query) {
            $normalized = self::normalize($query);
            if (preg_match('/ibl_team_info t ON (p\.teamid = t\.teamid|t\.teamid = p\.teamid)/', $normalized) === 1) {
                $actual[] = $normalized;
            }
        }

        $this->assertNotSame([], $actual, "$label captured no player-team join query");
        $this->assertSame($expectedSql, $actual, $label);
    }

    /**
     * @return array<string, array{0: string, 1: \Closure(MockDatabase): void, 2: list<string>}>
     */
    public static function joinSiteProvider(): array
    {
        return [
            'trait-playerWithTeamSelect' => [
                'trait-playerWithTeamSelect',
                static function (MockDatabase $db): void {
                    $probe = new class {
                        use \Repositories\PlayerTeamJoinQuery;

                        public function sql(): string
                        {
                            return $this->playerWithTeamSelect();
                        }
                    };
                    // Trait prefix is not a query: route it through the mock so the filter sees it.
                    $db->sql_query($probe->sql());
                },
                [
                    'SELECT p.*, t.team_name AS teamname, t.color1, t.color2 FROM ibl_plr p LEFT JOIN ibl_team_info t ON p.teamid = t.teamid',
                ],
            ],
            'LeagueStarters-getAllStartersWithTeamData' => [
                'LeagueStarters-getAllStartersWithTeamData',
                static function (MockDatabase $db): void {
                    (new \LeagueStarters\LeagueStartersRepository($db))->getAllStartersWithTeamData();
                },
                [
                    'SELECT p.*, t.team_name AS teamname, t.color1, t.color2 FROM ibl_plr p JOIN ibl_team_info t ON p.teamid = t.teamid WHERE p.retired = 0 AND p.teamid BETWEEN 1 AND 28 AND (p.pg_depth = 1 OR p.sg_depth = 1 OR p.sf_depth = 1 OR p.pf_depth = 1 OR p.c_depth = 1)',
                ],
            ],
            'ContractList-getActivePlayerContracts' => [
                'ContractList-getActivePlayerContracts',
                static function (MockDatabase $db): void {
                    (new \ContractList\ContractListRepository($db))->getActivePlayerContracts();
                },
                [
                    'SELECT p.pid, p.name, p.pos, t.team_name AS teamname, p.teamid, p.cy, p.cyt, p.salary_yr1, p.salary_yr2, p.salary_yr3, p.salary_yr4, p.salary_yr5, p.salary_yr6, p.bird, t.team_city, t.color1, t.color2 FROM ibl_plr p LEFT JOIN ibl_team_info t ON p.teamid = t.teamid WHERE p.retired = 0 ORDER BY p.ordinal ASC, p.pid ASC',
                ],
            ],
            'ComparePlayers-getPlayerByName' => [
                'ComparePlayers-getPlayerByName',
                static function (MockDatabase $db): void {
                    (new \ComparePlayers\ComparePlayersRepository($db))->getPlayerByName('Pin Player');
                },
                [
                    'SELECT p.*, t.team_city, t.color1, t.color2 FROM ibl_plr p LEFT JOIN ibl_team_info t ON p.teamid = t.teamid WHERE p.name = \'Pin Player\' LIMIT 1',
                ],
            ],
            'FreeAgencyPreview-getActivePlayers' => [
                'FreeAgencyPreview-getActivePlayers',
                static function (MockDatabase $db): void {
                    (new \FreeAgencyPreview\FreeAgencyPreviewRepository($db))->getActivePlayers();
                },
                [
                    'SELECT p.pid, p.teamid, p.name, t.team_name AS teamname, p.pos, p.age, p.draftyear, p.exp, p.cy, p.cyt, p.salary_yr1, p.salary_yr2, p.salary_yr3, p.salary_yr4, p.salary_yr5, p.salary_yr6, p.r_fga, p.r_fgp, p.r_fta, p.r_ftp, p.r_3ga, p.r_3gp, p.r_orb, p.r_drb, p.r_ast, p.r_stl, p.r_blk, p.r_tvr, p.r_foul, p.oo, p.r_drive_off, p.po, p.r_trans_off, p.od, p.dd, p.pd, p.td, p.talent, p.skill, p.intangibles, p.loyalty, p.winner, p.playing_time, p.security, p.tradition, t.team_city, t.color1, t.color2 FROM ibl_plr p LEFT JOIN ibl_team_info t ON p.teamid = t.teamid WHERE p.retired = 0 ORDER BY p.ordinal ASC, p.pid ASC',
                ],
            ],
            'SeasonHighs-getSeasonHighs' => [
                'SeasonHighs-getSeasonHighs',
                static function (MockDatabase $db): void {
                    (new \SeasonHighs\SeasonHighsRepository($db))->getSeasonHighs('(`game_2gm`*2)', 'POINTS', '', '2025-01-01', '2025-01-31');
                },
                [
                    'SELECT p.pid, p.name, p.teamid, t.team_name AS teamname, t.team_city, t.color1, t.color2, bs.game_date AS date, sch.box_id, COALESCE(bs.game_of_that_day, 0) AS game_of_that_day, (game_2gm*2) AS POINTS FROM ibl_box_scores bs JOIN ibl_plr p ON bs.pid = p.pid LEFT JOIN ibl_team_info t ON p.teamid = t.teamid LEFT JOIN ibl_schedule sch ON sch.game_date = bs.game_date AND sch.visitor_teamid = bs.visitor_teamid AND sch.home_teamid = bs.home_teamid WHERE bs.game_date BETWEEN \'2025-01-01\' AND \'2025-01-31\' ORDER BY POINTS DESC, bs.game_date ASC, bs.id ASC LIMIT 15',
                ],
            ],
            'SeasonHighs-getSeasonHighsBatch' => [
                'SeasonHighs-getSeasonHighsBatch',
                static function (MockDatabase $db): void {
                    (new \SeasonHighs\SeasonHighsRepository($db))->getSeasonHighsBatch(['POINTS' => '`game_pts`', 'ASSISTS' => '`game_ast`'], '', '2025-01-01', '2025-01-31');
                },
                [
                    '(SELECT \'POINTS\' AS stat_category, p.pid, p.name, p.teamid, t.team_name AS teamname, t.team_city, t.color1, t.color2, bs.game_date AS date, sch.box_id, bs.id AS sort_id, COALESCE(bs.game_of_that_day, 0) AS game_of_that_day, (game_pts) AS stat_value FROM ibl_box_scores bs JOIN ibl_plr p ON bs.pid = p.pid LEFT JOIN ibl_team_info t ON p.teamid = t.teamid LEFT JOIN ibl_schedule sch ON sch.game_date = bs.game_date AND sch.visitor_teamid = bs.visitor_teamid AND sch.home_teamid = bs.home_teamid WHERE bs.game_date BETWEEN \'2025-01-01\' AND \'2025-01-31\' ORDER BY stat_value DESC, bs.game_date ASC, bs.id ASC LIMIT 15) UNION ALL (SELECT \'ASSISTS\' AS stat_category, p.pid, p.name, p.teamid, t.team_name AS teamname, t.team_city, t.color1, t.color2, bs.game_date AS date, sch.box_id, bs.id AS sort_id, COALESCE(bs.game_of_that_day, 0) AS game_of_that_day, (game_ast) AS stat_value FROM ibl_box_scores bs JOIN ibl_plr p ON bs.pid = p.pid LEFT JOIN ibl_team_info t ON p.teamid = t.teamid LEFT JOIN ibl_schedule sch ON sch.game_date = bs.game_date AND sch.visitor_teamid = bs.visitor_teamid AND sch.home_teamid = bs.home_teamid WHERE bs.game_date BETWEEN \'2025-01-01\' AND \'2025-01-31\' ORDER BY stat_value DESC, bs.game_date ASC, bs.id ASC LIMIT 15)',
                ],
            ],
            'League-getAllStarCandidatesResult' => [
                'League-getAllStarCandidatesResult',
                static function (MockDatabase $db): void {
                    (new \League\League($db))->getAllStarCandidatesResult('EC');
                },
                [
                    'SELECT p.*, t.team_name AS teamname, t.team_city, t.color1, t.color2 FROM ibl_plr p JOIN ibl_team_info t ON p.teamid = t.teamid WHERE p.pos IN (\'PG\', \'SG\') AND p.teamid IN (\'1\',\'2\',\'3\',\'4\',\'5\',\'7\',\'8\',\'9\',\'10\',\'11\',\'12\',\'22\',\'25\',\'27\') AND p.retired = 0 AND p.stats_gm > \'14\' ORDER BY p.name, p.pid ASC',
                ],
            ],
            'League-getMVPCandidatesResult' => [
                'League-getMVPCandidatesResult',
                static function (MockDatabase $db): void {
                    (new \League\League($db))->getMVPCandidatesResult();
                },
                [
                    'SELECT p.*, t.team_name AS teamname, t.team_city, t.color1, t.color2 FROM ibl_plr p JOIN ibl_team_info t ON p.teamid = t.teamid WHERE p.retired = 0 AND p.stats_gm >= \'41\' AND p.stats_min / p.stats_gm >= \'30\' ORDER BY p.name, p.pid ASC',
                ],
            ],
            'League-getSixthPersonOfTheYearCandidatesResult' => [
                'League-getSixthPersonOfTheYearCandidatesResult',
                static function (MockDatabase $db): void {
                    (new \League\League($db))->getSixthPersonOfTheYearCandidatesResult();
                },
                [
                    'SELECT p.*, t.team_name AS teamname, t.team_city, t.color1, t.color2 FROM ibl_plr p JOIN ibl_team_info t ON p.teamid = t.teamid WHERE p.retired = 0 AND p.stats_min / p.stats_gm >= 15 AND p.stats_gs / p.stats_gm <= \'.5\' AND p.stats_gm >= \'41\' ORDER BY p.name, p.pid ASC',
                ],
            ],
            'League-getRookieOfTheYearCandidatesResult' => [
                'League-getRookieOfTheYearCandidatesResult',
                static function (MockDatabase $db): void {
                    (new \League\League($db))->getRookieOfTheYearCandidatesResult();
                },
                [
                    'SELECT p.*, t.team_name AS teamname, t.team_city, t.color1, t.color2 FROM ibl_plr p JOIN ibl_team_info t ON p.teamid = t.teamid WHERE p.retired = 0 AND p.exp = \'1\' AND p.stats_gm >= \'41\' ORDER BY p.name, p.pid ASC',
                ],
            ],
            'ApiInjuries-getInjuredPlayers' => [
                'ApiInjuries-getInjuredPlayers',
                static function (MockDatabase $db): void {
                    (new \Api\Repository\ApiInjuriesRepository($db))->getInjuredPlayers();
                },
                [
                    'SELECT p.uuid AS player_uuid, p.pid, p.name, p.pos, p.injured, t.teamid, t.uuid AS team_uuid, t.team_city, t.team_name FROM ibl_plr p LEFT JOIN ibl_team_info t ON p.teamid = t.teamid WHERE p.injured > 0 AND p.dc_can_play_in_game = 1 ORDER BY p.injured DESC, p.pid ASC',
                ],
            ],
            'TrainingCampRatingsDiff-getDiffRows' => [
                'TrainingCampRatingsDiff-getDiffRows',
                static function (MockDatabase $db): void {
                    (new \TrainingCampRatingsDiff\TrainingCampRatingsDiffRepository($db))->getDiffRows(2024, 'end-of-season');
                },
                [
                    'SELECT p.pid, p.name, p.pos, p.age, p.teamid, t.team_name, t.color1, t.color2, p.oo, p.od, p.r_drive_off, p.dd, p.po, p.pd, p.r_trans_off, p.td, p.r_fga, p.r_fgp, p.r_fta, p.r_ftp, p.r_3ga, p.r_3gp, p.r_orb, p.r_drb, p.r_ast, p.r_stl, p.r_tvr, p.r_blk, p.r_foul, s.oo AS s_oo, s.od AS s_od, s.r_drive_off AS s_r_drive_off, s.dd AS s_dd, s.po AS s_po, s.pd AS s_pd, s.r_trans_off AS s_r_trans_off, s.td AS s_td, s.r_fga AS s_r_fga, s.r_fgp AS s_r_fgp, s.r_fta AS s_r_fta, s.r_ftp AS s_r_ftp, s.r_3ga AS s_r_3ga, s.r_3gp AS s_r_3gp, s.r_orb AS s_r_orb, s.r_drb AS s_r_drb, s.r_ast AS s_r_ast, s.r_stl AS s_r_stl, s.r_tvr AS s_r_tvr, s.r_blk AS s_r_blk, s.r_foul AS s_r_foul FROM ibl_plr p LEFT JOIN ibl_team_info t ON p.teamid = t.teamid LEFT JOIN ibl_plr_snapshots s ON s.pid = p.pid AND s.season_year = 2024 AND s.snapshot_phase = \'end-of-season\' WHERE p.retired = 0 ORDER BY p.name, p.pid',
                ],
            ],
        ];
    }

    private static function normalize(string $sql): string
    {
        $collapsed = trim((string) preg_replace('/\s+/', ' ', $sql));

        return str_replace('t.teamid = p.teamid', 'p.teamid = t.teamid', $collapsed);
    }
}
