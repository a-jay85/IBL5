<?php

declare(strict_types=1);

namespace Draft\Contracts;

/**
 * DraftViewInterface - Contract for draft UI rendering
 *
 * Handles all HTML output for draft-related pages including error messages,
 * draft interface, and player tables.
 */
interface DraftViewInterface
{
    /**
     * Render a validation error message for display
     *
     * Formats an error message with user-friendly text and a link to return to the draft.
     * Provides retry instructions based on the error type.
     *
     * @param string $errorMessage The error message to display
     * @return string HTML formatted error message
     *
     * IMPORTANT BEHAVIORS:
     *  - Wraps error message in "Oops, <message>" format
     *  - Appends context-specific retry instructions based on error content:
     *    * If error contains "didn't select": suggests "select a player before hitting the Draft button"
     *    * Otherwise: suggests "try drafting again" if it's your turn
     *  - Includes clickable link to return to Draft module
     *  - Escapes error message for safe HTML output using DatabaseService::safeHtmlOutput
     *  - NEVER throws exceptions
     *
     * Return Value:
     *  - HTML formatted error message with link, ready for direct output
     *  - Safe for echo without further escaping
     *  - Single paragraph with instructions and return link
     *
     * Examples:
     *  $html = $view->renderValidationError("You didn't select a player.");
     *  // Returns: "Oops, You didn't select a player.
     *  //          <p><a href=\"/ibl5/modules.php?name=Draft\">Click here...</a>
     *  //          and please select a player before hitting the Draft button."
     */
    public function renderValidationError(string $errorMessage): string;

    /**
     * Render the draft interface with player list
     *
     * Creates the main draft selection page with a sortable player table,
     * draft form, and submit button (only shown if user owns current pick).
     *
     * @param array<int, array<string, mixed>> $players Array of player records from ibl_draft_class
     * @param string $teamLogo The current user's team name
     * @param string $pickOwner The team that owns the current pick
     * @param int $draftRound The current draft round
     * @param int $draftPick The current draft pick number
     * @param int $seasonYear The draft season year (for header display)
     * @param int $tid The team ID (for logo image lookup)
     * @return string HTML formatted draft interface
     *
     * IMPORTANT BEHAVIORS:
     *  - Displays team logo at top (from images/logo/{tid}.jpg)
     *  - Shows season year and welcome message in table header
     *  - Renders player table via renderPlayerTable (shows draft radio, name, stats for all players)
     *  - Shows Draft button ONLY if:
     *    * Current user owns the current pick ($teamLogo == $pickOwner)
     *    * AND there are undrafted players available
     *  - Draft button disabled on submit to prevent double-click
     *  - Form POSTs to /ibl5/modules/Draft/draft_selection.php
     *  - Includes hidden form fields: teamname, draft_round, draft_pick
     *  - Uses renderPlayerTable() for actual table content
     *  - NEVER throws exceptions
     *
     * Return Value:
     *  - Complete HTML with form, table, and button
     *  - Safe for direct output
     *  - Includes jQuery sortable table functionality (sortable class)
     *
     * Form Structure:
     *  - Hidden field: teamname = $teamLogo
     *  - Hidden field: draft_round = $draftRound
     *  - Hidden field: draft_pick = $draftPick
     *  - Radio buttons: name='player' value="{player_name}" (for available undrafted players)
     *  - Submit button: visible only if user owns pick and players available
     *
     * Examples:
     *  $html = $view->renderDraftInterface($players, 'New York', 'New York', 1, 5, 2026, 7);
     *  // Shows draft interface for New York's turn with team logo 7.jpg
     *  // Includes all players from $players array
     *  // Shows submit button (user owns pick)
     */
    public function renderDraftInterface(array $players, string $teamLogo, string $pickOwner, int $draftRound, int $draftPick, int $seasonYear, int $tid): string;

