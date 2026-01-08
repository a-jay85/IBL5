<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Player;
use Utilities\HtmlSanitizer;

/**
 * PlayerRatingsView - Renders player ratings information
 * 
 * Displays player ratings including talent, skill, intangibles, clutch, and consistency.
 */
class PlayerRatingsView
{
    /**
     * Render player misc ratings table
     * 
     * @param Player $player The player object
     * @return string HTML output for the ratings table
     */
    public static function renderMiscRatingsTable(Player $player): string
    {
        ob_start();
        ?>
<table class="misc-ratings-table">
    <tr class="header-row">
        <td>Talent</td>
        <td>Skill</td>
        <td>Intangibles</td>
        <td>Clutch</td>
        <td>Consistency</td>
    </tr>
    <tr>
        <td><?= HtmlSanitizer::safeHtmlOutput((string)$player->ratingTalent) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput((string)$player->ratingSkill) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput((string)$player->ratingIntangibles) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput((string)$player->ratingClutch) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput((string)$player->ratingConsistency) ?></td>
    </tr>
</table>
<p>
        <?php
        return ob_get_clean();
    }

    /**
     * Render free agency preferences table
     * 
     * @param Player $player The player object
     * @return string HTML output for the free agency preferences table
     */
    public static function renderFreeAgencyPreferences(Player $player): string
    {
        ob_start();
        ?>
<table class="misc-ratings-table">
    <tr class="header-row">
        <td>Loyalty</td>
        <td>Play for Winner</td>
        <td>Playing Time</td>
        <td>Security</td>
        <td>Tradition</td>
    </tr>
    <tr>
        <td><?= HtmlSanitizer::safeHtmlOutput((string)$player->freeAgencyLoyalty) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput((string)$player->freeAgencyPlayForWinner) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput((string)$player->freeAgencyPlayingTime) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput((string)$player->freeAgencySecurity) ?></td>
        <td><?= HtmlSanitizer::safeHtmlOutput((string)$player->freeAgencyTradition) ?></td>
    </tr>
</table>
        <?php
        return ob_get_clean();
    }
}
