<?php

declare(strict_types=1);

namespace Draft\Contracts;

/**
 * DraftSelectionHandlerInterface - Contract for draft selection workflow orchestration
 *
 * Coordinates the entire draft selection process including validation,
 * database updates, and Discord notifications.
 */
interface DraftSelectionHandlerInterface
{
    /**
     * Handle a draft selection submission
     *
     * Orchestrates the complete draft selection workflow:
     * 1. Validates the selection (player selected, pick available, player not drafted)
     * 2. Updates three database tables (ibl_draft, ibl_draft_class, ibl_plr)
     * 3. Sends Discord notifications (#general-chat and #draft-picks)
     * 4. Returns HTML response message
     *
     * @param string $teamName The name of the drafting team
     * @param string|null $playerName The name of the player to draft (null = not selected)
     * @param int $draftRound The draft round
     * @param int $draftPick The pick number
     * @return string HTML response message (either success or error)
     *
     * IMPORTANT BEHAVIORS:
     *  - Performs three validations before processing:
     *    * Player name is not null/empty
     *    * Pick not already filled (no current selection)
     *    * Player not already drafted by another team
     *  - If validation fails: returns HTML error message with retry instructions
     *  - If validation succeeds: calls processDraftSelection() to update databases and notify
     *  - Returns HTML from processDraftSelection() or validation error
     *  - NEVER throws exceptions – returns error message HTML instead
     *
     * Side Effects:
     *  - May update three database tables if validation passes (ibl_draft, ibl_draft_class, ibl_plr)
     *  - May post to Discord channels (#general-chat, #draft-picks)
     *  - Calls DraftRepository methods for database operations
     *  - Calls DraftValidator for validation
     *  - Calls DraftView for error message rendering
     *
     * Return Value:
     *  - String with HTML formatted response message
     *  - On validation failure: error message with retry instructions and back link
     *  - On success: draft announcement with next team info and back link
     *  - On database error: generic error message and back link
     *  - Safe for direct echo to user
     *
     * Database Dependencies:
     *  - Team must exist in ibl_team_info (for tid lookup)
     *  - Player must exist in ibl_draft_class (for rating mapping)
     *  - Draft pick must exist in ibl_draft (for round/pick lookup)
     *
     * Examples:
     *  $html = $handler->handleDraftSelection('New York', 'John Smith', 1, 5);
     *  // Returns success message with announcement and next team info
     *  // Updates ibl_draft, ibl_draft_class, ibl_plr
     *  // Posts to #general-chat and #draft-picks on Discord
     *
     *  $html = $handler->handleDraftSelection('New York', null, 1, 5);
     *  // Returns error message "You didn't select a player."
     *  // No database updates
     *  // No Discord notifications
     */
    public function handleDraftSelection(string $teamName, ?string $playerName, int $draftRound, int $draftPick): string;
}
