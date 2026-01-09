<?php

declare(strict_types=1);

namespace SeriesRecords\Contracts;

/**
 * SeriesRecordsControllerInterface - Main controller contract for Series Records module
 * 
 * Coordinates between Repository, Service, and View components following
 * the MVC pattern used in other refactored modules.
 * 
 * The controller handles user authentication, orchestrates data retrieval
 * and transformation, and composes the final page output.
 */
interface SeriesRecordsControllerInterface
{
    /**
     * Display the series records page for a specific team context
     * 
     * Orchestrates the complete series records page rendering including:
     * 1. Resolve user's team ID for highlighting
     * 2. Load all teams and series records from repository
     * 3. Transform records into display matrix via service
     * 4. Render page with header, menu, and series grid via view
     * 
     * @param int $userTeamId The team ID associated with the logged-in user (0 if none)
     * @return void Outputs HTML directly
     * 
     * **Behaviors:**
     * - Displays page header and navigation menu
     * - Renders series records grid table with user's team highlighted
     * - Handles case where no teams or records exist gracefully
     */
    public function displaySeriesRecords(int $userTeamId): void;

    /**
     * Handle the main entry point for unauthenticated users
     * 
     * Prompts user to log in if not authenticated.
     * 
     * @return void Outputs HTML directly
     */
    public function displayLoginPrompt(): void;

    /**
     * Handle the main entry point for authenticated users
     * 
     * Retrieves user's team and delegates to displaySeriesRecords.
     * 
     * @param string $username The authenticated user's username
     * @return void Outputs HTML directly
     */
    public function displayForUser(string $username): void;

    /**
     * Main controller entry point for the Series Records module.
     *
     * This method is intended to be called from index.php and is responsible for:
     * - Determining whether the user is authenticated
     * - Delegating to displayLoginPrompt() for unauthenticated users
     * - Delegating to displayForUser() / displaySeriesRecords() for authenticated users
     *
     * @param mixed $user The global $user cookie array
     * @return void Outputs HTML directly
     */
    public function main($user): void;
}
