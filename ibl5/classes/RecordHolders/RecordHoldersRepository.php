<?php

declare(strict_types=1);

namespace RecordHolders;

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
    private PlayerRecordRepository $playerRecords;
    private TeamRecordRepository $teamRecords;
    private FranchiseRecordRepository $franchiseRecords;
    private RecordAnnouncementRepository $announcements;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->playerRecords = new PlayerRecordRepository($db, $leagueContext);
        $this->teamRecords = new TeamRecordRepository($db, $leagueContext);
        $this->franchiseRecords = new FranchiseRecordRepository($db, $leagueContext);
        $this->announcements = new RecordAnnouncementRepository($db, $leagueContext);
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
        return $this->franchiseRecords->getMostPlayoffAppearances();
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
        return $this->franchiseRecords->getMostTitlesByType($titlePattern);
    }

    /**
     * @see RecordHoldersRepositoryInterface::getLastAnnouncedDate()
     */
    public function getLastAnnouncedDate(): ?string
    {
        return $this->announcements->getLastAnnouncedDate();
    }

    /**
     * @see RecordHoldersRepositoryInterface::markAnnouncementsProcessed()
     */
    public function markAnnouncementsProcessed(string $gameDate): void
    {
        $this->announcements->markAnnouncementsProcessed($gameDate);
    }

    /**
     * @see RecordHoldersRepositoryInterface::getUnannouncedGameDates()
     *
     * @return list<string>
     */
    public function getUnannouncedGameDates(?string $lastAnnouncedDate): array
    {
        return $this->announcements->getUnannouncedGameDates($lastAnnouncedDate);
    }
}
