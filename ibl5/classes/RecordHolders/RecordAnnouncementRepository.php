<?php

declare(strict_types=1);

namespace RecordHolders;

use League\LeagueContext;

/**
 * Manages announcement pipeline state: last-processed date and unannounced game discovery.
 */
final class RecordAnnouncementRepository extends \BaseMysqliRepository
{
    private const ANNOUNCEMENT_CACHE_KEY = 'record_announcements_last_date';

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * Retrieve the last date for which record announcements were processed.
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
     * Mark announcements as processed up to the given game date.
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
     * Return all game dates in the latest sim's range that have not yet been announced.
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
