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
     * Removes all boxscore records from November (preseason month)
     * of the preseason year (9998).
     *
     * @return bool True if both deletions succeeded, false otherwise
     */
    public function deletePreseasonBoxScores(): bool;

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
     * @param int $visitorTeamID Visitor team ID
     * @param int $homeTeamID Home team ID
     * @param int $gameOfThatDay Game number for that day (1 = first game, 2 = doubleheader)
     * @return array<string, mixed>|null Row with quarter point columns, or null if not found
     */
    public function findTeamBoxscore(string $date, int $visitorTeamID, int $homeTeamID, int $gameOfThatDay): ?array;

    /**
     * Delete team boxscore records for a specific game
     *
     * @param string $date Game date in Y-m-d format
     * @param int $visitorTeamID Visitor team ID
     * @param int $homeTeamID Home team ID
     * @param int $gameOfThatDay Game number for that day
     * @return int Number of affected rows
     */
    public function deleteTeamBoxscoresByGame(string $date, int $visitorTeamID, int $homeTeamID, int $gameOfThatDay): int;

    /**
     * Delete player boxscore records for a specific game
     *
     * @param string $date Game date in Y-m-d format
     * @param int $visitorTID Visitor team ID
     * @param int $homeTID Home team ID
     * @return int Number of affected rows
     */
    public function deletePlayerBoxscoresByGame(string $date, int $visitorTID, int $homeTID): int;

    /**
     * Insert a team boxscore row
     *
     * @param string $date Game date in Y-m-d format
     * @param string $name Team name from .sco file
     * @param int $gameOfThatDay Game number for that day
     * @param int $visitorTeamID Visitor team ID
     * @param int $homeTeamID Home team ID
     * @param int $attendance Attendance figure
     * @param int $capacity Arena capacity
     * @param int $visitorWins Visitor win count
     * @param int $visitorLosses Visitor loss count
     * @param int $homeWins Home win count
     * @param int $homeLosses Home loss count
     * @param int $visitorQ1points Visitor Q1 points
     * @param int $visitorQ2points Visitor Q2 points
     * @param int $visitorQ3points Visitor Q3 points
     * @param int $visitorQ4points Visitor Q4 points
     * @param int $visitorOTpoints Visitor OT points
     * @param int $homeQ1points Home Q1 points
     * @param int $homeQ2points Home Q2 points
     * @param int $homeQ3points Home Q3 points
     * @param int $homeQ4points Home Q4 points
     * @param int $homeOTpoints Home OT points
     * @param int $fieldGoalsMade Game FGM
     * @param int $fieldGoalsAttempted Game FGA
     * @param int $freeThrowsMade Game FTM
     * @param int $freeThrowsAttempted Game FTA
     * @param int $threePointersMade Game 3PM
     * @param int $threePointersAttempted Game 3PA
     * @param int $offensiveRebounds Game ORB
     * @param int $defensiveRebounds Game DRB
     * @param int $assists Game AST
     * @param int $steals Game STL
     * @param int $turnovers Game TOV
     * @param int $blocks Game BLK
     * @param int $personalFouls Game PF
     * @return int Number of affected rows
     */
    public function insertTeamBoxscore(
        string $date,
        string $name,
        int $gameOfThatDay,
        int $visitorTeamID,
        int $homeTeamID,
        int $attendance,
        int $capacity,
        int $visitorWins,
        int $visitorLosses,
        int $homeWins,
        int $homeLosses,
        int $visitorQ1points,
        int $visitorQ2points,
        int $visitorQ3points,
        int $visitorQ4points,
        int $visitorOTpoints,
        int $homeQ1points,
        int $homeQ2points,
        int $homeQ3points,
        int $homeQ4points,
        int $homeOTpoints,
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

    /**
     * Find All-Star Game team names from existing boxscore records
     *
     * Queries ibl_box_scores_teams for the All-Star Game (visitorTeamID=50, homeTeamID=51)
     * on the given date. Returns the team names from the two team-total rows.
     *
     * @param string $date Game date in Y-m-d format
     * @return array{awayName: string, homeName: string}|null Team names or null if not found
     */
    public function findAllStarTeamNames(string $date): ?array;

    /**
     * Insert a player boxscore row
     *
     * @param string $date Game date in Y-m-d format
     * @param string $uuid UUID for this record
     * @param string $name Player name
     * @param string $position Player position
     * @param int $playerID Player ID
     * @param int $visitorTeamID Visitor team ID
     * @param int $homeTeamID Home team ID
     * @param int $gameOfThatDay Game number for that date (1st, 2nd game)
     * @param int $attendance Attendance at the game
     * @param int $capacity Arena capacity
     * @param int $visitorWins Visitor team wins before this game
     * @param int $visitorLosses Visitor team losses before this game
     * @param int $homeWins Home team wins before this game
     * @param int $homeLosses Home team losses before this game
     * @param int $teamID Player's team ID (visitor or home)
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
        int $visitorTeamID,
        int $homeTeamID,
        int $gameOfThatDay,
        int $attendance,
        int $capacity,
        int $visitorWins,
        int $visitorLosses,
        int $homeWins,
        int $homeLosses,
        int $teamID,
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
