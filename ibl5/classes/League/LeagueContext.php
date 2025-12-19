<?php

declare(strict_types=1);

namespace League;

/**
 * LeagueContext - Multi-league support for IBL5
 * 
 * Handles league selection, table name mapping, module availability,
 * and league-specific configuration for IBL and Olympics leagues.
 */
class LeagueContext
{
    const LEAGUE_IBL = 'ibl';
    const LEAGUE_OLYMPICS = 'olympics';
    const COOKIE_NAME = 'ibl_league';

    /**
     * Get the current active league
     * 
     * Priority order:
     * 1. URL parameter ($_GET['league'])
     * 2. Session variable ($_SESSION['current_league'])
     * 3. Cookie (ibl_league)
     * 4. Default to 'ibl'
     * 
     * @return string The current league identifier ('ibl' or 'olympics')
     */
    public function getCurrentLeague(): string
    {
        // Check URL override first
        if (isset($_GET['league']) && $this->isValidLeague($_GET['league'])) {
            return $_GET['league'];
        }

        // Check session
        if (isset($_SESSION['current_league']) && $this->isValidLeague($_SESSION['current_league'])) {
            return $_SESSION['current_league'];
        }

        // Check cookie
        if (isset($_COOKIE[self::COOKIE_NAME]) && $this->isValidLeague($_COOKIE[self::COOKIE_NAME])) {
            return $_COOKIE[self::COOKIE_NAME];
        }

        // Default to IBL
        return self::LEAGUE_IBL;
    }

    /**
     * Set the active league
     * 
     * Sets both session variable and cookie with 30-day expiry
     * 
     * @param string $league League identifier ('ibl' or 'olympics')
     * @return void
     * @throws \InvalidArgumentException If league is not valid
     */
    public function setLeague(string $league): void
    {
        if (!$this->isValidLeague($league)) {
            throw new \InvalidArgumentException("Invalid league: {$league}");
        }

        // Set session
        $_SESSION['current_league'] = $league;

        // Set cookie with 30-day expiry (skip in CLI/test mode to avoid header errors)
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            $expiry = time() + (30 * 24 * 60 * 60);
            setcookie(self::COOKIE_NAME, $league, $expiry, '/');
        }
    }

    /**
     * Get the actual table name for the current league
     * 
     * For Olympics league, maps certain tables to Olympics-specific tables.
     * For IBL league, returns the original table name.
     * Shared tables return unchanged for both leagues.
     * 
     * @param string $baseTable Base table name
     * @return string Actual table name for the current league
     */
    public function getTableName(string $baseTable): string
    {
        $currentLeague = $this->getCurrentLeague();

        // For IBL league, always return original table name
        if ($currentLeague === self::LEAGUE_IBL) {
            return $baseTable;
        }

        // For Olympics league, map specific tables
        if ($currentLeague === self::LEAGUE_OLYMPICS) {
            return match ($baseTable) {
                'ibl_team_info' => 'ibl_olympics_team_info',
                'ibl_standings' => 'ibl_olympics_standings',
                'ibl_schedule' => 'ibl_olympics_schedule',
                'ibl_box_scores' => 'ibl_olympics_box_scores',
                'ibl_box_scores_teams' => 'ibl_olympics_box_scores_teams',
                // Shared tables remain unchanged
                'ibl_plr', 'ibl_hist', 'nuke_users', 'nuke_authors' => $baseTable,
                // All other tables remain unchanged
                default => $baseTable
            };
        }

        return $baseTable;
    }

    /**
     * Check if a module is enabled for the current league
     * 
     * Some modules are IBL-only and disabled for Olympics
     * 
     * @param string $moduleName Module name to check
     * @return bool True if module is enabled, false otherwise
     */
    public function isModuleEnabled(string $moduleName): bool
    {
        $currentLeague = $this->getCurrentLeague();

        // All modules enabled for IBL
        if ($currentLeague === self::LEAGUE_IBL) {
            return true;
        }

        // For Olympics, disable certain IBL-only modules
        if ($currentLeague === self::LEAGUE_OLYMPICS) {
            $iblOnlyModules = [
                'Draft',
                'Draft_Pick_Locator',
                'Free_Agency',
                'Waivers',
                'Trading',
                'Voting',
                'Voting_Results',
                'Cap_Info',
                'Franchise_History',
                'Power_Rankings'
            ];

            return !in_array($moduleName, $iblOnlyModules, true);
        }

        return true;
    }

    /**
     * Get configuration for the current league
     * 
     * @return array Associative array with title, short_name, primary_color, logo_path
     */
    public function getConfig(): array
    {
        $currentLeague = $this->getCurrentLeague();

        return match ($currentLeague) {
            self::LEAGUE_IBL => [
                'title' => 'Internet Basketball League',
                'short_name' => 'IBL',
                'primary_color' => '#1a365d',
                'logo_path' => 'images/ibl/logo.png'
            ],
            self::LEAGUE_OLYMPICS => [
                'title' => 'IBL Olympics',
                'short_name' => 'Olympics',
                'primary_color' => '#c53030',
                'logo_path' => 'images/olympics/logo.png'
            ],
            default => [
                'title' => 'Internet Basketball League',
                'short_name' => 'IBL',
                'primary_color' => '#1a365d',
                'logo_path' => 'images/ibl/logo.png'
            ]
        };
    }

    /**
     * Validate if a league identifier is valid
     * 
     * @param string $league League identifier to validate
     * @return bool True if valid, false otherwise
     */
    private function isValidLeague(string $league): bool
    {
        return $league === self::LEAGUE_IBL || $league === self::LEAGUE_OLYMPICS;
    }
}
