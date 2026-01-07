<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Contracts\PlayerPageViewInterface;
use Player\Player;
use Player\PlayerStats;
use Player\PlayerPageViewHelper;
use Utilities\HtmlSanitizer;

/**
 * PlayerRatingsAndSalaryView - Renders player ratings and salary information
 * 
 * @see PlayerPageViewInterface
 */
class PlayerRatingsAndSalaryView implements PlayerPageViewInterface
{
    private Player $player;
    private PlayerStats $playerStats;
    private PlayerPageViewHelper $viewHelper;

    public function __construct(
        Player $player,
        PlayerStats $playerStats,
        PlayerPageViewHelper $viewHelper
    ) {
        $this->player = $player;
        $this->playerStats = $playerStats;
        $this->viewHelper = $viewHelper;
    }

    /**
     * @see PlayerPageViewInterface::render
     */
    public function render(): string
    {
        $h = HtmlSanitizer::class;
        
        ob_start();
        ?>
<table border=1 cellspacing=0 style='margin: 0 auto;'>
    <tr>
        <td colspan=2 style='font-weight:bold; text-align:center; background-color:#00c; color:#fff;'>Ratings & Salary</td>
    </tr>
    <tr>
        <td style="padding: 10px;">
            <?= $this->viewHelper->renderRatingsTable($this->player, true) ?>
        </td>
        <td style="padding: 10px; vertical-align: top;">
            <?= $this->renderSalaryInfo() ?>
        </td>
    </tr>
</table>
        <?php
        return ob_get_clean();
    }

    /**
     * Render salary information table
     */
    private function renderSalaryInfo(): string
    {
        $h = HtmlSanitizer::class;
        $player = $this->player;
        
        ob_start();
        ?>
<table border=1 cellspacing=0>
    <tr>
        <td colspan=2 style='font-weight:bold; text-align:center; background-color:#666; color:#fff;'>Contract</td>
    </tr>
    <tr>
        <td>Current Salary</td>
        <td><center>$<?= $h::safeHtmlOutput(number_format($player->salary)) ?></center></td>
    </tr>
    <tr>
        <td>Years Remaining</td>
        <td><center><?= $h::safeHtmlOutput($player->yearsRemaining) ?></center></td>
    </tr>
    <tr>
        <td>Player Option</td>
        <td><center><?= $player->playerOption ? 'Yes' : 'No' ?></center></td>
    </tr>
    <tr>
        <td>Team Option</td>
        <td><center><?= $player->teamOption ? 'Yes' : 'No' ?></center></td>
    </tr>
    <tr>
        <td>Bird Rights</td>
        <td><center><?= $h::safeHtmlOutput($player->getBirdRightsDescription()) ?></center></td>
    </tr>
    <tr>
        <td>MLE/BAE</td>
        <td><center><?= $player->isMleOrBae ? 'Yes' : 'No' ?></center></td>
    </tr>
</table>
        <?php
        return ob_get_clean();
    }
}
