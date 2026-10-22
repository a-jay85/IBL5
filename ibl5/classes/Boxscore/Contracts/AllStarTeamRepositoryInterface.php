<?php

declare(strict_types=1);

namespace Boxscore\Contracts;

/**
 * AllStarTeamRepositoryInterface - Contract for All-Star game team data access
 *
 * Reads and renames the two team rows of an All-Star game and lists each side's players.
 *
 * @see \Boxscore\AllStarTeamRepository For the concrete implementation
 */
interface AllStarTeamRepositoryInterface
{
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
}
