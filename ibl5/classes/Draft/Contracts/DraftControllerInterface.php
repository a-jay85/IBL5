<?php

declare(strict_types=1);

namespace Draft\Contracts;

/**
 * DraftControllerInterface - Contract for the Draft module controller
 *
 * Orchestrates authentication, read-path board display, and write-path
 * draft selection submission.
 */
interface DraftControllerInterface
{
    /**
     * Main entry point — enforces auth gate, then delegates to displayDraftBoard().
     *
     * @param mixed $user The PHP-Nuke $user cookie variable
     */
    public function main(mixed $user): void;

    /**
     * Render the draft board for the given username.
     *
     * @see \Draft\DraftController::displayDraftBoard()
     */
    public function displayDraftBoard(string $username): void;

    /**
     * Narrow and delegate a draft-selection POST submission.
     *
     * @param array<string, mixed> $post
     * @see \Draft\DraftController::submitSelection()
     */
    public function submitSelection(array $post): string;

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
     */
    public function handleDraftSelection(string $teamName, ?string $playerName, int $draftRound, int $draftPick): string;
}
