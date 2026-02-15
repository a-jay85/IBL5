<?php

declare(strict_types=1);

namespace Season\Contracts;

/**
 * Interface for Season query repository
 *
 * Provides database query methods for season settings, sim dates,
 * box scores, and phase-specific calculations.
 */
interface SeasonQueryRepositoryInterface
{
    /**
     * Bulk-fetch multiple settings in a single query
     *
     * @param list<string> $names Setting names to fetch
     * @return array<string, string> Map of setting name => value
     */
    public function getBulkSettings(array $names): array;

    /**
     * Get current season phase
     *
     * @return string Current season phase (e.g., 'Regular Season', 'Playoffs', 'Free Agency')
     */
    public function getSeasonPhase(): string;

    /**
     * Get season ending year
     *
     * @return string Season ending year (e.g., '2024')
     */
    public function getSeasonEndingYear(): string;

    /**
     * Get first box score date
     *
     * @return string First box score date from database
     */
    public function getFirstBoxScoreDate(): string;

    /**
     * Get last box score date
     *
     * @return string Last box score date from database
     */
    public function getLastBoxScoreDate(): string;

    /**
     * Get last sim dates array
     *
     * @return array{Sim: int, 'Start Date': string, 'End Date': string}
     */
    public function getLastSimDatesArray(): array;

    /**
     * Set last sim dates array
     *
     * @param string $newSimNumber New sim number
     * @param string $newSimStartDate New sim start date (YYYY-MM-DD format)
     * @param string $newSimEndDate New sim end date (YYYY-MM-DD format)
     * @return int Number of affected rows
     */
    public function setLastSimDatesArray(string $newSimNumber, string $newSimStartDate, string $newSimEndDate): int;

    /**
     * Get the last regular season game date from the schedule
     *
     * @param int $endingYear Season ending year (used to calculate playoffs start)
     * @return string|null Last RS game date (YYYY-MM-DD), or null if no schedule data
     */
    public function getLastRegularSeasonGameDate(int $endingYear): ?string;

    /**
     * Get allow trades status
     *
     * @return string Status of allowing trades ('Yes' or 'No')
     */
    public function getAllowTradesStatus(): string;

    /**
     * Get allow waivers status
     *
     * @return string Status of allowing waivers ('Yes' or 'No')
     */
    public function getAllowWaiversStatus(): string;

    /**
     * Get free agency notifications state
     *
     * @return string State of free agency notifications ('On' or 'Off')
     */
    public function getFreeAgencyNotificationsState(): string;

    /**
     * Calculate phase-specific sim number for any sim/phase/season combination
     *
     * @param int $overallSimNumber The overall sim number to calculate for
     * @param string $phase The season phase
     * @param int $seasonYear The season ending year
     * @return int Phase-specific sim number (falls back to overall if 0)
     */
    public function calculatePhaseSimNumber(int $overallSimNumber, string $phase, int $seasonYear): int;
}
