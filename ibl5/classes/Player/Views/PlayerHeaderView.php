<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Player;
use Player\PlayerImageHelper;
use Utilities\HtmlSanitizer;

/**
 * PlayerHeaderView - Renders player page header with image and title
 * 
 * @since 2026-01-08
 */
class PlayerHeaderView
{
    /**
     * Render player header with image and basic info
     * 
     * @param Player $player The player object
     * @param int $playerID The player's ID
     * @return string HTML for player header
     */
    public static function render(Player $player, int $playerID): string
    {
        ob_start();
        $playerImageUrl = PlayerImageHelper::getImageUrl($playerID);
        
        $position = HtmlSanitizer::safeHtmlOutput($player->position);
        $name = HtmlSanitizer::safeHtmlOutput($player->name);
        $nickname = HtmlSanitizer::safeHtmlOutput($player->nickname);
        $teamName = HtmlSanitizer::safeHtmlOutput($player->teamName);
        ?>
<table class="player-header-table">
    <tr>
        <td class="player-header-cell">
            <h2 class="player-title"><?= $position ?> <?= $name ?>
            <?php if ($player->nickname != NULL): ?>
                - Nickname: "<?= $nickname ?>"
            <?php endif; ?>
                (<a href="modules.php?name=Team&op=team&teamID=<?= $player->teamID ?>"><?= $teamName ?></a>)
            </h2>
            <hr>
            <table>
                <tr>
                    <td><img src="<?= HtmlSanitizer::safeHtmlOutput($playerImageUrl) ?>"></td>
                    <td>
        <?php
        return ob_get_clean();
    }
}
