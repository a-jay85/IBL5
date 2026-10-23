<?php

declare(strict_types=1);

namespace Tests\SeasonArchive;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SeasonArchive\SeasonArchiveRepository;

/**
 * Pins the SQL text, bind types, and bind order that
 * SeasonArchiveRepository::getTeamAwardsByYear() hands to fetchAll().
 *
 * Full-SQL equality is asserted on purpose: this is a SQL-construction security
 * test. Whitespace is collapsed, so only a change to the query itself fails.
 */
class SeasonArchiveTeamAwardsSqlPinTest extends TestCase
{
    private const string EXPECTED_SQL = "SELECT year, name, award, id FROM `ibl_team_awards` WHERE year = ? UNION ALL SELECT ranked.year, ranked.name, 'IBL Champions' AS award, 0 AS id FROM ( SELECT psr.year, psr.winner AS name, psr.round, MAX(psr.round) OVER (PARTITION BY psr.year) AS max_round, COUNT(*) OVER (PARTITION BY psr.year, psr.round) AS series_in_round FROM vw_playoff_series_results psr WHERE psr.year = ? ) ranked WHERE ranked.round = ranked.max_round AND ranked.series_in_round = 1 UNION ALL SELECT ranked.year, ranked.name, ranked.award, 0 AS id FROM ( SELECT psr.year AS year, psr.winner AS name, CONCAT(lc.conference, ' Conference Champions') AS award, COUNT(*) OVER (PARTITION BY psr.year, lc.conference) AS series_in_conf FROM vw_playoff_series_results psr LEFT JOIN `ibl_franchise_seasons` fs ON fs.franchise_id = psr.winner_tid AND fs.season_ending_year = psr.year JOIN `ibl_league_config` lc ON lc.season_ending_year = psr.year AND lc.team_name = COALESCE(fs.team_name, psr.winner) WHERE psr.round = 3 AND psr.year = ? AND psr.winner_games >= NULLIF(CAST(SUBSTRING_INDEX(lc.playoff_round3_format, ' ', 1) AS UNSIGNED), 0) ) ranked WHERE ranked.series_in_conf = 1 UNION ALL SELECT hc.year, ti.team_name AS name, 'IBL HEAT Champions' AS award, 0 AS id FROM ( SELECT YEAR(bst.game_date) AS year, CASE WHEN COALESCE(bst.home_q1_points + bst.home_q2_points + bst.home_q3_points + bst.home_q4_points + COALESCE(bst.home_ot_points, 0), FIRST_VALUE(bst.calc_points) OVER ( PARTITION BY bst.game_date, bst.visitor_teamid, bst.home_teamid, bst.game_of_that_day ORDER BY bst.id DESC)) > COALESCE(bst.visitor_q1_points + bst.visitor_q2_points + bst.visitor_q3_points + bst.visitor_q4_points + COALESCE(bst.visitor_ot_points, 0), FIRST_VALUE(bst.calc_points) OVER ( PARTITION BY bst.game_date, bst.visitor_teamid, bst.home_teamid, bst.game_of_that_day ORDER BY bst.id ASC)) THEN bst.home_teamid ELSE bst.visitor_teamid END AS winner_tid, ROW_NUMBER() OVER ( PARTITION BY YEAR(bst.game_date) ORDER BY bst.game_date DESC, bst.game_of_that_day ASC, bst.id ASC ) AS rn FROM `ibl_box_scores_teams` bst WHERE bst.game_type = 3 AND YEAR(bst.game_date) = ? ) hc JOIN `ibl_team_info` ti ON ti.teamid = hc.winner_tid WHERE hc.rn = 1 ORDER BY CASE award WHEN 'IBL Champions' THEN 1 WHEN 'IBL HEAT Champions' THEN 2 WHEN 'Eastern Conference Champions' THEN 3 WHEN 'Western Conference Champions' THEN 4 WHEN 'Atlantic Division Champions' THEN 5 WHEN 'Central Division Champions' THEN 6 WHEN 'Midwest Division Champions' THEN 7 WHEN 'Pacific Division Champions' THEN 8 WHEN 'IBL Draft Lottery Winners' THEN 9 ELSE 10 END, award ASC, name ASC, id ASC";

    /**
     * @return SeasonArchiveRepository&object{sql: string, types: string, params: list<mixed>}
     */
    private function capturingRepository(): SeasonArchiveRepository
    {
        return new class (new \mysqli()) extends SeasonArchiveRepository {
            public string $sql = '';
            public string $types = '';
            /** @var list<mixed> */
            public array $params = [];

            protected function fetchAll(string $query, string $types = '', mixed ...$params): array
            {
                $this->sql = $query;
                $this->types = $types;
                $this->params = array_values($params);
                return [];
            }
        };
    }

    private static function normalise(string $sql): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $sql));
    }

    #[Test]
    public function teamAwardsByYearSqlTextIsPinned(): void
    {
        $repo = $this->capturingRepository();
        $repo->getTeamAwardsByYear(2025, 2026);

        self::assertSame(self::EXPECTED_SQL, self::normalise($repo->sql));
    }

    #[Test]
    public function teamAwardsByYearBindsFourIntegersInOrder(): void
    {
        $repo = $this->capturingRepository();
        $repo->getTeamAwardsByYear(2025, 2026);

        self::assertSame('iiii', $repo->types);
        self::assertSame([2025, 2025, 2025, 2026], $repo->params);
        self::assertSame(4, substr_count($repo->sql, '?'));
    }

    #[Test]
    public function teamAwardsByYearSqlTextCarriesNoRuntimeValue(): void
    {
        $repo = $this->capturingRepository();
        $repo->getTeamAwardsByYear(2025, 2026);

        self::assertStringNotContainsString('2025', $repo->sql);
        self::assertStringNotContainsString('2026', $repo->sql);
    }
}
