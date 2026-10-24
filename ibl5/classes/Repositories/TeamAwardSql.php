<?php

declare(strict_types=1);

namespace Repositories;

/**
 * SQL fragments shared by the two team-award builders:
 * SeasonArchiveRepository::buildTeamAwardsByYearQuery() and
 * TeamRepository::buildTeamAccomplishmentsQuery().
 *
 * Constants only. Each builder owns its WHERE predicates and bind order;
 * no runtime value is ever concatenated into these strings.
 */
final class TeamAwardSql
{
    /**
     * Hierarchical award ordering used by team-award queries.
     *
     * Orders awards from hardest to easiest to win so award displays are
     * deterministic (avoids flaky e2e tests when a year has multiple awards):
     * IBL Champions → IBL HEAT Champions → Conference Champions (alpha) →
     * Division Champions (alpha) → IBL Draft Lottery Winners → everything else.
     */
    public const string AWARD_HIERARCHY_CASE = "CASE award
                WHEN 'IBL Champions' THEN 1
                WHEN 'IBL HEAT Champions' THEN 2
                WHEN 'Eastern Conference Champions' THEN 3
                WHEN 'Western Conference Champions' THEN 4
                WHEN 'Atlantic Division Champions' THEN 5
                WHEN 'Central Division Champions' THEN 6
                WHEN 'Midwest Division Champions' THEN 7
                WHEN 'Pacific Division Champions' THEN 8
                WHEN 'IBL Draft Lottery Winners' THEN 9
                ELSE 10
            END";

    /**
     * Inner HEAT subselect: one row per HEAT box score with the winner and a
     * per-year row number. Ends on the game_type predicate so a caller may
     * append further AND clauses (the archive adds the year filter).
     */
    public const string HEAT_WINNER_SUBSELECT = "SELECT
                    YEAR(bst.game_date) AS year,
                    CASE
                        WHEN COALESCE(bst.home_q1_points + bst.home_q2_points + bst.home_q3_points + bst.home_q4_points
                                      + COALESCE(bst.home_ot_points, 0),
                                      FIRST_VALUE(bst.calc_points) OVER (
                                          PARTITION BY bst.game_date, bst.visitor_teamid, bst.home_teamid, bst.game_of_that_day
                                          ORDER BY bst.id DESC))
                           > COALESCE(bst.visitor_q1_points + bst.visitor_q2_points + bst.visitor_q3_points + bst.visitor_q4_points
                                      + COALESCE(bst.visitor_ot_points, 0),
                                      FIRST_VALUE(bst.calc_points) OVER (
                                          PARTITION BY bst.game_date, bst.visitor_teamid, bst.home_teamid, bst.game_of_that_day
                                          ORDER BY bst.id ASC))
                        THEN bst.home_teamid
                        ELSE bst.visitor_teamid
                    END AS winner_tid,
                    ROW_NUMBER() OVER (
                        PARTITION BY YEAR(bst.game_date)
                        ORDER BY bst.game_date DESC, bst.game_of_that_day ASC, bst.id ASC
                    ) AS rn
                FROM `ibl_box_scores_teams` bst
                WHERE bst.game_type = 3";

    /** Conference-champion source: playoff series joined to the conference of the winner that year. */
    public const string CONFERENCE_CHAMPION_JOIN = "FROM vw_playoff_series_results psr
                LEFT JOIN `ibl_franchise_seasons` fs
                    ON fs.franchise_id = psr.winner_tid AND fs.season_ending_year = psr.year
                JOIN `ibl_league_config` lc
                    ON lc.season_ending_year = psr.year
                   AND lc.team_name = COALESCE(fs.team_name, psr.winner)";
}
