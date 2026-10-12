<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use Search\Contracts\SearchRepositoryInterface;
use Search\SearchRepository;
use Standings\StandingsRepository;
use TeamOffDefStats\TeamOffDefStatsRepository;
use Tests\WideUnit\Mocks\MockDatabase;

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
}
