<?php

declare(strict_types=1);

namespace League;

/**
 * LeagueContext - Multi-league support for IBL5
 * 
 * Handles league selection, module availability, and league-specific
 * configuration for IBL and Olympics leagues.
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
        $getLeague = $_GET['league'] ?? null;
        if (is_string($getLeague) && $this->isValidLeague($getLeague)) {
            return $getLeague;
        }

        // Check session
        $sessionLeague = $_SESSION['current_league'] ?? null;
        if (is_string($sessionLeague) && $this->isValidLeague($sessionLeague)) {
            return $sessionLeague;
        }

        // Check cookie
        $cookieLeague = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (is_string($cookieLeague) && $this->isValidLeague($cookieLeague)) {
            return $cookieLeague;
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
        // SECURITY: Use secure cookie options
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            $expiry = time() + (30 * 24 * 60 * 60);
            $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            setcookie(self::COOKIE_NAME, $league, [
                'expires' => $expiry,
                'path' => '/',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',  // Lax for league switching via links
            ]);
        }
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
                'DraftPickLocator',
                'FreeAgency',
                'Waivers',
                'Trading',
                'Voting',
                'VotingResults',
                'CapSpace',
                'FranchiseHistory'
            ];

            return !in_array($moduleName, $iblOnlyModules, true);
        }

        return true;
    }

    /**
     * Get configuration for the current league
     *
     * @return array<string, string> Associative array with title, short_name, primary_color, logo_path
     */
    public function getConfig(): array
    {
        $currentLeague = $this->getCurrentLeague();

        return match ($currentLeague) {
            self::LEAGUE_IBL => [
                'title' => 'Internet Basketball League',
                'short_name' => 'IBL',
                'primary_color' => '#1a365d',
                'logo_path' => 'images/ibl/logo.png',
                'images_path' => 'images/'
            ],
            self::LEAGUE_OLYMPICS => [
                'title' => 'IBL Olympics',
                'short_name' => 'Olympics',
                'primary_color' => '#c53030',
                'logo_path' => 'images/olympics/logo.png',
                'images_path' => 'images/olympics/'
            ],
            default => [
                'title' => 'Internet Basketball League',
                'short_name' => 'IBL',
                'primary_color' => '#1a365d',
                'logo_path' => 'images/ibl/logo.png',
                'images_path' => 'images/'
            ]
        };
    }

    /**
     * Check if the current league is Olympics
     */
    public function isOlympics(): bool
    {
        return $this->getCurrentLeague() === self::LEAGUE_OLYMPICS;
    }

    /**
     * Resolve table name based on current league context
     *
     * For IBL context, returns the input table name unchanged.
     * For Olympics context, maps IBL table names to their Olympics equivalents.
     * Unmapped table names are returned unchanged.
     */
    public function getTableName(string $iblTableName): string
    {
        if (!$this->isOlympics()) {
            return $iblTableName;
        }

        return match ($iblTableName) {
            'ibl_box_scores' => 'ibl_olympics_box_scores',
            'ibl_box_scores_teams' => 'ibl_olympics_box_scores_teams',
            'ibl_schedule' => 'ibl_olympics_schedule',
            'ibl_standings' => 'ibl_olympics_standings',
            'ibl_power' => 'ibl_olympics_power',
            'ibl_team_info' => 'ibl_olympics_team_info',
            default => $iblTableName,
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
