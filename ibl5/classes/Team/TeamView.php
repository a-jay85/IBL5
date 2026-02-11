<?php

declare(strict_types=1);

namespace Team;

use Team\Contracts\TeamViewInterface;

/**
 * @phpstan-import-type TeamPageData from Contracts\TeamServiceInterface
 *
 * @see TeamViewInterface
 */
class TeamView implements TeamViewInterface
{
    /**
     * @see TeamViewInterface::render()
     * @param TeamPageData $pageData
     */
    public function render(array $pageData): string
    {
        $teamID = $pageData['teamID'];
        $team = $pageData['team'];
        /** @var string $imagesPath */
        $imagesPath = \Utilities\HtmlSanitizer::safeHtmlOutput($pageData['imagesPath']);
        $yr = $pageData['yr'];
        $isActualTeam = $pageData['isActualTeam'];
        $tableOutput = $pageData['tableOutput'];
        $draftPicksTable = $pageData['draftPicksTable'];
        $currentSeasonCard = $pageData['currentSeasonCard'];
        $awardsCard = $pageData['awardsCard'];
        $franchiseHistoryCard = $pageData['franchiseHistoryCard'];
        $rafters = $pageData['rafters'];
        $userTeamName = $pageData['userTeamName'];
        $isOwnTeam = $pageData['isOwnTeam'];

        $draftPicksHtml = $isActualTeam ? $this->renderDraftPicksSection($team, $draftPicksTable) : "";
        $cardsRowHtml = "";
        if ($isActualTeam) {
            $cardsRowHtml = "<div class=\"team-cards-row\">$currentSeasonCard$draftPicksHtml$awardsCard</div>";
            $draftPicksHtml = ""; // already inside cards row
        }
        $franchiseHtml = $isActualTeam ? "<div style=\"max-width: 1115px; margin: 0 auto;\">$franchiseHistoryCard</div>" : "";
        $raftersHtml = $isActualTeam ? "<div class=\"team-page-rafters\">$rafters</div>" : "";

        /** @var string $yrSafe */
        $yrSafe = \Utilities\HtmlSanitizer::safeHtmlOutput($yr ?? '');
        /** @var string $teamNameSafe */
        $teamNameSafe = \Utilities\HtmlSanitizer::safeHtmlOutput($team->name);
        $yearHeading = ($yr !== null && $yr !== '')
            ? "<h1 class=\"ibl-title\">$yrSafe $teamNameSafe</h1>"
            : "";

        $bannerHtml = $isActualTeam
            ? $this->renderTeamBanner($teamID, $team, $imagesPath, $userTeamName, $isOwnTeam)
            : "<div style=\"text-align: center; margin-bottom: 1rem;\"><img src=\"./{$imagesPath}logo/{$teamID}.jpg\" style=\"display: block; margin: 0 auto;\"></div>";

        ob_start();
        ?>
<div class="team-page-layout">
    <div class="team-page-main">
        <div class="team-stats-block">
            <?= $yearHeading ?>
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
        return (string) ob_get_clean();
    }

    /**
     * Render the team banner row with logo centered and action links flanking it
     *
     * @param \Team $team Team object with color1, color2, name, discordID properties
     */
    private function renderTeamBanner(int $teamID, object $team, string $imagesPath, string $userTeamName, bool $isOwnTeam): string
    {
        /** @var \Team $team */
        $color1 = \UI\TableStyles::sanitizeColor($team->color1);
        $color2 = \UI\TableStyles::sanitizeColor($team->color2);

        $tradeButton = '';
        $discordButton = '';
        if ($userTeamName !== '') {
            if ($isOwnTeam) {
                $tradeButton = '<a href="modules.php?name=Trading&amp;op=reviewtrade" class="team-action-link">Trade</a>';
                $discordButton = '<a href="https://discord.com/channels/' . \Discord::IBL_GUILD_ID . '" class="team-action-link" target="_blank" rel="noopener noreferrer">Discord</a>';
            } else {
                $partnerParam = \Utilities\HtmlSanitizer::safeHtmlOutput($team->name);
                $tradeButton = '<a href="modules.php?name=Trading&amp;op=offertrade&amp;partner=' . urlencode($team->name) . '" class="team-action-link">Trade</a>';
                if ($team->discordID !== null) {
                    /** @var string $discordIDSafe */
                    $discordIDSafe = \Utilities\HtmlSanitizer::safeHtmlOutput((string) $team->discordID);
                    $discordButton = '<a href="https://discord.com/users/' . $discordIDSafe . '" class="team-action-link" target="_blank" rel="noopener noreferrer">Discord</a>';
                }
            }
        }

        ob_start();
        ?>
<div class="team-banner-row" style="--team-tab-bg-color: #<?= $color1 ?>; --team-tab-active-color: #<?= $color2 ?>;">
    <?= $tradeButton ?>
    <a href="modules.php?name=Schedule&amp;teamID=<?= $teamID ?>" class="team-action-link">Schedule</a>
    <div class="team-banner-logo" style="text-align: center;">
        <img src="./<?= $imagesPath ?>logo/<?= $teamID ?>.jpg" style="display: block; margin: 0 18px;">
    </div>
    <a href="modules.php?name=DraftHistory&amp;teamID=<?= $teamID ?>" class="team-action-link">Draft History</a>
    <?= $discordButton ?>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the draft picks section with team-colored header
     *
     * @param \Team $team Team object with color1, color2 properties
     */
    private function renderDraftPicksSection(object $team, string $draftPicksTable): string
    {
        /** @var \Team $team */
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
        return (string) ob_get_clean();
    }
}
