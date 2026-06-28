<?php

declare(strict_types=1);

namespace Boxscore\Contracts;

/**
 * BoxscoreRepositoryInterface - Contract for boxscore data access
 *
 * Defines methods for managing boxscore data in the database.
 *
 * @see \Boxscore\BoxscoreRepository For the concrete implementation
 */
interface BoxscoreRepositoryInterface
{
    /**
     * Delete preseason boxscores for both players and teams
     *
     * Removes all preseason boxscore records (September) of the given season beginning year.
     *
     * @param int $seasonBeginningYear The year the season starts (e.g., 2024 for 2024-25 season)
     * @return bool True if both deletions succeeded, false otherwise
     */
    public function deletePreseasonBoxScores(int $seasonBeginningYear): bool;

    /**
     * Delete H.E.A.T. tournament boxscores for both players and teams
     *
     * Removes all boxscore records from October (HEAT month)
     * of the specified season starting year.
     *
     * @param int $seasonStartingYear The year the season starts (e.g., 2024 for 2024-25 season)
     * @return bool True if both deletions succeeded, false otherwise
     */
    public function deleteHeatBoxScores(int $seasonStartingYear): bool;

    /**
     * Delete regular season and playoff boxscores for both players and teams
     *
     * Removes all boxscore records from November of the starting year
     * through June of the following year.
     *
     * @param int $seasonStartingYear The year the season starts (e.g., 2024 for 2024-25 season)
     * @return bool True if both deletions succeeded, false otherwise
     */
    public function deleteRegularSeasonAndPlayoffsBoxScores(int $seasonStartingYear): bool;

    /**
     * Find a team boxscore by game identifiers
     *
     * @param string $date Game date in Y-m-d format
     * @param int $visitor_teamid Visitor team ID
     * @param int $home_teamid Home team ID
     * @param int $game_of_that_day Game number for that day (1 = first game, 2 = doubleheader)
     * @return array<string, mixed>|null Row with quarter point columns, or null if not found
     */
    public function findTeamBoxscore(string $date, int $visitor_teamid, int $home_teamid, int $game_of_that_day): ?array;

    /**
     * Delete team boxscore records for a specific game
     *
     * @param string $date Game date in Y-m-d format
     * @param int $visitor_teamid Visitor team ID
     * @param int $home_teamid Home team ID
     * @param int $game_of_that_day Game number for that day
     * @return int Number of affected rows
     */
    public function deleteTeamBoxscoresByGame(string $date, int $visitor_teamid, int $home_teamid, int $game_of_that_day): int;

    /**
     * Delete player boxscore records for a specific game
     *
     * @param string $date Game date in Y-m-d format
     * @param int $visitor_teamid Visitor team ID
     * @param int $home_teamid Home team ID
     * @return int Number of affected rows
     */
    public function deletePlayerBoxscoresByGame(string $date, int $visitor_teamid, int $home_teamid): int;

    /**
     * Insert a team boxscore row.
     *
     * @param array{
     *     game_date: string, name: string, game_of_that_day: int, visitor_teamid: int, home_teamid: int,
     *     attendance: int, capacity: int, visitor_wins: int, visitor_losses: int, home_wins: int, home_losses: int,
     *     visitor_q1_points: int, visitor_q2_points: int, visitor_q3_points: int, visitor_q4_points: int, visitor_ot_points: int,
     *     home_q1_points: int, home_q2_points: int, home_q3_points: int, home_q4_points: int, home_ot_points: int,
     *     game_2gm: int, game_2ga: int, game_ftm: int, game_fta: int, game_3gm: int, game_3ga: int,
     *     game_orb: int, game_drb: int, game_ast: int, game_stl: int, game_tov: int, game_blk: int, game_pf: int
     * } $row Column => value map; keys match `ibl_box_scores_teams` column names.
     * @return int Number of affected rows
     */
    public function insertTeamBoxscore(array $row): int;

    /**
     * Check if any player boxscore records for a game have NULL teamid
     *
     * @param string $date Game date in Y-m-d format
     * @param int $visitor_teamid Visitor team ID
     * @param int $home_teamid Home team ID
     * @return bool True if at least one player record has NULL teamid
     */
    public function hasNullTeamIdPlayerBoxscores(string $date, int $visitor_teamid, int $home_teamid): bool;

