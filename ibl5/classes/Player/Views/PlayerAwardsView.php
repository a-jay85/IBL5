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
        <table class="allstar-table">
            <tr>
                <th colspan=2>All-Star Activity</th>
            </tr>
            <tr>
                <td class="text-bold">All Star Games:</td>
                <td><?= $allStarGames ?></td>
            </tr>
            <tr>
                <td class="text-bold">Three-Point<br>Contests:</td>
                <td><?= $threePointContests ?></td>
            </tr>
            <tr>
                <td class="text-bold">Slam Dunk<br>Competitions:</td>
                <td><?= $dunkContests ?></td>
            </tr>
            <tr>
                <td class="text-bold">Rookie-Sophomore<br>Challenges:</td>
                <td><?= $rookieSophChallenges ?></td>
            </tr>
        </table>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see PlayerAwardsViewInterface::renderAwardsList()
     */
    public function renderAwardsList(string $playerName): string
    {
        $awards = $this->repository->getAwards($playerName);

        ob_start();
        ?>
<table class="awards-table">
    <tr>
        <td class="content-header">Year</td>
        <td class="content-header">Award</td>
    </tr>
        <?php
        foreach ($awards as $award) {
            /** @var string $year */
            $year = HtmlSanitizer::safeHtmlOutput($award['year']);
            /** @var string $awardName */
            $awardName = HtmlSanitizer::safeHtmlOutput($award['Award']);
            ?>
    <tr>
        <td class="year-cell"><?= $year ?></td>
        <td><?= $awardName ?></td>
    </tr>
            <?php
        }
        ?>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
