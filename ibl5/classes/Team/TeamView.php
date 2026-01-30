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
        $tabs = $pageData['tabs'];
        $tableOutput = $pageData['tableOutput'];
        $startersTable = $pageData['startersTable'];
        $draftPicksTable = $pageData['draftPicksTable'];
        $teamInfoRight = $pageData['teamInfoRight'];
        $rafters = $pageData['rafters'];

        $draftPicksHtml = $isActualTeam ? $this->renderDraftPicksSection($team, $draftPicksTable) : "";
        $sidebarMobileHtml = $isActualTeam ? "<div class=\"team-page-sidebar-mobile\">$teamInfoRight</div>" : "";
        $raftersHtml = $isActualTeam ? "<div class=\"team-page-rafters\">$rafters</div>" : "";
        $sidebarDesktopHtml = $isActualTeam ? "<div class=\"team-page-sidebar\">$teamInfoRight</div>" : "";

        $yearHeading = ($yr !== null && $yr !== '')
            ? "<h1 class=\"ibl-title\" style=\"margin: 0.5rem 0;\">" . \Utilities\HtmlSanitizer::safeHtmlOutput($yr) . " " . \Utilities\HtmlSanitizer::safeHtmlOutput($team->name) . "</h1>"
            : "";

        ob_start();
        ?>
<div class="team-page-layout">
    <div class="team-page-main">
        <div style="text-align: center; margin-bottom: 1rem;">
            <img src="./<?= $imagesPath ?>logo/<?= $teamID ?>.jpg" style="display: block; margin: 0 auto;">
            <?= $yearHeading ?>
        </div>
        <div class="team-stats-block">
            <?= $tabs ?>
            <div class="table-scroll-wrapper">
                <div class="table-scroll-container">
                    <?= $tableOutput ?>
                </div>
            </div>
        </div>
        <div class="table-scroll-wrapper">
            <div class="table-scroll-container">
                <?= $startersTable ?>
            </div>
        </div>
        <?= $draftPicksHtml ?>
        <?= $sidebarMobileHtml ?>
        <?= $raftersHtml ?>
    </div>
    <?= $sidebarDesktopHtml ?>
</div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the draft picks section with team-colored header
     */
    private function renderDraftPicksSection(object $team, string $draftPicksTable): string
    {
        $color1 = \Utilities\HtmlSanitizer::safeHtmlOutput($team->color1);
        $color2 = \Utilities\HtmlSanitizer::safeHtmlOutput($team->color2);

        ob_start();
        ?>
<div style="background-color: #<?= $color1 ?>; text-align: center; padding: 4px;">
    <span style="color: #<?= $color2 ?>; font-weight: bold;">Draft Picks</span>
</div>
<div class="table-scroll-wrapper">
    <div class="table-scroll-container">
        <?= $draftPicksTable ?>
    </div>
</div>
        <?php
        return ob_get_clean();
    }
}
