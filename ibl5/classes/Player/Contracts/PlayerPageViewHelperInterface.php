<?php

namespace Player\Contracts;

use Player\Player;

/**
 * PlayerPageViewHelperInterface - Contract for player page HTML rendering
 * 
 * Defines the interface for generating HTML elements displayed on the player page.
 * All methods use output buffering pattern for clean, readable code.
 */
interface PlayerPageViewHelperInterface
{
    /**
     * Generate player header HTML section
     * 
     * Renders the top section of the player page with:
     * - Player name and position
     * - Player nickname (if present)
     * - Team link
     * - Player image
     * - Beginning of player details section
     * 
     * Output uses output buffering (ob_start/ob_get_clean) pattern.
     * 
     * @param Player $player The player to display
     * @param int $playerID The player's database ID
     * @return string Complete HTML for player header section
     */
    public function renderPlayerHeader(Player $player, int $playerID): string;

    /**
     * Generate rookie option used message HTML
     * 
     * Renders a prominent red box message indicating that the player's rookie
     * option has been exercised and renegotiation is impossible.
     * Message text: "ROOKIE OPTION\nUSED; RENEGOTIATION\nIMPOSSIBLE"
     * 
     * Output uses output buffering (ob_start/ob_get_clean) pattern.
     * 
     * @return string Complete HTML for rookie option used message
     */
    public function renderRookieOptionUsedMessage(): string;

    /**
     * Generate renegotiation button HTML
     * 
     * Renders a red button that links to the player renegotiation module.
     * Button text: "RENEGOTIATE\nCONTRACT"
     * 
     * Output uses output buffering (ob_start/ob_get_clean) pattern.
     * 
     * @param int $playerID The player's database ID (used in link URL)
     * @return string Complete HTML for renegotiation button
     */
    public function renderRenegotiationButton(int $playerID): string;

    /**
     * Generate rookie option button HTML
     * 
     * Renders an orange button that links to the rookie option module.
     * Button text: "ROOKIE\nOPTION"
     * 
     * Output uses output buffering (ob_start/ob_get_clean) pattern.
     * 
     * @param int $playerID The player's database ID (used in link URL)
     * @return string Complete HTML for rookie option button
     */
    public function renderRookieOptionButton(int $playerID): string;
}
