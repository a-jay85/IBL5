<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Contracts\PlayerPageViewInterface;
use Player\Player;
use Player\PlayerStats;
use Player\PlayerAwardsRepository;
use Utilities\HtmlSanitizer;

/**
 * PlayerAwardsAndNewsView - Renders player awards, news, and All-Star activity
 * 
 * @see PlayerPageViewInterface
 */
class PlayerAwardsAndNewsView implements PlayerPageViewInterface
{
    private Player $player;
    private PlayerStats $playerStats;
    private PlayerAwardsRepository $awardsRepository;

    public function __construct(
        Player $player,
        PlayerStats $playerStats,
        PlayerAwardsRepository $awardsRepository
    ) {
        $this->player = $player;
        $this->playerStats = $playerStats;
        $this->awardsRepository = $awardsRepository;
    }

    /**
     * @see PlayerPageViewInterface::render
     */
    public function render(): string
    {
        $h = HtmlSanitizer::class;
        $playerId = $this->player->playerID;
        
        ob_start();
        ?>
<table border=1 cellspacing=0 style='margin: 0 auto;'>
    <tr>
        <td colspan=2 style='font-weight:bold; text-align:center; background-color:#00c; color:#fff;'>Awards, News & All-Star Activity</td>
    </tr>
    <tr>
        <td style="padding: 10px; vertical-align: top;">
            <?= $this->renderAwardsSection() ?>
        </td>
        <td style="padding: 10px; vertical-align: top;">
            <?= $this->renderNewsSection() ?>
        </td>
    </tr>
    <tr>
        <td colspan=2 style="padding: 10px;">
            <?= $this->renderAllStarSection() ?>
        </td>
    </tr>
</table>
        <?php
        return ob_get_clean();
    }

    /**
     * Render awards section
     */
    private function renderAwardsSection(): string
    {
        $h = HtmlSanitizer::class;
        $awards = $this->awardsRepository->getPlayerAwards($this->player->playerID);
        
        ob_start();
        ?>
<table border=1 cellspacing=0>
    <tr>
        <td colspan=2 style='font-weight:bold; text-align:center; background-color:#666; color:#fff;'>Awards</td>
    </tr>
        <?php
        if (empty($awards)) {
            ?>
    <tr>
        <td colspan=2 style='text-align:center; padding: 10px;'>No awards on record.</td>
    </tr>
            <?php
        } else {
            foreach ($awards as $award) {
                ?>
    <tr>
        <td><?= $h::safeHtmlOutput($award['year']) ?></td>
        <td><?= $h::safeHtmlOutput($award['award']) ?></td>
    </tr>
                <?php
            }
        }
        ?>
</table>
        <?php
        return ob_get_clean();
    }

    /**
     * Render news section
     */
    private function renderNewsSection(): string
    {
        $h = HtmlSanitizer::class;
        $news = $this->awardsRepository->getPlayerNews($this->player->playerID, 10);
        
        ob_start();
        ?>
<table border=1 cellspacing=0>
    <tr>
        <td colspan=2 style='font-weight:bold; text-align:center; background-color:#666; color:#fff;'>Recent News</td>
    </tr>
        <?php
        if (empty($news)) {
            ?>
    <tr>
        <td colspan=2 style='text-align:center; padding: 10px;'>No recent news.</td>
    </tr>
            <?php
        } else {
            foreach ($news as $item) {
                ?>
    <tr>
        <td><?= $h::safeHtmlOutput($item['date']) ?></td>
        <td><?= $h::safeHtmlOutput($item['headline']) ?></td>
    </tr>
                <?php
            }
        }
        ?>
</table>
        <?php
        return ob_get_clean();
    }

    /**
     * Render All-Star activity section
     */
    private function renderAllStarSection(): string
    {
        $h = HtmlSanitizer::class;
        $playerId = $this->player->playerID;
        
        $allStarCount = $this->awardsRepository->countAllStarSelections($playerId);
        $threePointCount = $this->awardsRepository->countThreePointContests($playerId);
        $dunkCount = $this->awardsRepository->countDunkContests($playerId);
        $rscCount = $this->awardsRepository->countRookieSophomoreChallenges($playerId);
        $allStarActivity = $this->awardsRepository->getAllStarActivity($playerId);
        
        ob_start();
        ?>
<table border=1 cellspacing=0 style='width: 100%;'>
    <tr>
        <td colspan=5 style='font-weight:bold; text-align:center; background-color:#666; color:#fff;'>All-Star Weekend Activity</td>
    </tr>
    <tr>
        <th>All-Star Games</th>
        <th>3-Point Contests</th>
        <th>Dunk Contests</th>
        <th>Rookie/Sophomore Games</th>
    </tr>
    <tr>
        <td><center><?= $h::safeHtmlOutput($allStarCount) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($threePointCount) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($dunkCount) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($rscCount) ?></center></td>
    </tr>
        <?php
        if (!empty($allStarActivity)) {
            ?>
    <tr>
        <td colspan=5 style='font-weight:bold; text-align:center;'>Activity Details</td>
    </tr>
    <tr>
        <th>Year</th>
        <th>Event</th>
        <th>Team/Type</th>
        <th colspan=2>Details</th>
    </tr>
            <?php
            foreach ($allStarActivity as $activity) {
                ?>
    <tr>
        <td><center><?= $h::safeHtmlOutput($activity['year']) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($activity['event']) ?></center></td>
        <td><center><?= $h::safeHtmlOutput($activity['team'] ?? '-') ?></center></td>
        <td colspan=2><center><?= $h::safeHtmlOutput($activity['details'] ?? '-') ?></center></td>
    </tr>
                <?php
            }
        }
        ?>
</table>
        <?php
        return ob_get_clean();
    }
}
