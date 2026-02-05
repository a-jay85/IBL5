<?php

declare(strict_types=1);

namespace RecordHolders\Contracts;

/**
 * Repository interface for Record Holders module.
 *
 * Provides methods to retrieve all-time IBL records from the database.
 *
 * @phpstan-type PlayerSingleGameRecord array{
 *     pid: int,
 *     name: string,
 *     tid: int,
 *     team_name: string,
 *     date: string,
 *     BoxID: int,
 *     oppTid: int,
 *     opp_team_name: string,
 *     value: int
 * }
 *
 * @phpstan-type PlayerSeasonRecord array{
 *     pid: int,
 *     name: string,
 *     teamid: int,
 *     team: string,
 *     year: int,
 *     value: float
 * }
 *
 * @phpstan-type QuadrupleDoubleRecord array{
 *     pid: int,
 *     name: string,
 *     tid: int,
 *     team_name: string,
 *     date: string,
 *     BoxID: int,
 *     oppTid: int,
 *     opp_team_name: string,
 *     points: int,
 *     rebounds: int,
 *     assists: int,
 *     steals: int,
 *     blocks: int
 * }
 *
 * @phpstan-type AllStarRecord array{
 *     name: string,
 *     pid: int|null,
 *     appearances: int
 * }
 *
 * @phpstan-type TeamSingleGameRecord array{
 *     tid: int,
 *     team_name: string,
 *     date: string,
 *     BoxID: int,
 *     oppTid: int,
 *     opp_team_name: string,
 *     value: int
 * }
 *
 * @phpstan-type TeamHalfRecord array{
 *     tid: int,
 *     team_name: string,
 *     date: string,
 *     BoxID: int,
 *     oppTid: int,
 *     opp_team_name: string,
 *     value: int
 * }
 *
 * @phpstan-type MarginRecord array{
 *     winner_tid: int,
 *     winner_name: string,
 *     loser_tid: int,
 *     loser_name: string,
 *     date: string,
 *     BoxID: int,
 *     margin: int
 * }
 *
 * @phpstan-type SeasonWinLossRecord array{
 *     team_name: string,
 *     year: string,
 *     wins: int,
 *     losses: int
 * }
 *
 * @phpstan-type StreakRecord array{
 *     team_name: string,
 *     streak: int,
 *     start_date: string,
 *     end_date: string,
 *     start_year: int,
 *     end_year: int
 * }
 *
 * @phpstan-type SeasonStartRecord array{
 *     team_name: string,
 *     year: int,
 *     wins: int,
 *     losses: int
 * }
 *
 * @phpstan-type FranchiseTitleRecord array{
 *     team_name: string,
 *     count: int,
 *     years: string
 * }
 *
 * @phpstan-type PlayoffAppearanceRecord array{
 *     team_name: string,
 *     count: int,
 *     years: string
 * }
 */
interface RecordHoldersRepositoryInterface
{
    /**
     * Get the top player single-game record for a stat.
     *
     * @param string $statExpression SQL expression for the stat
     * @param string $dateFilter SQL WHERE clause for date filtering
     * @return list<PlayerSingleGameRecord>
     */
    public function getTopPlayerSingleGame(string $statExpression, string $dateFilter): array;

    /**
     * Get the top player full-season average for a stat.
     *
     * @param string $statColumn Column name in ibl_hist for the stat
     * @param string $gamesColumn Column name for games played
     * @param int $minGames Minimum games required
     * @return list<PlayerSeasonRecord>
     */
    public function getTopSeasonAverage(string $statColumn, string $gamesColumn, int $minGames = 50): array;

    /**
     * Get all quadruple doubles in IBL history.
     *
     * @return list<QuadrupleDoubleRecord>
     */
    public function getQuadrupleDoubles(): array;

    /**
     * Get the player(s) with the most All-Star appearances.
     *
     * @return list<AllStarRecord>
     */
    public function getMostAllStarAppearances(): array;

    /**
     * Get the top team single-game record for a stat.
     *
     * @param string $statExpression SQL expression for the stat
     * @param string $dateFilter SQL WHERE clause for date filtering
     * @param string $order Sort direction ('DESC' or 'ASC')
     * @return list<TeamSingleGameRecord>
     */
    public function getTopTeamSingleGame(string $statExpression, string $dateFilter, string $order = 'DESC'): array;

    /**
     * Get the top team half scoring record.
     *
     * @param string $half Which half ('first' or 'second')
     * @param string $order Sort direction ('DESC' or 'ASC')
     * @return list<TeamHalfRecord>
     */
    public function getTopTeamHalfScore(string $half, string $order): array;

    /**
     * Get the largest margin of victory.
     *
     * @param string $dateFilter SQL WHERE clause for date filtering
     * @return list<MarginRecord>
     */
    public function getLargestMarginOfVictory(string $dateFilter): array;

    /**
     * Get best or worst season record.
     *
     * @param string $order Sort direction ('DESC' or 'ASC' by win percentage)
     * @return list<SeasonWinLossRecord>
     */
    public function getBestWorstSeasonRecord(string $order): array;

    /**
     * Get the longest winning or losing streak.
     *
     * @param string $type Streak type ('winning' or 'losing')
     * @return list<StreakRecord>
     */
    public function getLongestStreak(string $type): array;

    /**
     * Get the best or worst season start.
     *
     * @param string $type Start type ('best' or 'worst')
     * @return list<SeasonStartRecord>
     */
    public function getBestWorstSeasonStart(string $type): array;

    /**
     * Get the team(s) with the most playoff appearances.
     *
     * @return list<PlayoffAppearanceRecord>
     */
    public function getMostPlayoffAppearances(): array;

    /**
     * Get the team(s) with the most titles of a given type.
     *
     * @param string $titlePattern LIKE pattern for the award type
     * @return list<FranchiseTitleRecord>
     */
    public function getMostTitlesByType(string $titlePattern): array;
}