    /**
     * Render the player table for draft selection
     *
     * Creates a sortable HTML table with all draft class players, their stats, and
     * radio buttons for selection. Drafted players are shown as strikethrough and disabled.
     *
     * @param array<int, array<string, mixed>> $players Array of player records from ibl_draft_class
     * @param string $teamLogo The current user's team name
     * @param string $pickOwner The team that owns the current pick
     * @return string HTML formatted player table
     *
     * IMPORTANT BEHAVIORS:
     *  - Renders complete table with 27 columns (draft/name/pos/team/stats/ratings)
     *  - Alternates row colors: EEEEEE and DDDDDD for visual distinction
     *  - Drafted players (drafted=1) shown as strikethrough disabled text, no radio button
     *  - Undrafted players (drafted=0):
     *    * If current user owns pick: shows radio button + clickable name
     *    * If user doesn't own pick: shows no radio button, locked appearance
     *  - Player names escaped with htmlspecialchars(ENT_QUOTES) for safe output
     *  - Uses DatabaseService::safeHtmlOutput() for all numeric stats and ratings
     *  - Radio button value uses htmlspecialchars to handle apostrophes in names
     *  - Table uses "sortable" class for jQuery sorting functionality
     *  - NEVER throws exceptions
     *
     * Return Value:
     *  - Complete HTML table with tbody rows for each player
     *  - Safe for direct output
     *  - Compatible with jQuery tablesorter or similar sortable plugin
     *
     * Table Columns (27 total):
     *  1. Draft (radio button or empty)
     *  2. Name
     *  3. Pos (position)
     *  4. Team
     *  5. Age
     *  6-11. Stats: fga, fgp, fta, ftp, tga, tgp
     *  12-19. Advanced: orb, drb, ast, stl, tvr, blk
     *  20-27. Ratings: oo, do, po, to, od, dd, pd, td
     *  (Additional columns in code: tal, skl, int)
     *
     * Row Styling:
     *  - Drafted players: bgcolor inherited, strikethrough text (crossed-out), disabled interaction
     *  - Undrafted with pick ownership: bgcolor alternating, clickable radio, selectable name
     *  - Undrafted without ownership: bgcolor alternating, no radio, locked appearance
     *
     * Examples:
     *  $table = $view->renderPlayerTable($players, 'New York', 'New York');
     *  // Returns HTML table with draft radios available for New York's players
     *
     *  $table = $view->renderPlayerTable($players, 'Boston', 'New York');
     *  // Returns HTML table without draft radios (Boston doesn't own pick)
     *  // Drafted players show as strikethrough
     */
    public function renderPlayerTable(array $players, string $teamLogo, string $pickOwner): string;

    /**
     * Get the appropriate retry instructions based on the error message
     *
     * Provides context-specific help text based on the type of validation error.
     * Used by renderValidationError() to append helpful instructions.
     *
     * @param string $errorMessage The error message text
     * @return string Retry instructions appropriate for this error
     *
     * IMPORTANT BEHAVIORS:
     *  - If error contains "didn't select": returns " and please select a player before hitting the Draft button."
     *  - Otherwise: returns " and if it's your turn, try drafting again."
     *  - Returns string with leading space for concatenation with error message
     *  - Uses strpos() for substring matching (case-sensitive)
     *  - NEVER throws exceptions
     *
     * Return Value:
     *  - String starting with space, suitable for concatenation
     *  - Complete sentence fragment ending with period
     *
     * Examples:
     *  $retry = $view->getRetryInstructions("You didn't select a player.");
     *  // Returns: " and please select a player before hitting the Draft button."
     *
     *  $retry = $view->getRetryInstructions("It looks like you've already drafted a player...");
     *  // Returns: " and if it's your turn, try drafting again."
     */
    public function getRetryInstructions(string $errorMessage): string;

    /**
     * Check if there are any undrafted players available
     *
     * Determines whether the draft has any players remaining for selection.
     * Used to show/hide the Draft button in the interface.
     *
     * @param array<int, array<string, mixed>> $players Array of player records
     * @return bool True if at least one player is undrafted (drafted=0), false if all drafted
     *
     * IMPORTANT BEHAVIORS:
     *  - Iterates through all players checking 'drafted' field
     *  - Returns true as soon as first undrafted player found (early exit)
     *  - Returns false if $players is empty or all players drafted
     *  - Compares drafted field with == 0 (loose comparison with integer 0)
     *  - NEVER throws exceptions
     *
     * Examples:
     *  $hasPlayers = $view->hasUndraftedPlayers($players);
     *  // Returns true if any player has drafted == 0
     *  // Returns false if all have drafted == 1 or array is empty
     */
    public function hasUndraftedPlayers(array $players): bool;
}
