<?php

declare(strict_types=1);

namespace Season;

use Season\Contracts\SeasonQueryRepositoryInterface;

/**
 * SeasonQueryRepository - Database queries for season settings, sim dates, and phase calculations
 *
 * Extracted from Season.php to separate database queries from entity/business logic.
 *
 * @see SeasonQueryRepositoryInterface
 */
class SeasonQueryRepository extends \BaseMysqliRepository implements SeasonQueryRepositoryInterface
{
    /**
     * Bulk-fetch multiple settings in a single query
     *
     * @param list<string> $names Setting names to fetch
     * @return array<string, string> Map of setting name => value
     */
    public function getBulkSettings(array $names): array
    {
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $types = str_repeat('s', count($names));

        /** @var list<array{name: string, value: string}> $rows */
        $rows = $this->fetchAll(
            "SELECT name, value FROM ibl_settings WHERE name IN ({$placeholders})",
            $types,
            ...$names
        );

        /** @var array<string, string> $map */
        $map = [];
        foreach ($rows as $row) {
            $map[$row['name']] = $row['value'];
        }

        return $map;
    }

    /**
     * Get current season phase
     *
     * @return string Current season phase (e.g., 'Regular Season', 'Playoffs', 'Free Agency')
     */
    public function getSeasonPhase(): string
    {
        /** @var array{value: string}|null $result */
        $result = $this->fetchOne(
            "SELECT value FROM ibl_settings WHERE name = ? LIMIT 1",
            "s",
            "Current Season Phase"
        );

        return $result['value'] ?? '';
    }

    /**
     * Get season ending year
     *
     * @return string Season ending year (e.g., '2024')
     */
    public function getSeasonEndingYear(): string
    {
        /** @var array{value: string}|null $result */
        $result = $this->fetchOne(
            "SELECT value FROM ibl_settings WHERE name = ? LIMIT 1",
            "s",
            "Current Season Ending Year"
        );

        return $result['value'] ?? '';
    }

    /**
     * Get first box score date
     *
     * @return string First box score date from database
     */
    public function getFirstBoxScoreDate(): string
    {
        /** @var array{Date: string}|null $result */
        $result = $this->fetchOne(
            "SELECT Date FROM ibl_box_scores ORDER BY Date ASC LIMIT 1"
        );

        return $result['Date'] ?? '';
    }

    /**
     * Get last box score date
     *
     * @return string Last box score date from database
     */
    public function getLastBoxScoreDate(): string
    {
        /** @var array{Date: string}|null $result */
        $result = $this->fetchOne(
            "SELECT Date FROM ibl_box_scores ORDER BY Date DESC LIMIT 1"
        );

        return $result['Date'] ?? '';
    }

    /**
     * Get last sim dates array
     *
     * Returns the most recent simulation date range from ibl_sim_dates.
     * Note: 'Start Date' and 'End Date' columns are DATE type in schema.
     *
     * @return array{Sim: int, 'Start Date': string, 'End Date': string}
     */
    public function getLastSimDatesArray(): array
    {
        /** @var array{Sim: int, 'Start Date': string, 'End Date': string}|null $result */
        $result = $this->fetchOne(
            "SELECT * FROM ibl_sim_dates ORDER BY sim DESC LIMIT 1"
        );

        return $result ?? ['Sim' => 0, 'Start Date' => '', 'End Date' => ''];
    }

    /**
     * Set last sim dates array
     *
     * Inserts a new simulation date range into ibl_sim_dates.
     * Note: 'Start Date' and 'End Date' columns are DATE type in schema.
     *
     * @param string $newSimNumber New sim number
     * @param string $newSimStartDate New sim start date (YYYY-MM-DD format)
     * @param string $newSimEndDate New sim end date (YYYY-MM-DD format)
     * @return int Number of affected rows
     */
    public function setLastSimDatesArray(string $newSimNumber, string $newSimStartDate, string $newSimEndDate): int
    {
        return $this->execute(
            "INSERT INTO ibl_sim_dates (`Sim`, `Start Date`, `End Date`) VALUES (?, ?, ?)",
            "sss",
            $newSimNumber,
            $newSimStartDate,
            $newSimEndDate
        );
    }