    /**
     * Find All-Star Game team names from existing boxscore records
     *
     * Queries ibl_box_scores_teams for the All-Star Game (visitor_teamid=ALL_STAR_AWAY_TEAMID, home_teamid=ALL_STAR_HOME_TEAMID)
     * on the given date. Returns the team names from the two team-total rows.
     *
     * @param string $date Game date in Y-m-d format
     * @return array{awayName: string, homeName: string}|null Team names or null if not found
     */
    public function findAllStarTeamNames(string $date): ?array;

    /**
     * Find All-Star Game team records that still have default placeholder names
     *
     * Returns rows from `ibl_box_scores_teams` where name is 'Team Away' or 'Team Home'
     * and the game is an All-Star Game (visitor_teamid=ALL_STAR_AWAY_TEAMID, home_teamid=ALL_STAR_HOME_TEAMID).
     *
     * @return list<array{id: int, game_date: string, name: string, visitor_teamid: int, home_teamid: int}>
     */
    public function findAllStarGamesWithDefaultNames(): array;

    /**
     * Get player names for an All-Star team on a given date
     *
     * Uses full names from `ibl_plr` (falls back to ibl_box_scores.name for team total rows).
     *
     * @param string $date Game date in Y-m-d format
     * @param int $teamid Team ID (50 = visitor, 51 = home)
     * @return list<string> Player names in insertion order
     */
    public function getPlayersForAllStarTeam(string $date, int $teamid): array;

    /**
     * Rename an All-Star team by updating the name on a team boxscore record
     *
     * @param int $recordId Primary key of the ibl_box_scores_teams row
     * @param string $newName New team name (max 16 chars)
     * @return int Number of affected rows
     */
    public function renameAllStarTeam(int $recordId, string $newName): int;

    /**
     * Insert a player boxscore row
     *
     * @param string $date Game date in Y-m-d format
     * @param string $uuid UUID for this record
     * @param string $name Player name
     * @param string $position Player position
     * @param int $playerID Player ID
     * @param int $visitor_teamid Visitor team ID
     * @param int $home_teamid Home team ID
     * @param int $game_of_that_day Game number for that date (1st, 2nd game)
     * @param int $attendance Attendance at the game
     * @param int $capacity Arena capacity
     * @param int $visitor_wins Visitor team wins before this game
     * @param int $visitor_losses Visitor team losses before this game
     * @param int $home_wins Home team wins before this game
     * @param int $home_losses Home team losses before this game
     * @param int $teamid Player's team ID (visitor or home)
     * @param int $minutesPlayed Minutes played
     * @param int $fieldGoalsMade FGM
     * @param int $fieldGoalsAttempted FGA
     * @param int $freeThrowsMade FTM
     * @param int $freeThrowsAttempted FTA
     * @param int $threePointersMade 3PM
     * @param int $threePointersAttempted 3PA
     * @param int $offensiveRebounds ORB
     * @param int $defensiveRebounds DRB
     * @param int $assists AST
     * @param int $steals STL
     * @param int $turnovers TOV
     * @param int $blocks BLK
     * @param int $personalFouls PF
     * @return int Number of affected rows
     */
    public function insertPlayerBoxscore(
        string $date,
        string $uuid,
        string $name,
        string $position,
        int $playerID,
        int $visitor_teamid,
        int $home_teamid,
        int $game_of_that_day,
        int $attendance,
        int $capacity,
        int $visitor_wins,
        int $visitor_losses,
        int $home_wins,
        int $home_losses,
        int $teamid,
        int $minutesPlayed,
        int $fieldGoalsMade,
        int $fieldGoalsAttempted,
        int $freeThrowsMade,
        int $freeThrowsAttempted,
        int $threePointersMade,
        int $threePointersAttempted,
        int $offensiveRebounds,
        int $defensiveRebounds,
        int $assists,
        int $steals,
        int $turnovers,
        int $blocks,
        int $personalFouls,
    ): int;
}
