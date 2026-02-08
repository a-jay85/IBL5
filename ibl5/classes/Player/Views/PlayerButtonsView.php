<?php

declare(strict_types=1);

namespace Player\Views;

/**
 * PlayerButtonsView - Renders action buttons for player pages
 * 
 * @since 2026-01-08
 */
class PlayerButtonsView
{
    /**
     * Render "Rookie Option Used" message
     * 
     * @return string HTML for rookie option used message
     */
    public static function renderRookieOptionUsedMessage(): string
    {
        ob_start();
        ?>
<table class="player-button rookie-option-used">
    <tr>
        <td>ROOKIE OPTION<br>USED; RENEGOTIATION<br>IMPOSSIBLE</td>
    </tr>
</table>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render renegotiation button
     * 
     * @param int $playerID The player's ID
     * @return string HTML for renegotiation button
     */
    public static function renderRenegotiationButton(int $playerID): string
    {
        ob_start();
        ?>
<table class="player-button renegotiation-button">
    <tr>
        <td><a href="modules.php?name=Player&pa=negotiate&pid=<?= $playerID ?>">RENEGOTIATE<BR>CONTRACT</a></td>
    </tr>
</table>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render rookie option button
     * 
     * @param int $playerID The player's ID
     * @return string HTML for rookie option button
     */
    public static function renderRookieOptionButton(int $playerID): string
    {
        ob_start();
        ?>
<table class="player-button rookie-option-button">
    <tr>
        <td><a href="modules.php?name=Player&pa=rookieoption&pid=<?= $playerID ?>&from=player">ROOKIE<BR>OPTION</a></td>
    </tr>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
