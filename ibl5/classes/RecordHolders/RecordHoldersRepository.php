<?php

declare(strict_types=1);

namespace RecordHolders;

use League\League;
use League\LeagueContext;
use RecordHolders\Contracts\RecordHoldersRepositoryInterface;

/**
 * RecordHoldersRepository - Data access layer for all-time IBL records.
 *
 * Retrieves record data from box scores, history, awards, and team tables.
 *
 * @phpstan-import-type PlayerSingleGameRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type PlayerSeasonRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type QuadrupleDoubleRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type AllStarRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type TeamSingleGameRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type TeamHalfRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type MarginRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type SeasonWinLossRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type StreakRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type SeasonStartRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type FranchiseTitleRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type PlayoffAppearanceRecord from RecordHoldersRepositoryInterface
 *
 * @see RecordHoldersRepositoryInterface
 * @see \BaseMysqliRepository For base class documentation
 */
class RecordHoldersRepository extends \BaseMysqliRepository implements RecordHoldersRepositoryInterface
{
    private const ANNOUNCEMENT_CACHE_KEY = 'record_announcements_last_date';

    private PlayerRecordRepository $playerRecords;
    private TeamRecordRepository $teamRecords;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->playerRecords = new PlayerRecordRepository($db, $leagueContext);
        $this->teamRecords = new TeamRecordRepository($db, $leagueContext);
    }

    /**
     * @see RecordHoldersRepositoryInterface::getQuadrupleDoubles()
     *
     * @return list<QuadrupleDoubleRecord>
     */
    public function getQuadrupleDoubles(): array
    {
        return $this->playerRecords->getQuadrupleDoubles();
    }

    /**
     * @see RecordHoldersRepositoryInterface::getMostAllStarAppearances()
     *
     * @return list<AllStarRecord>
     */
    public function getMostAllStarAppearances(): array
    {
        return $this->playerRecords->getMostAllStarAppearances();
    }

    /**
     * @see RecordHoldersRepositoryInterface::getTopTeamHalfScore()
     *
     * @return list<TeamHalfRecord>
     */
    public function getTopTeamHalfScore(string $half, string $order): array
    {
        return $this->teamRecords->getTopTeamHalfScore($half, $order);
    }

    /**
     * @see RecordHoldersRepositoryInterface::getLargestMarginOfVictory()
     *
     * @return list<MarginRecord>
     */
    public function getLargestMarginOfVictory(string $dateFilter): array
    {
        return $this->teamRecords->getLargestMarginOfVictory($dateFilter);
    }

    /**
     * @see RecordHoldersRepositoryInterface::getBestWorstSeasonRecord()
     *
     * @return list<SeasonWinLossRecord>
     */
    public function getBestWorstSeasonRecord(string $order): array
    {
        return $this->teamRecords->getBestWorstSeasonRecord($order);
    }

    /**
     * @see RecordHoldersRepositoryInterface::getLongestStreak()
     *
     * @return list<StreakRecord>
     */
    public function getLongestStreak(string $type): array
    {
        return $this->teamRecords->getLongestStreak($type);
    }

    /**
     * @see RecordHoldersRepositoryInterface::getBestWorstSeasonStart()
     *
     * @return list<SeasonStartRecord>
     */
    public function getBestWorstSeasonStart(string $type): array
    {
        return $this->teamRecords->getBestWorstSeasonStart($type);
    }

    /**
     * @see RecordHoldersRepositoryInterface::getMostPlayoffAppearances()
     *
     * @return list<PlayoffAppearanceRecord>
     */
    public function getMostPlayoffAppearances(): array
    {
        $query = "SELECT
                t.team_name,
                COUNT(DISTINCT pr.year) AS count,
                GROUP_CONCAT(DISTINCT pr.year ORDER BY pr.year ASC SEPARATOR ', ') AS years
            FROM vw_playoff_series_results pr
            JOIN `ibl_team_info` t ON t.teamid = pr.winner_tid OR t.teamid = pr.loser_tid
            WHERE t.teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
            GROUP BY t.team_name
            ORDER BY count DESC, t.team_name ASC
            LIMIT 5";

        $rows = $this->fetchAll($query);

        // Find the max count to include ties
        $maxCount = 0;
        foreach ($rows as $countRow) {
            /** @var array{team_name: string, count: int, years: string} $countRow */
            if ($countRow['count'] > $maxCount) {
                $maxCount = $countRow['count'];
            }
        }

        /** @var list<PlayoffAppearanceRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{team_name: string, count: int, years: string} $row */
            if ($row['count'] === $maxCount) {
                $records[] = [
                    'team_name' => $row['team_name'],
                    'count' => $row['count'],
                    'years' => $row['years'],
                ];
            }
        }

        return $records;
    }

    /**
     * @see RecordHoldersRepositoryInterface::getTopPlayerSingleGameBatch()
     *
     * @param array<string, string> $statExpressions
     * @return array<string, list<PlayerSingleGameRecord>>
     */
    public function getTopPlayerSingleGameBatch(array $statExpressions, string $dateFilter): array
    {
        return $this->playerRecords->getTopPlayerSingleGameBatch($statExpressions, $dateFilter);
    }

    /**
     * @see RecordHoldersRepositoryInterface::getTopTeamSingleGameBatch()
     *
     * @param array<string, array{expression: string, order: string}> $statExpressions
     * @return array<string, list<TeamSingleGameRecord>>
     */
    public function getTopTeamSingleGameBatch(array $statExpressions, string $dateFilter): array
    {
        return $this->teamRecords->getTopTeamSingleGameBatch($statExpressions, $dateFilter);
    }

    /**
     * @see RecordHoldersRepositoryInterface::getTopSeasonAverageBatch()
     *
     * @param array<string, array{statColumn: string, gamesColumn: string}> $statColumns
     * @return array<string, list<PlayerSeasonRecord>>
     */
    public function getTopSeasonAverageBatch(array $statColumns, int $minGames = 50): array
    {
        return $this->playerRecords->getTopSeasonAverageBatch($statColumns, $minGames);
    }

    /**
     * @see RecordHoldersRepositoryInterface::getMostTitlesByType()
     *
     * @return list<FranchiseTitleRecord>
     */
    public function getMostTitlesByType(string $titlePattern): array
    {
        $rows = $this->fetchAll(
            self::buildMostTitlesByTypeQuery(),
            's',
            '%' . $titlePattern . '%'
        );

        // Find the max count to include ties
        $maxCount = 0;
        foreach ($rows as $countRow) {
            /** @var array{team_name: string, count: int, years: string} $countRow */
            if ($countRow['count'] > $maxCount) {
                $maxCount = $countRow['count'];
            }
        }

        /** @var list<FranchiseTitleRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{team_name: string, count: int, years: string} $row */
            if ($row['count'] === $maxCount) {
                $records[] = [
                    'team_name' => $row['team_name'],
                    'count' => $row['count'],
                    'years' => $row['years'],
                ];
            }
        }

        return $records;
    }

    /**
     * Inlined team awards query with optimized Champions/HEAT branches.
     *
     * Uses window functions instead of correlated subqueries. The HEAT-champion
     * branch reads from the `vw_heat_champions` view (migration 149) rather than
     * an inline CTE.
     */
    private static function buildMostTitlesByTypeQuery(): string
    {
        return "SELECT
                name AS team_name,
                COUNT(*) AS count,
                GROUP_CONCAT(year ORDER BY year ASC SEPARATOR ', ') AS years
            FROM (
                SELECT year, name, award
                FROM `ibl_team_awards`

                UNION ALL

                SELECT ranked.year, ranked.name, 'IBL Champions' AS award
                FROM (
                    SELECT
                        psr.year,
                        psr.winner AS name,
                        psr.round,
                        MAX(psr.round) OVER (PARTITION BY psr.year) AS max_round,
                        COUNT(*) OVER (PARTITION BY psr.year, psr.round) AS series_in_round
                    FROM vw_playoff_series_results psr
                ) ranked
                WHERE ranked.round = ranked.max_round AND ranked.series_in_round = 1

                UNION ALL

                SELECT year, name, award
                FROM `vw_heat_champions`
            ) all_awards
            WHERE award LIKE ?
            GROUP BY name
            ORDER BY count DESC, name ASC
            LIMIT 5";
    }

    /**
     * @see RecordHoldersRepositoryInterface::getLastAnnouncedDate()
     */
    public function getLastAnnouncedDate(): ?string
    {
        $row = $this->fetchOne(
            "SELECT `value` FROM `cache` WHERE `cache_key` = ?",
            's',
            self::ANNOUNCEMENT_CACHE_KEY
        );

        if ($row === null) {
            return null;
        }

        /** @var array{value: string} $row */
        return $row['value'];
    }

    /**
     * @see RecordHoldersRepositoryInterface::markAnnouncementsProcessed()
     */
    public function markAnnouncementsProcessed(string $gameDate): void
    {
        $this->execute(
            "REPLACE INTO `cache` (`cache_key`, `value`, `expiration`) VALUES (?, ?, 0)",
            'ss',
            self::ANNOUNCEMENT_CACHE_KEY,
            $gameDate
        );
    }

    /**
     * @see RecordHoldersRepositoryInterface::getUnannouncedGameDates()
     *
     * @return list<string>
     */
    public function getUnannouncedGameDates(?string $lastAnnouncedDate): array
    {
        // Get the latest sim's date range from `ibl_sim_dates`
        /** @var array{start_date: string, end_date: string}|null $latestSim */
        $latestSim = $this->fetchOne(
            "SELECT start_date, end_date FROM `ibl_sim_dates` ORDER BY sim DESC LIMIT 1"
        );

        if ($latestSim === null) {
            return [];
        }

        $simStart = $latestSim['start_date'];
        $simEnd = $latestSim['end_date'];

        // If the last announced date is at or after the sim end, everything is already processed
        if ($lastAnnouncedDate !== null && $lastAnnouncedDate >= $simEnd) {
            return [];
        }

        // Use the later of sim start or (lastAnnouncedDate + 1 day) as the floor
        $floor = $simStart;
        if ($lastAnnouncedDate !== null && $lastAnnouncedDate >= $simStart) {
            $floor = $lastAnnouncedDate;
        }

        /** @var list<array{game_date: string}> $rows */
        $rows = $this->fetchAll(
            "SELECT DISTINCT game_date FROM `ibl_box_scores` WHERE game_date > ? AND game_date <= ? ORDER BY game_date ASC",
            'ss',
            $floor,
            $simEnd
        );

        return array_map(static fn(array $row): string => $row['game_date'], $rows);
    }

}
