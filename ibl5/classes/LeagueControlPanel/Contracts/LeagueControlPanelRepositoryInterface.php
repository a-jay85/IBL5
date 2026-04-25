<?php

declare(strict_types=1);

namespace LeagueControlPanel\Contracts;

interface LeagueControlPanelRepositoryInterface
{
    /**
     * Get a single setting value by name
     *
     * @param string $name Setting name (e.g., 'Current Season Phase')
     * @return string|null Setting value, or null if not found
     */
    public function getSetting(string $name): ?string;

    /**
     * Get multiple settings in a single query
     *
     * @param list<string> $names Setting names to fetch
     * @return array<string, string> Map of setting name => value
     */
    public function getBulkSettings(array $names): array;

    /**
     * Get the sim length in days
     *
     * @return int Sim length in days
     */
    public function getSimLengthInDays(): int;

    /**
     * Update a single setting value
     *
     * @param string $name Setting name
     * @param string $value New value
     * @return bool True on success
     */
    public function updateSetting(string $name, string $value): bool;

    /**
     * Set the current season phase
     *
     * When phase is Preseason or HEAT, also resets draft link to Off
     * and deactivates the Draft module.
     *
     * @param string $phase Phase value (Preseason, HEAT, Regular Season, Playoffs, Draft, Free Agency)
     * @return bool True on success
     */
    public function setSeasonPhase(string $phase): bool;

    /**
     * Set the sim length in days
     *
     * @param int $days Number of days
     * @return bool True on success
     */
    public function setSimLengthInDays(int $days): bool;

    /**
     * Set the Show Draft Link setting and sync the Draft module active flag
     *
     * @param string $value 'On' or 'Off'
     * @return bool True on success
     */
    public function setShowDraftLink(string $value): bool;

    /**
     * Reset All-Star Game voting
     *
     * Clears all ASG votes, sets ASG Voting to 'Yes', and resets team asg_vote flags.
     *
     * @return bool True on success
     */
    public function resetAllStarVoting(): bool;

    /**
     * Reset End of Year voting
     *
     * Clears all EOY votes, sets EOY Voting to 'Yes', and resets team eoy_vote flags.
     *
     * @return bool True on success
     */
    public function resetEndOfYearVoting(): bool;

    /**
     * Move all waived players to Free Agents and reset their Bird years
     *
     * @return bool True on success
     */
    public function setWaiversToFreeAgents(): bool;

    /**
     * Set free agency factors for Play For Winner from standings
     *
     * @return bool True on success
     */
    public function setFreeAgencyFactorsForPfw(): bool;

    /**
     * Set the Allow Trades setting
     *
     * @param string $value 'Yes' or 'No'
     * @return bool True on success
     */
    public function setAllowTrades(string $value): bool;

    /**
     * Set the Allow Waiver Moves setting
     *
     * @param string $value 'Yes' or 'No'
     * @return bool True on success
     */
    public function setAllowWaivers(string $value): bool;

    /**
     * Set the Free Agency Notifications setting
     *
     * @param string $value 'On' or 'Off'
     * @return bool True on success
     */
    public function setFreeAgencyNotifications(string $value): bool;

    /**
     * Activate Trivia Mode (hides Player and Season Leaders modules)
     *
     * @return bool True on success
     */
    public function activateTriviaMode(): bool;

    /**
     * Deactivate Trivia Mode (shows Player and Season Leaders modules)
     *
     * @return bool True on success
     */
    public function deactivateTriviaMode(): bool;

    /**
     * Reset all teams' contract extension flags
     *
     * @return bool True on success
     */
    public function resetAllContractExtensions(): bool;

    /**
     * Reset all teams' MLE and LLE flags
     *
     * @return bool True on success
     */
    public function resetAllMlesAndLles(): bool;

    /**
     * Upsert an award row into ibl_awards.
     *
     * Uses INSERT ... ON DUPLICATE KEY UPDATE on (year, award, name).
     *
     * @param int $year Season ending year
     * @param string $award Award name (e.g., "Most Valuable Player (1st)")
     * @param string $name Player name
     * @return int Affected rows (1=inserted, 2=updated, 0=unchanged)
     */
    public function upsertAward(int $year, string $award, string $name): int;

    /**
     * Upsert a GM award row into ibl_gm_awards.
     *
     * @param int $year Season ending year
     * @param string $name GM username
     * @return int Affected rows (1=inserted, 2=updated, 0=unchanged)
     */
    public function upsertGmAward(int $year, string $name): int;

    /**
     * Check if a Finals MVP award exists for the given year.
     *
     * @param int $year Season ending year
     * @return bool True if a Finals MVP exists
     */
    public function hasFinalsMvp(int $year): bool;

    /**
     * Delete temporary draft player placeholders from ibl_plr.
     *
     * These are rows with pid >= 90000, created during the draft when a player
     * is selected but before plrParser assigns permanent PIDs.
     *
     * @return int Number of deleted rows
     */
    public function deleteDraftPlaceholders(): int;

    /**
     * Delete buyouts and cash considerations whose remaining contract year salaries are all zero.
     *
     * A record is "outdated" when every salary field for future contract years (after the current
     * year) is 0, meaning no money is owed or received in any upcoming season.
     *
     * @return int Number of deleted rows
     */
    public function deleteOutdatedBuyoutsAndCash(): int;
}
