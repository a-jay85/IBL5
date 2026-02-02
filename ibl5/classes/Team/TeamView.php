<?php

declare(strict_types=1);

namespace Team;

use Team\Contracts\TeamViewInterface;

/**
 * @see TeamViewInterface
 */
class TeamView implements TeamViewInterface
{
    /**
     * @see TeamViewInterface::render()
     */
    public function render(array $pageData): string
    {
        $teamID = (int) $pageData['teamID'];
        $team = $pageData['team'];
        $imagesPath = \Utilities\HtmlSanitizer::safeHtmlOutput($pageData['imagesPath']);
        $yr = $pageData['yr'];
        $isActualTeam = $pageData['isActualTeam'];
        $tableOutput = $pageData['tableOutput'];
        $draftPicksTable = $pageData['draftPicksTable'];
        $currentSeasonCard = $pageData['currentSeasonCard'];
        $awardsCard = $pageData['awardsCard'];
        $franchiseHistoryCard = $pageData['franchiseHistoryCard'];
        $rafters = $pageData['rafters'];

        $draftPicksHtml = $isActualTeam ? $this->renderDraftPicksSection($team, $draftPicksTable) : "";
        $cardsRowHtml = "";
        if ($isActualTeam) {
            $cardsRowHtml = "<div class=\"team-cards-row\">$currentSeasonCard$draftPicksHtml$awardsCard</div>";
            $draftPicksHtml = ""; // already inside cards row
        }
        $franchiseHtml = $isActualTeam ? $franchiseHistoryCard : "";
        $raftersHtml = $isActualTeam ? "<div class=\"team-page-rafters\">$rafters</div>" : "";

        $yearHeading = ($yr !== null && $yr !== '')
            ? "<h1 class=\"ibl-title\" style=\"margin: 0.5rem 0;\">" . \Utilities\HtmlSanitizer::safeHtmlOutput($yr) . " " . \Utilities\HtmlSanitizer::safeHtmlOutput($team->name) . "</h1>"
            : "";

        $bannerHtml = $isActualTeam
            ? $this->renderTeamBanner($teamID, $team, $imagesPath, $yearHeading)
            : "<div style=\"text-align: center; margin-bottom: 1rem;\"><img src=\"./{$imagesPath}logo/{$teamID}.jpg\" style=\"display: block; margin: 0 auto;\">{$yearHeading}</div>";

        ob_start();
        ?>
<div class="team-page-layout">
    <div class="team-page-main">
        <div class="team-stats-block">
            <?= $bannerHtml ?>
            <div class="table-scroll-wrapper">
                <div class="table-scroll-container">
                    <?= $tableOutput ?>
                </div>
            </div>
        </div>
        <?= $draftPicksHtml ?>
        <?= $cardsRowHtml ?>
        <?= $franchiseHtml ?>
        <?= $raftersHtml ?>
    </div>
</div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the team banner row with logo centered and action links flanking it
     */
    private function renderTeamBanner(int $teamID, object $team, string $imagesPath, string $yearHeading): string
    {
        $color1 = \UI\TableStyles::sanitizeColor($team->color1);
        $color2 = \UI\TableStyles::sanitizeColor($team->color2);

        ob_start();
        ?>
<div class="team-banner-row" style="--team-tab-bg-color: #<?= $color1 ?>; --team-tab-active-color: #<?= $color2 ?>;">
    <a href="modules.php?name=Schedule&amp;teamID=<?= $teamID ?>" class="team-action-link">Schedule</a>
    <div style="text-align: center;">
        <img src="./<?= $imagesPath ?>logo/<?= $teamID ?>.jpg" style="display: block; margin: 0 18px;">
        <?= $yearHeading ?>
    </div>
    <a href="modules.php?name=Draft_History&amp;teamID=<?= $teamID ?>" class="team-action-link">Draft History</a>
</div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the draft picks section with team-colored header
     */
    private function renderDraftPicksSection(object $team, string $draftPicksTable): string
    {
        $color1 = \UI\TableStyles::sanitizeColor($team->color1);
        $color2 = \UI\TableStyles::sanitizeColor($team->color2);

        ob_start();
        ?>
<div class="team-card" style="--team-color-primary: #<?= $color1 ?>; --team-color-secondary: #<?= $color2 ?>;">
    <div class="team-card__header">
        <h3 class="team-card__title">Draft Picks</h3>
    </div>
    <div class="team-card__body--flush">
        <?= $draftPicksTable ?>
    </div>
</div>
        <?php
        return ob_get_clean();
    }
}
