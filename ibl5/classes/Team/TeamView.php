<?php

declare(strict_types=1);

namespace Team;

use Team\Contracts\TeamViewInterface;
use Security\HtmlSanitizer;
use Discord\Discord;
use Extension\Contracts\ExtensionViewInterface;
use Extension\ExtensionView;

/**
 * @phpstan-import-type TeamPageData from Contracts\TeamServiceInterface
 *
 * @see TeamViewInterface
 */
class TeamView implements TeamViewInterface
{
    private ExtensionViewInterface $extensionView;

    public function __construct(?ExtensionViewInterface $extensionView = null)
    {
        $this->extensionView = $extensionView ?? new ExtensionView();
    }

    /**
     * @see TeamViewInterface::render()
     * @param TeamPageData $pageData
     */
    public function render(array $pageData): string
    {
        $teamid = $pageData['teamid'];
        $team = $pageData['team'];
        $imagesPath = $pageData['imagesPath'];
        $yr = $pageData['yr'];
        $isActualTeam = $pageData['isActualTeam'];
        $tableOutput = $pageData['tableOutput'];
        $draftPicksTable = $pageData['draftPicksTable'];
        $currentSeasonCard = $pageData['currentSeasonCard'];
        $awardsCard = $pageData['awardsCard'];
        $franchiseHistoryCard = $pageData['franchiseHistoryCard'];
        $rafters = $pageData['rafters'];
        $rivalsCard = $pageData['rivalsCard'] ?? '';
        $userTeamName = $pageData['userTeamName'];
        $isOwnTeam = $pageData['isOwnTeam'];
        $extensionResult = $pageData['extensionResult'];
        $extensionMsg = $pageData['extensionMsg'];

        ob_start();
        ?>
<div class="team-page-layout">
    <div class="team-page-main">
        <div class="team-stats-block">
            <?= HtmlSanitizer::trusted($this->renderPageTitleHeading($isActualTeam)) ?>
            <?= HtmlSanitizer::trusted($this->renderBannerBlock($isActualTeam, $teamid, $team, $imagesPath, $userTeamName, $isOwnTeam)) ?>
            <?= HtmlSanitizer::trusted($this->extensionView->renderResultBanner($extensionResult, $extensionMsg)) ?>
            <?= HtmlSanitizer::trusted($this->renderYearHeading($isActualTeam, $yr, $team->name ?? '')) ?>
            <?= HtmlSanitizer::trusted($this->renderRosterRegion($tableOutput)) ?>
        </div>
                <?= HtmlSanitizer::trusted($this->renderCardsRow($isActualTeam, $team, $currentSeasonCard, $draftPicksTable, $awardsCard)) ?>
        <?= HtmlSanitizer::trusted($this->renderFranchiseHistory($isActualTeam, $franchiseHistoryCard) . $rivalsCard) ?>
        <?= HtmlSanitizer::trusted($this->renderRafters($isActualTeam, $rafters)) ?>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Non-actual page (Free Agents, teamid=0): the decorative fallback logo cannot be
     * an <h1>, so emit a literal text <h1> to keep page-has-heading-one coverage.
     */
    private function renderPageTitleHeading(bool $isActualTeam): string
    {
        return $isActualTeam ? '' : '<h1 class="ibl-title">Free Agents</h1>';
    }

    /**
     * Actual team: the banner logo is the page <h1> (see renderTeamBanner).
     *
     * @param Team $team Team object
     */
    private function renderBannerBlock(bool $isActualTeam, int $teamid, object $team, string $imagesPath, string $userTeamName, bool $isOwnTeam): string
    {
        if ($isActualTeam) {
            return $this->renderTeamBanner($teamid, $team, $imagesPath, $userTeamName, $isOwnTeam);
        }

        return '<div class="team-logo-fallback"><img src="./' . HtmlSanitizer::e($imagesPath) . 'logo/' . $teamid . '.jpg" alt=""></div>';
    }

    /**
     * When a historical year is selected, show "<year> <team>" as an <h2> in its own row
     * between the banner and the roster table. Current season (yr null) shows no text.
     */
    private function renderYearHeading(bool $isActualTeam, ?string $yr, string $teamName): string
    {
        if (!$isActualTeam || $yr === null || $yr === '') {
            return '';
        }

        return '<h2 class="ibl-title">' . HtmlSanitizer::e($yr) . ' ' . HtmlSanitizer::e($teamName) . '</h2>';
    }

    /**
     * @param string $tableOutput Pre-built roster table HTML from TeamTableService::getTableOutput()
     */
    private function renderRosterRegion(string $tableOutput): string
    {
        // Whitespace reproduces the original template bytes (pinned by TeamServiceSnapshotTest).
        return "<div class=\"table-scroll-wrapper\">\n"
            . str_repeat(' ', 16) . "<div class=\"table-scroll-container\" tabindex=\"0\" role=\"region\" aria-label=\"Team roster\">\n"
            . str_repeat(' ', 20) . $tableOutput
            . str_repeat(' ', 16) . "</div>\n"
            . str_repeat(' ', 12) . "</div>\n";
    }

    /**
     * @param Team $team Team object with color1, color2 properties
     * @param string $currentSeasonCard Pre-built HTML from TeamService::getTeamPageData() (TeamCardRenderer)
     * @param string $draftPicksTable Pre-built HTML from TeamCardRenderer::renderDraftPicksTable()
     * @param string $awardsCard Pre-built HTML from TeamService::getTeamPageData() (TeamCardRenderer)
     */
    private function renderCardsRow(bool $isActualTeam, object $team, string $currentSeasonCard, string $draftPicksTable, string $awardsCard): string
    {
        if (!$isActualTeam) {
            return '';
        }

        return '<div class="team-cards-row">'
            . $currentSeasonCard
            . $this->renderDraftPicksSection($team, $draftPicksTable)
            . $awardsCard
            . '</div>';
    }

    /**
     * @param string $franchiseHistoryCard Pre-built HTML from TeamService::getTeamPageData() (TeamCardRenderer)
     */
    private function renderFranchiseHistory(bool $isActualTeam, string $franchiseHistoryCard): string
    {
        return $isActualTeam ? '<div class="franchise-history-wrapper">' . $franchiseHistoryCard . '</div>' : '';
    }

    /**
     * @param string $rafters Pre-built HTML from TeamService::getTeamPageData() (TeamCardRenderer)
     */
    private function renderRafters(bool $isActualTeam, string $rafters): string
    {
        return $isActualTeam ? '<div class="team-page-rafters">' . $rafters . '</div>' : '';
    }

    /**
     * Render the team banner row with logo centered and action links flanking it
     *
     * @param Team $team Team object with color1, color2, name, discord_id properties
     */
    private function renderTeamBanner(int $teamid, object $team, string $imagesPath, string $userTeamName, bool $isOwnTeam): string
    {
        /** @var Team $team */
        $color1 = \UI\TableStyles::sanitizeColor($team->color1);
        $color2 = \UI\TableStyles::sanitizeColor($team->color2);

        ob_start();
        ?>
<div class="team-banner-row" style="<?= \UI\TableStyles::inlineTeamVars($color1, $color2) ?>">
    <?= HtmlSanitizer::trusted($this->renderTradeButton($userTeamName, $isOwnTeam, $team, $imagesPath)) ?>
    <a href="modules.php?name=Schedule&amp;teamid=<?= HtmlSanitizer::e($teamid) ?>" class="team-action-link"><?= HtmlSanitizer::trusted($this->renderActionLinkInner('Schedule', 'schedule-icon.svg', $imagesPath)) ?></a>
    <div class="team-banner-logo">
        <h1><img src="./<?= HtmlSanitizer::e($imagesPath) ?>logo/<?= HtmlSanitizer::e($teamid) ?>.jpg" alt="<?= HtmlSanitizer::e($team->name ?? '') ?>"></h1>
    </div>
    <a href="modules.php?name=DraftHistory&amp;teamid=<?= HtmlSanitizer::e($teamid) ?>" class="team-action-link"><?= HtmlSanitizer::trusted($this->renderActionLinkInner('Draft History', 'draft-history-icon.svg', $imagesPath)) ?></a>
    <?= HtmlSanitizer::trusted($this->renderDiscordButton($userTeamName, $isOwnTeam, $team, $imagesPath)) ?>
</div>
        <?php
        return (string) ob_get_clean();
    }

    private function renderActionLinkInner(string $label, string $iconFile, string $imagesPath): string
    {
        return '<span class="team-action-link__text">' . HtmlSanitizer::e($label) . '</span>'
            . '<img src="./' . HtmlSanitizer::e($imagesPath) . HtmlSanitizer::e($iconFile) . '" alt="' . HtmlSanitizer::e($label) . '" class="team-action-link__icon">';
    }

    /**
     * @param Team $team Team object with name property
     */
    private function renderTradeButton(string $userTeamName, bool $isOwnTeam, object $team, string $imagesPath): string
    {
        if ($userTeamName === '') {
            return '';
        }

        $inner = $this->renderActionLinkInner('Trade', 'trade-icon.svg', $imagesPath);
        if ($isOwnTeam) {
            return '<a href="modules.php?name=Trading&amp;op=reviewtrade" class="team-action-link">' . $inner . '</a>';
        }

        /** @var Team $team */
        return '<a href="modules.php?name=Trading&amp;op=offertrade&amp;partner=' . HtmlSanitizer::e(urlencode($team->name ?? '')) . '" class="team-action-link">' . $inner . '</a>';
    }

    /**
     * @param Team $team Team object with discord_id property
     */
    private function renderDiscordButton(string $userTeamName, bool $isOwnTeam, object $team, string $imagesPath): string
    {
        if ($userTeamName === '') {
            return '';
        }

        $inner = $this->renderActionLinkInner('Discord', 'discord-symbol-white.svg', $imagesPath);
        if ($isOwnTeam) {
            return '<a href="https://discord.com/channels/' . HtmlSanitizer::e(Discord::getGuildID()) . '" class="team-action-link team-action-link--discord" target="_blank" rel="noopener noreferrer">' . $inner . '</a>';
        }

        /** @var Team $team */
        if ($team->discord_id === null) {
            return '';
        }

        return '<a href="https://discord.com/users/' . HtmlSanitizer::e((string) $team->discord_id) . '" class="team-action-link team-action-link--discord" target="_blank" rel="noopener noreferrer">' . $inner . '</a>';
    }

    /**
     * Render the draft picks section with team-colored header
     *
     * @param Team $team Team object with color1, color2 properties
     */
    private function renderDraftPicksSection(object $team, string $draftPicksTable): string
    {
        /** @var Team $team */
        ob_start();
        ?>
<div class="team-card" style="<?= \UI\TableStyles::inlineTeamVars($team->color1, $team->color2) ?>">
    <div class="team-card__header">
        <h2 class="team-card__title">Draft Picks</h2>
    </div>
    <?= HtmlSanitizer::trusted($this->renderDraftPicksBody($draftPicksTable)) ?>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param string $draftPicksTable Pre-built HTML from TeamCardRenderer::renderDraftPicksTable() via TeamPageData['draftPicksTable']
     */
    private function renderDraftPicksBody(string $draftPicksTable): string
    {
        return "<div class=\"team-card__body--flush\">\n"
            . str_repeat(' ', 8) . $draftPicksTable
            . str_repeat(' ', 4) . "</div>\n";
    }
}
