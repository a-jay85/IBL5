<?php

declare(strict_types=1);

namespace Player\Views;

/**
 * PlayerMenuView - Renders player page navigation menu
 * 
 * @since 2026-01-08
 */
class PlayerMenuView
{
    /**
     * Render player menu navigation
     * 
     * @param int $playerID The player's ID
     * @return string HTML for player menu
     */
    public static function render(int $playerID): string
    {
        ob_start();
        ?>
<tr>
    <td colspan="2"><hr></td>
</tr>
<tr>
    <td colspan="2" class="player-menu-container">
        <div class="player-menu-title">PLAYER MENU</div>
        <div class="player-menu-links">
            <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::OVERVIEW) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::OVERVIEW) ?></a> | 
            <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::AWARDS_AND_NEWS) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::AWARDS_AND_NEWS) ?></a><br>
            <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::ONE_ON_ONE) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::ONE_ON_ONE) ?></a> | 
            <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::SIM_STATS) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::SIM_STATS) ?></a><br>
            <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::REGULAR_SEASON_TOTALS) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::REGULAR_SEASON_TOTALS) ?></a> | 
            <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::REGULAR_SEASON_AVERAGES) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::REGULAR_SEASON_AVERAGES) ?></a><br>
            <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::PLAYOFF_TOTALS) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::PLAYOFF_TOTALS) ?></a> | 
            <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::PLAYOFF_AVERAGES) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::PLAYOFF_AVERAGES) ?></a><br>
            <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::HEAT_TOTALS) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::HEAT_TOTALS) ?></a> | 
            <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::HEAT_AVERAGES) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::HEAT_AVERAGES) ?></a><br>
            <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::OLYMPIC_TOTALS) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::OLYMPIC_TOTALS) ?></a> | 
            <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::OLYMPIC_AVERAGES) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::OLYMPIC_AVERAGES) ?></a><br>
            <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::RATINGS_AND_SALARY) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::RATINGS_AND_SALARY) ?></a>
        </div>
    </td>
</tr>
<tr>
    <td colspan="3"><hr></td>
</tr>
        <?php
        return ob_get_clean();
    }
}
