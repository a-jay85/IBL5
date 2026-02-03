<?php

declare(strict_types=1);

namespace OneOnOneGame\Contracts;

use OneOnOneGame\OneOnOneGameResult;

/**
 * OneOnOneGameViewInterface - Contract for One-on-One game view rendering
 * 
 * Defines the interface for rendering all HTML output for the One-on-One
 * game module, including forms, game results, and replays.
 */
interface OneOnOneGameViewInterface
{
    /**
     * Render the page header and title
     * 
     * Outputs the page title section for the One-on-One module.
     * 
     * @return string HTML for the page header
     */
    public function renderHeader(): string;

    /**
     * Render the player selection form
     * 
     * Generates the form for selecting two players and starting a game.
     * Includes dropdowns for both players and submit button.
     * 
     * @param array<int, array{pid: int, name: string}> $players Available players
     * @param int|null $selectedPlayer1 Currently selected player 1 ID
     * @param int|null $selectedPlayer2 Currently selected player 2 ID
     * @return string HTML for the selection form
     */
    public function renderPlayerSelectionForm(array $players, ?int $selectedPlayer1, ?int $selectedPlayer2): string;

    /**
     * Render the game ID lookup form
     * 
     * Generates the form for looking up a previously played game by ID.
     * 
     * @return string HTML for the lookup form
     */
    public function renderGameLookupForm(): string;

    /**
     * Render validation errors
     * 
     * Displays any validation errors for player selection.
     * 
     * @param array<string> $errors Array of error messages
     * @return string HTML for error display
     */
    public function renderErrors(array $errors): string;

    /**
     * Render a completed game result
     * 
     * Displays the full play-by-play, final score, statistics table,
     * and game ID for a just-completed game.
     * 
     * @param OneOnOneGameResult $result The game result to display
     * @param int $gameId The saved game ID
     * @return string HTML for the game result display
     */
    public function renderGameResult(OneOnOneGameResult $result, int $gameId): string;

    /**
     * Render a game replay
     * 
     * Displays a previously played game retrieved from the database.
     * 
     * @param array<string, mixed> $gameData The stored game data
     * @return string HTML for the replay display
     */
    public function renderGameReplay(array $gameData): string;
}