    /**
     * Get the last regular season game date from the schedule
     *
     * Fetches MAX(Date) from ibl_schedule before the playoffs start date.
     * Used to detect the RS-to-Playoffs gap for sim date projections.
     *
     * @param int $endingYear Season ending year (used to calculate playoffs start)
     * @return string|null Last RS game date (YYYY-MM-DD), or null if no schedule data
     */
    public function getLastRegularSeasonGameDate(int $endingYear): ?string
    {
        $playoffsStart = sprintf('%d-%02d-01', $endingYear, \Season::IBL_PLAYOFF_MONTH);

        /** @var array{max_date: string|null}|null $result */
        $result = $this->fetchOne(
            "SELECT MAX(Date) AS max_date FROM ibl_schedule WHERE Date < ?",
            "s",
            $playoffsStart
        );

        return ($result !== null && $result['max_date'] !== null) ? $result['max_date'] : null;
    }

    /**
     * Get allow trades status
     *
     * @return string Status of allowing trades ('Yes' or 'No')
     */
    public function getAllowTradesStatus(): string
    {
        /** @var array{value: string}|null $result */
        $result = $this->fetchOne(
            "SELECT value FROM ibl_settings WHERE name = ? LIMIT 1",
            "s",
            "Allow Trades"
        );

        return $result['value'] ?? '';
    }

    /**
     * Get allow waivers status
     *
     * @return string Status of allowing waivers ('Yes' or 'No')
     */
    public function getAllowWaiversStatus(): string
    {
        /** @var array{value: string}|null $result */
        $result = $this->fetchOne(
            "SELECT value FROM ibl_settings WHERE name = ? LIMIT 1",
            "s",
            "Allow Waiver Moves"
        );

        return $result['value'] ?? '';
    }

    /**
     * Get free agency notifications state
     *
     * @return string State of free agency notifications ('On' or 'Off')
     */
    public function getFreeAgencyNotificationsState(): string
    {
        /** @var array{value: string}|null $result */
        $result = $this->fetchOne(
            "SELECT value FROM ibl_settings WHERE name = ? LIMIT 1",
            "s",
            "Free Agency Notifications"
        );

        return $result['value'] ?? '';
    }

    /**
     * Calculate phase-specific sim number for any sim/phase/season combination
     *
     * Counts sims within the phase's date range up to the given overall sim number.
     * Uses `End Date` (not `Start Date`) because the first sim of a phase can have
     * a Start Date in the prior phase's month.
     *
     * @param int $overallSimNumber The overall sim number to calculate for
     * @param string $phase The season phase
     * @param int $seasonYear The season ending year
     * @return int Phase-specific sim number (falls back to overall if 0)
     */
    public function calculatePhaseSimNumber(int $overallSimNumber, string $phase, int $seasonYear): int
    {
        $beginningYear = $seasonYear - 1;

        switch ($phase) {
            case 'Preseason':
                $phaseStartDate = sprintf('%d-%02d-01', \Season::IBL_PRESEASON_YEAR, \Season::IBL_REGULAR_SEASON_STARTING_MONTH);
                $phaseEndDate = sprintf('%d-%02d-30', \Season::IBL_PRESEASON_YEAR + 1, \Season::IBL_REGULAR_SEASON_ENDING_MONTH);
                break;
            case 'HEAT':
                $phaseStartDate = sprintf('%d-%02d-01', $beginningYear, \Season::IBL_HEAT_MONTH);
                $phaseEndDate = sprintf('%d-%02d-30', $beginningYear, \Season::IBL_HEAT_MONTH);
                break;
            case 'Playoffs':
                $phaseStartDate = sprintf('%d-%02d-01', $seasonYear, \Season::IBL_PLAYOFF_MONTH);
                $phaseEndDate = sprintf('%d-%02d-30', $seasonYear, \Season::IBL_PLAYOFF_MONTH);
                break;
            default: // Regular Season (and fallback for other phases)
                $phaseStartDate = sprintf('%d-%02d-01', $beginningYear, \Season::IBL_REGULAR_SEASON_STARTING_MONTH);
                $phaseEndDate = sprintf('%d-%02d-30', $seasonYear, \Season::IBL_REGULAR_SEASON_ENDING_MONTH);
                break;
        }

        /** @var array{cnt: int}|null $result */
        $result = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM ibl_sim_dates WHERE `End Date` BETWEEN ? AND ? AND Sim <= ?",
            "ssi",
            $phaseStartDate,
            $phaseEndDate,
            $overallSimNumber
        );

        $phaseSimNumber = $result['cnt'] ?? 0;

        // Fallback to overall sim number for non-game phases (Draft, Free Agency, etc.)
        return $phaseSimNumber > 0 ? $phaseSimNumber : $overallSimNumber;
    }
}
