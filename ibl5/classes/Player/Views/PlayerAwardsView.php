<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerRepository;
use Player\Contracts\PlayerAwardsViewInterface;
use Utilities\HtmlSanitizer;

/**
 * PlayerAwardsView - Renders player awards and All-Star activity
 * 
 * Pure rendering with no database logic - all data fetched via PlayerRepository
 * 
 * @see PlayerAwardsViewInterface
 */
class PlayerAwardsView implements PlayerAwardsViewInterface
{
    private PlayerRepository $repository;

    public function __construct(PlayerRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see PlayerAwardsViewInterface::renderAllStarActivity()
     */
    public function renderAllStarActivity(string $playerName): string
    {
        $allStarGames = $this->repository->getAllStarGameCount($playerName);
        $threePointContests = $this->repository->getThreePointContestCount($playerName);
        $dunkContests = $this->repository->getDunkContestCount($playerName);
        $rookieSophChallenges = $this->repository->getRookieSophChallengeCount($playerName);

        ob_start();
        ?>
<tr>
    <td colspan=3>
        <table align=left cellspacing=1 cellpadding=0 border=1>
            <th colspan=2><center>All-Star Activity</center></th>
</tr>
<tr>
    <td><b>All Star Games:</b></td>
    <td><?= HtmlSanitizer::safeHtmlOutput((string)$allStarGames) ?></td>
</tr>
<tr>
    <td><b>Three-Point<br>Contests:</b></td>
    <td><?= HtmlSanitizer::safeHtmlOutput((string)$threePointContests) ?></td>
</tr>
<tr>
    <td><b>Slam Dunk<br>Competitions:</b></td>
    <td><?= HtmlSanitizer::safeHtmlOutput((string)$dunkContests) ?></td>
</tr>
<tr>
    <td><b>Rookie-Sophomore<br>Challenges:</b></td>
    <td><?= HtmlSanitizer::safeHtmlOutput((string)$rookieSophChallenges) ?></td>
</tr>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * @see PlayerAwardsViewInterface::renderAwardsList()
     */
    public function renderAwardsList(string $playerName): string
    {
        $awards = $this->repository->getAwards($playerName);

        ob_start();
        ?>
<table border=1 cellspacing=1 cellpadding=0>
    <tr>
        <td><center><b><font class="content">Year</font></b></center></td>
        <td><center><b><font class="content">Award</font></b></center></td>
    </tr>
        <?php
        foreach ($awards as $award) {
            $year = HtmlSanitizer::safeHtmlOutput($award['year']);
            $awardName = HtmlSanitizer::safeHtmlOutput($award['Award']);
            ?>
    <tr>
        <td align=center><?= $year ?></td>
        <td><?= $awardName ?></td>
    </tr>
            <?php
        }
        ?>
</table>
        <?php
        return ob_get_clean();
    }
}
