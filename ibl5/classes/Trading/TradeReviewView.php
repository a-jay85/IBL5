<?php

declare(strict_types=1);

namespace Trading;

use UI\TableStyles;
use Security\HtmlSanitizer;
use Security\CsrfGuard;
use UI\TeamCellHelper;

class TradeReviewView
{
    /**
     * @see \Trading\Contracts\TradingViewInterface::renderTradeReview()
     *
     * @param array<string, mixed> $pageData
     */
    public function renderTradeReview(array $pageData): string
    {
        /** @var array{userTeam: string, userTeamId: int, tradeOffers: array<int, array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>, previewData: array{fromPids: list<int>, toPids: list<int>, fromTeamId: int, toTeamId: int, fromColor1: string, toColor1: string, fromCash: array<int, int>, toCash: array<int, int>, cashStartYear: int, cashEndYear: int, seasonEndingYear: int}}>, teams: list<array{name: string, city: string, fullName: string, teamid: int, color1: string, color2: string, mobileOrder: int}>, result?: string, error?: string} $pageData */

        $userTeam = HtmlSanitizer::safeHtmlOutput($pageData['userTeam']);
        $userTeamId = $pageData['userTeamId'];
        $tradeOffers = $pageData['tradeOffers'];
        $teams = $pageData['teams'];

        /** @var array<int, array<string, mixed>> $reviewConfigs */
        $reviewConfigs = [];

        ob_start();
        echo \UI\AlertRenderer::fromCode($pageData['result'] ?? null, \Trading\TradeAlertMap::MAP, $pageData['error'] ?? null);
        ?>
<div class="trading-layout__header">
    <h1 class="ibl-title">Trading</h1>
    <img src="images/logo/<?= HtmlSanitizer::e($userTeamId) ?>.jpg" alt="Team Logo" class="team-logo-banner">
</div>
<div class="trading-review-wrapper">
    <div class="trading-review-offers">
<?php if ($tradeOffers !== []):
    // One CSRF token per action shared by every offer card's accept/reject form
    // on this render. Generating a token per card overflowed CsrfGuard's
    // per-action MAX_TOKENS=10 cap once many offers accumulated (or concurrent
    // renders piled onto the shared E2E session), evicting the oldest card's
    // token before it could be submitted ("Invalid or expired form submission").
    // Sharing keeps token usage flat regardless of offer count; tokens are
    // single-use and each action PRG-reloads to mint a fresh pair.
    $acceptToken = CsrfGuard::generateRawToken('trade_accept');
    $rejectToken = CsrfGuard::generateRawToken('trade_reject');
?>
    <?php foreach ($tradeOffers as $offerId => $offer):
        $preview = $offer['previewData'];
        $reviewConfigs[(int) $offerId] = [
            'rosterPreviewApiBaseUrl' => 'modules.php?name=Trading&op=roster-preview-api',
            'fromTeam' => $offer['from'],
            'toTeam' => $offer['to'],
            'fromTeamId' => $preview['fromTeamId'],
            'toTeamId' => $preview['toTeamId'],
            'fromPids' => $preview['fromPids'],
            'toPids' => $preview['toPids'],
            'fromCash' => $preview['fromCash'],
            'toCash' => $preview['toCash'],
            'cashStartYear' => $preview['cashStartYear'],
            'cashEndYear' => $preview['cashEndYear'],
            'seasonEndingYear' => $preview['seasonEndingYear'],
            'fromColor1' => $preview['fromColor1'],
            'toColor1' => $preview['toColor1'],
            'userTeamId' => $userTeamId,
        ];
    ?>
        <?= HtmlSanitizer::trusted($this->renderTradeOfferCard((int) $offerId, $offer, $userTeam, $userTeamId, $acceptToken, $rejectToken)) ?>
    <?php endforeach; ?>
<?php endif; ?>
    </div>
    <?= HtmlSanitizer::trusted($this->renderTeamSelectionLinks($teams)) ?>
</div>
<?php if ($reviewConfigs !== []): ?>
<script>window.IBL_TRADE_REVIEW_CONFIGS = <?= json_encode($reviewConfigs, JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;</script>
<script src="jslib/trade-review-preview.js" defer></script>
<?php endif; ?>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see \Trading\Contracts\TradingViewInterface::renderTeamSelectionLinks()
     *
     * @param list<array{name: string, city: string, fullName: string, teamid: int, color1: string, color2: string, mobileOrder: int}> $teams Conference-split, sorted, interleaved team list from TradingService::buildTeamList()
     */
    public function renderTeamSelectionLinks(array $teams): string
    {
        ob_start();
        ?>
<table class="ibl-data-table trading-team-select">
    <thead>
        <tr>
            <th>West</th>
            <th>East</th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($teams as $team): ?>
        <?php
        $teamId = $team['teamid'];
        $teamName = HtmlSanitizer::safeHtmlOutput($team['name']);
        $partnerUrl = 'modules.php?name=Trading&amp;op=offertrade&amp;partner=' . $teamName;
        $cityHtml = HtmlSanitizer::safeHtmlOutput($team['city']);
        $nameHtml = '<span class="ibl-team-cell__city">' . $cityHtml . ' </span>' . $teamName;
        $cell = TeamCellHelper::renderTeamCell($teamId, $team['fullName'], $team['color1'], $team['color2'], '', $partnerUrl, $nameHtml, '--mobile-order: ' . $team['mobileOrder'] . '; ');
        ?>
        <tr>
            <?= HtmlSanitizer::trusted($cell) ?>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render a single trade offer card with items, action buttons, and preview panel
     *
     * @param array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>, previewData: array{fromPids: list<int>, toPids: list<int>, fromTeamId: int, toTeamId: int, fromColor1: string, toColor1: string, fromCash: array<int, int>, toCash: array<int, int>, cashStartYear: int, cashEndYear: int, seasonEndingYear: int}} $offer
     * @param string $acceptToken Shared per-render CSRF token for the accept form (see renderReview)
     * @param string $rejectToken Shared per-render CSRF token for the reject form (see renderReview)
     */
    private function renderTradeOfferCard(int $offerId, array $offer, string $userTeam, int $userTeamId, string $acceptToken, string $rejectToken): string
    {
        $oppositeTeam = HtmlSanitizer::safeHtmlOutput($offer['oppositeTeam']);

        ob_start();
        ?>
<div class="trade-offer-card">
    <div class="trade-offer-card__header">
        <strong>Trade Offer #<?= HtmlSanitizer::e($offerId) ?></strong>
    </div>
    <div class="trade-offer-card__actions">
<?php if ($offer['hasHammer']): ?>
        <form name="tradeaccept" method="post" action="/ibl5/modules/Trading/accepttradeoffer.php" class="trade-offer-card__form">
            <input type="hidden" name="_csrf_token" value="<?= HtmlSanitizer::e($acceptToken) ?>">
            <input type="hidden" name="offer" value="<?= HtmlSanitizer::e($offerId) ?>">
            <button type="submit" class="ibl-btn ibl-btn--success">Accept</button>
        </form>
<?php else: ?>
        <span class="trade-offer-card__awaiting">Awaiting Approval</span>
<?php endif; ?>
        <form name="tradereject" method="post" action="/ibl5/modules/Trading/rejecttradeoffer.php" class="trade-offer-card__form">
            <input type="hidden" name="_csrf_token" value="<?= HtmlSanitizer::e($rejectToken) ?>">
            <input type="hidden" name="offer" value="<?= HtmlSanitizer::e($offerId) ?>">
            <input type="hidden" name="teamRejecting" value="<?= HtmlSanitizer::trusted($userTeam) ?>">
            <input type="hidden" name="teamReceiving" value="<?= HtmlSanitizer::trusted($oppositeTeam) ?>">
            <button type="submit" class="ibl-btn ibl-btn--danger">Reject</button>
        </form>
    </div>
    <div class="trade-offer-items">
<?php foreach ($offer['items'] as $item): ?>
    <?php if ($item['description'] !== ''):
        $descriptionEscaped = HtmlSanitizer::safeHtmlOutput($item['description']);
    ?>
        <p><?= HtmlSanitizer::trusted($descriptionEscaped) ?></p>
        <?php if ($item['notes'] !== null):
            $notesEscaped = HtmlSanitizer::safeHtmlOutput($item['notes']);
        ?>
            <p class="trade-offer-card__notes"><?= HtmlSanitizer::trusted($notesEscaped) ?></p>
        <?php endif; ?>
    <?php endif; ?>
<?php endforeach; ?>
    </div>
    <div class="trade-offer-card__preview-wrap">
        <button type="button" class="ibl-btn ibl-btn--neutral ibl-btn--sm" data-preview-offer="<?= HtmlSanitizer::e($offerId) ?>">Preview</button>
    </div>
</div>
<?= HtmlSanitizer::trusted($this->renderReviewRosterPreview($offerId, $offer['previewData'], $userTeamId)) ?>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render a roster preview panel for a trade review offer card
     *
     * @param array{fromPids: list<int>, toPids: list<int>, fromTeamId: int, toTeamId: int, fromColor1: string, toColor1: string, fromCash: array<int, int>, toCash: array<int, int>, cashStartYear: int, cashEndYear: int, seasonEndingYear: int} $previewData
     */
    private function renderReviewRosterPreview(int $offerId, array $previewData, int $userTeamId): string
    {
        $fromTeamId = $previewData['fromTeamId'];
        $toTeamId = $previewData['toTeamId'];

        // Determine initial team: show the user's team first
        $initialTeamId = ($userTeamId === $fromTeamId) ? $fromTeamId : $toTeamId;
        $initialColor = ($initialTeamId === $fromTeamId)
            ? \UI\TableStyles::sanitizeColor($previewData['fromColor1'])
            : \UI\TableStyles::sanitizeColor($previewData['toColor1']);
        $safeFromColor = \UI\TableStyles::sanitizeColor($previewData['fromColor1']);
        $safeToColor = \UI\TableStyles::sanitizeColor($previewData['toColor1']);

        ob_start();
        ?>
<div id="trade-review-preview-<?= HtmlSanitizer::e($offerId) ?>" class="trade-roster-preview" style="--preview-user-color: #<?= HtmlSanitizer::e($safeFromColor) ?>; --preview-partner-color: #<?= HtmlSanitizer::e($safeToColor) ?>;" hidden>
    <div class="trade-roster-preview__header">
        <img src="images/logo/new<?= HtmlSanitizer::e($fromTeamId) ?>.png" alt="From Team" class="trade-roster-preview__logo<?= $initialTeamId === $fromTeamId ? ' trade-roster-preview__logo--active' : '' ?>" data-team-id="<?= HtmlSanitizer::e($fromTeamId) ?>">
        <div class="trade-roster-preview__title">Roster Preview</div>
        <img src="images/logo/new<?= HtmlSanitizer::e($toTeamId) ?>.png" alt="To Team" class="trade-roster-preview__logo<?= $initialTeamId === $toTeamId ? ' trade-roster-preview__logo--active' : '' ?>" data-team-id="<?= HtmlSanitizer::e($toTeamId) ?>">
    </div>
    <div class="trade-roster-preview__tabs ibl-tabs" role="tablist" style="<?= TableStyles::inlineTeamVars($initialColor, $initialColor) ?>">
        <button type="button" class="ibl-tab ibl-tab--active" data-display="ratings" role="tab">Ratings</button>
        <button type="button" class="ibl-tab" data-display="total_s" role="tab">Totals</button>
        <button type="button" class="ibl-tab" data-display="avg_s" role="tab">Averages</button>
        <button type="button" class="ibl-tab" data-display="per36mins" role="tab">Per 36</button>
        <button type="button" class="ibl-tab" data-display="contracts" role="tab">Contracts</button>
    </div>
    <div class="table-scroll-wrapper">
        <div class="table-scroll-container" tabindex="0" role="region" aria-label="Trade review roster preview">
            <div class="trade-roster-preview__loading">Loading</div>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
