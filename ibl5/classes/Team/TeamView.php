<?php

declare(strict_types=1);

namespace Team;

use Team\Contracts\TeamViewInterface;
use Utilities\HtmlSanitizer;

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
        $extensionResult = $pageData['extensionResult'];
        $extensionMsg = $pageData['extensionMsg'];

        $draftPicksHtml = $isActualTeam ? $this->renderDraftPicksSection($team, $draftPicksTable) : "";
        $cardsRowHtml = "";
        if ($isActualTeam) {
            $cardsRowHtml = "<div class=\"team-cards-row\">$currentSeasonCard$draftPicksHtml$awardsCard</div>";
            $draftPicksHtml = ""; // already inside cards row
        }
        $franchiseHtml = $isActualTeam ? "<div class=\"franchise-history-wrapper\">$franchiseHistoryCard</div>" : "";
        $raftersHtml = $isActualTeam ? "<div class=\"team-page-rafters\">$rafters</div>" : "";

        $yrSafe = \Utilities\HtmlSanitizer::safeHtmlOutput($yr ?? '');
        $teamNameSafe = \Utilities\HtmlSanitizer::safeHtmlOutput($team->name);
        $yearHeading = ($yr !== null && $yr !== '')
            ? "<h1 class=\"ibl-title\">$yrSafe $teamNameSafe</h1>"
            : "";

        $bannerHtml = $isActualTeam
            ? $this->renderTeamBanner($teamID, $team, $imagesPath, $userTeamName, $isOwnTeam)
            : "<div class=\"team-logo-fallback\"><img src=\"./{$imagesPath}logo/{$teamID}.jpg\"></div>";

        ob_start();
        ?>
<div class="team-page-layout">
    <div class="team-page-main">
        <div class="team-stats-block">
            <?= $yearHeading ?>
            <?= $bannerHtml ?>
            <?= $this->renderExtensionResultBanner($extensionResult, $extensionMsg) ?>
            <div class="table-scroll-wrapper">
                <div class="table-scroll-container" tabindex="0" role="region" aria-label="Team roster">
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
     * Render a flash message banner for extension results (PRG pattern)
     */
    private function renderExtensionResultBanner(?string $result, ?string $msg): string
    {
        if ($result === null) {
            return '';
        }

        $msgSafe = HtmlSanitizer::e($msg ?? '');

        if ($result === 'extension_error') {
            return '<div class="ibl-alert ibl-alert--error">'
                . $msgSafe
                . ' Your extension attempt was not legal and will not be recorded.'
                . '</div>';
        }

        if ($result === 'extension_accepted') {
            return '<div class="ibl-alert ibl-alert--success">'
                . '<strong>Player response:</strong> ' . $msgSafe
                . '<br>Note from the commissioner\'s office: You have used up your successful extension for this season and may not make any more extension attempts.'
                . '</div>';
        }

        if ($result === 'extension_rejected') {
            return '<div class="ibl-alert ibl-alert--info">'
                . '<strong>Player response:</strong> ' . $msgSafe
                . '<br>Note from the commissioner\'s office: You will be able to make another attempt next sim as you have not yet used up your successful extension for this season.'
                . '</div>';
        }

        return '';
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
                $discordButton = '<a href="https://discord.com/channels/' . \Discord::getGuildID() . '" class="team-action-link" target="_blank" rel="noopener noreferrer">Discord</a>';
            } else {
                $partnerParam = \Utilities\HtmlSanitizer::safeHtmlOutput($team->name);
                $tradeButton = '<a href="modules.php?name=Trading&amp;op=offertrade&amp;partner=' . urlencode($team->name) . '" class="team-action-link">Trade</a>';
                if ($team->discordID !== null) {
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
    <div class="team-banner-logo">
        <img src="./<?= $imagesPath ?>logo/<?= $teamID ?>.jpg">
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
        ob_start();
        ?>
<div class="team-card" style="<?= \UI\TableStyles::inlineVars($team->color1, $team->color2) ?>">
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
