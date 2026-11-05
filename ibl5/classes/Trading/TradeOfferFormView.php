<?php

declare(strict_types=1);

namespace Trading;

use League\League;
use Player\PlayerImageHelper;
use Security\HtmlSanitizer;
use UI\TableStyles;

/**
 * @phpstan-import-type TradingPlayerRow from \Trading\Contracts\TradeFormRepositoryInterface
 * @phpstan-import-type TradingDraftPickRow from \Trading\Contracts\TradeFormRepositoryInterface
 */
class TradeOfferFormView
{
    /**
     * @see \Trading\Contracts\TradingViewInterface::renderTradeOfferForm()
     *
     * @param array<string, mixed> $pageData
     */
    public function renderTradeOfferForm(array $pageData): string
    {
        /** @var array{userTeam: string, userTeamId: int, partnerTeam: string, partnerTeamId: int, userPlayers: list<TradingPlayerRow>, userPicks: list<TradingDraftPickRow>, userFutureSalary: array{player: array<int, int>, hold: array<int, int>}, partnerPlayers: list<TradingPlayerRow>, partnerPicks: list<TradingDraftPickRow>, partnerFutureSalary: array{player: array<int, int>, hold: array<int, int>}, seasonEndingYear: int, seasonPhase: string, cashStartYear: int, cashEndYear: int, userTeamColor1: string, userTeamColor2: string, partnerTeamColor1: string, partnerTeamColor2: string, result?: string, error?: string} $pageData */

        $userTeam = $pageData['userTeam'];
        $userTeamId = $pageData['userTeamId'];
        $partnerTeam = $pageData['partnerTeam'];
        $partnerTeamId = $pageData['partnerTeamId'];
        $seasonEndingYear = $pageData['seasonEndingYear'];
        $seasonPhase = $pageData['seasonPhase'];
        $cashStartYear = $pageData['cashStartYear'];
        $cashEndYear = $pageData['cashEndYear'];

        $userColor1 = TableStyles::sanitizeColor($pageData['userTeamColor1']);
        $userColor2 = TableStyles::sanitizeColor($pageData['userTeamColor2']);
        $partnerColor1 = TableStyles::sanitizeColor($pageData['partnerTeamColor1']);
        $partnerColor2 = TableStyles::sanitizeColor($pageData['partnerTeamColor2']);

        // Restore previous form selections after a failed trade attempt
        /** @var array{checkedItems: array<string, true>, userSendsCash: array<int, int>, partnerSendsCash: array<int, int>}|null $previousFormData */
        $previousFormData = $pageData['previousFormData'] ?? null;
        /** @var array<string, true> $checkedItems */
        $checkedItems = $previousFormData['checkedItems'] ?? [];

        // Build player + pick rows for both teams, tracking the form field counter
        $k = 0;

        ob_start();
        echo \UI\AlertRenderer::fromCode($pageData['result'] ?? null, \Trading\TradeAlertMap::MAP, $pageData['error'] ?? null);
        ?>
<form name="Trade_Offer" method="post" action="/ibl5/modules/Trading/maketradeoffer.php">
    <?= \Security\CsrfGuard::generateToken('trade_offer') ?>
    <input type="hidden" name="offeringTeam" value="<?= HtmlSanitizer::safeHtmlOutput($userTeam) ?>">
    <div class="trading-layout">
        <h1 class="ibl-title">Trading</h1>
        <div class="team-cards-row">
            <div class="trading-layout__card">
                <details class="trading-roster-details" open>
<?= HtmlSanitizer::trusted($this->renderCardHeader($userTeamId, $userTeam, $userColor1, $userColor2)) ?>
<?= HtmlSanitizer::trusted($this->renderPlayersPanel($pageData['userPlayers'], $userTeamId, $pageData['userTeamColor1'], $pageData['userTeamColor2'], $seasonPhase, $k, $checkedItems)) ?>
<?= HtmlSanitizer::trusted($this->renderPicksPanel($pageData['userPicks'], $k, $checkedItems)) ?>
<?= HtmlSanitizer::trusted($this->renderCashPanel('user', 'userSendsCash', $cashStartYear, $cashEndYear, $seasonEndingYear, $pageData['userTeam'], $previousFormData['userSendsCash'] ?? [])) ?>
                </details>
            </div>
            <div class="trading-layout__card">
<?php $switchCounter = $k; ?>
                <input type="hidden" name="switchCounter" value="<?= HtmlSanitizer::e($switchCounter) ?>">
                <input type="hidden" name="listeningTeam" value="<?= HtmlSanitizer::safeHtmlOutput($partnerTeam) ?>">
                <details class="trading-roster-details" open>
<?= HtmlSanitizer::trusted($this->renderCardHeader($partnerTeamId, $partnerTeam, $partnerColor1, $partnerColor2)) ?>
<?= HtmlSanitizer::trusted($this->renderPlayersPanel($pageData['partnerPlayers'], $partnerTeamId, $pageData['partnerTeamColor1'], $pageData['partnerTeamColor2'], $seasonPhase, $k, $checkedItems)) ?>
<?= HtmlSanitizer::trusted($this->renderPicksPanel($pageData['partnerPicks'], $k, $checkedItems)) ?>
<?= HtmlSanitizer::trusted($this->renderCashPanel('partner', 'partnerSendsCash', $cashStartYear, $cashEndYear, $seasonEndingYear, $pageData['partnerTeam'], $previousFormData['partnerSendsCash'] ?? [])) ?>
                </details>
            </div>
        </div>
<?= HtmlSanitizer::trusted($this->renderRosterPreview($userTeamId, $partnerTeamId, $userTeam, $partnerTeam, $userColor1, $partnerColor1)) ?>
        <div class="trading-layout__submit">
<?php $k--; ?>
            <input type="hidden" name="fieldsCounter" value="<?= HtmlSanitizer::e($k) ?>">
            <button type="submit" class="ibl-btn ibl-btn--primary" id="trade-submit-btn" disabled>Make Trade Offer</button>
        </div>
    </div>
<?php
$tradeConfig = [
    'rosterPreviewApiBaseUrl' => 'modules.php?name=Trading&op=roster-preview-api',
    'userTeam' => $pageData['userTeam'],
    'partnerTeam' => $pageData['partnerTeam'],
    'userTeamId' => $userTeamId,
    'partnerTeamId' => $partnerTeamId,
    'switchCounter' => $switchCounter,
    'userFutureSalary' => $pageData['userFutureSalary']['player'],
    'partnerFutureSalary' => $pageData['partnerFutureSalary']['player'],
    'hardCap' => League::HARD_CAP_MAX,
    'seasonEndingYear' => $seasonEndingYear,
    'seasonPhase' => $seasonPhase,
    'cashStartYear' => $cashStartYear,
    'cashEndYear' => $cashEndYear,
    'userTeamColor1' => $userColor1,
    'partnerTeamColor1' => $partnerColor1,
];
?>
<script>window.IBL_TRADE_CONFIG = <?= json_encode($tradeConfig, JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;</script>
<script src="jslib/trading-roster-tabs.js"></script>
<script src="jslib/trade-roster-preview.js"></script>
<script src="jslib/trade-submit-guard.js"></script>
<script src="jslib/contract-hint.js"></script>
</form>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render one team card's header: the logo summary and the panel tabs.
     * Both colors are already sanitized by the caller.
     */
    private function renderCardHeader(int $teamId, string $teamName, string $color1, string $color2): string
    {
        ob_start();
        ?>
                    <summary class="trading-roster-details__summary" style="<?= TableStyles::inlineTeamVars($color1, $color2) ?>">
                        <img src="images/logo/<?= HtmlSanitizer::e($teamId) ?>.jpg" alt="<?= HtmlSanitizer::safeHtmlOutput($teamName) ?>" class="trading-roster-details__logo">
                        <span class="trading-roster-details__chevron"></span>
                    </summary>
                    <div class="trading-roster-details__tabs ibl-tabs" role="tablist" style="<?= TableStyles::inlineTeamVars($color1, $color1) ?>">
                        <button type="button" class="ibl-tab ibl-tab--active" data-panel="players" role="tab">Players</button>
                        <button type="button" class="ibl-tab" data-panel="picks" role="tab">Picks</button>
                        <button type="button" class="ibl-tab" data-panel="cash" role="tab">Cash</button>
                    </div>
<?php
        return (string) ob_get_clean();
    }

    /**
     * Render one team's players panel (the "Players" tab body).
     * Colors are the raw team colors, as the table style has always received them.
     *
     * @param list<TradingPlayerRow> $players
     * @param int $k Form field counter, advanced in place by buildPlayerRows
     * @param array<string, true> $checkedItems
     */
    private function renderPlayersPanel(array $players, int $teamId, string $rawColor1, string $rawColor2, string $seasonPhase, int &$k, array $checkedItems): string
    {
        ob_start();
        ?>
                    <div class="trading-roster-details__panel trading-roster-details__panel--active" data-panel-id="players">
                        <table class="ibl-data-table trading-roster team-table" data-team-id="<?= HtmlSanitizer::e($teamId) ?>" style="<?= TableStyles::inlineTeamVars($rawColor1, $rawColor2) ?>">
                            <colgroup>
                                <col class="trading-col--checkbox">
                                <col>
                                <col class="trading-col--stat">
                                <col class="trading-col--stat">
                            </colgroup>
                            <tbody>
                                <?= HtmlSanitizer::trusted($this->buildPlayerRows($players, $seasonPhase, $k, $checkedItems)) ?>
                            </tbody>
                        </table>
                    </div>
<?php
        return (string) ob_get_clean();
    }

    /**
     * Render one team's draft-picks panel (the "Picks" tab body).
     *
     * @param list<TradingDraftPickRow> $picks
     * @param int $k Form field counter, advanced in place by buildPickRows
     * @param array<string, true> $checkedItems
     */
    private function renderPicksPanel(array $picks, int &$k, array $checkedItems): string
    {
        ob_start();
        ?>
                    <div class="trading-roster-details__panel" data-panel-id="picks">
                        <table class="ibl-data-table trading-roster" data-no-responsive>
                            <colgroup>
                                <col class="trading-col--checkbox">
                                <col>
                            </colgroup>
                            <tbody>
                                <?= HtmlSanitizer::trusted($this->buildPickRows($picks, $k, $checkedItems)) ?>
                            </tbody>
                        </table>
                    </div>
<?php
        return (string) ob_get_clean();
    }

    /**
     * Render one team's cash panel (the "Cash" tab body).
     *
     * @param array<int, int> $previousCash
     */
    private function renderCashPanel(string $side, string $prefix, int $cashStartYear, int $cashEndYear, int $seasonEndingYear, string $teamName, array $previousCash): string
    {
        ob_start();
        ?>
                    <div class="trading-roster-details__panel" data-panel-id="cash">
                        <table class="ibl-data-table trading-cash-exchange" data-no-responsive data-side="<?= HtmlSanitizer::e($side) ?>">
                            <tbody>
<?= HtmlSanitizer::trusted($this->renderCashRows($prefix, $cashStartYear, $cashEndYear, $seasonEndingYear, $teamName, $previousCash)) ?>
                            </tbody>
                        </table>
                    </div>
<?php
        return (string) ob_get_clean();
    }

    /**
     * Build the player-name link with its thumbnail
     */
    private function renderPlayerCellLink(int $pid, string $rawName): string
    {
        $resolved = PlayerImageHelper::resolvePlayerDisplay($pid, $rawName);

        return '<a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=' . HtmlSanitizer::e($pid) . '">'
            . $resolved['thumbnail'] . HtmlSanitizer::e((string) $resolved['name']) . '</a>';
    }

    /**
     * Build HTML rows for players in the trade form
     *
     * @param list<TradingPlayerRow> $players Player rows from repository
     * @param string $seasonPhase Current season phase
     * @param int $k Form field counter, advanced in place by one per row
     * @param array<string, true> $checkedItems Previously checked items keyed by "type:id"
     */
    private function buildPlayerRows(array $players, string $seasonPhase, int &$k, array $checkedItems = []): string
    {
        $isOffseason = ($seasonPhase === 'Playoffs' || $seasonPhase === 'Draft' || $seasonPhase === 'Free Agency');

        ob_start();
        foreach ($players as $row) {
            $pid = $row['pid'];
            $ordinal = $row['ordinal'] ?? 0;
            $contractAmount = TradeFormContract::amountFor($row, $isOffseason);
            ?>
<tr>
<?php if ($contractAmount !== 0 && $ordinal <= \League\JsbConstants::WAIVERS_ORDINAL):
    $wasChecked = isset($checkedItems['1:' . $pid]);
?>
    <td>
        <input type="hidden" name="index<?= HtmlSanitizer::e($k) ?>" value="<?= HtmlSanitizer::e($pid) ?>">
        <input type="hidden" name="contract<?= HtmlSanitizer::e($k) ?>" value="<?= HtmlSanitizer::e($contractAmount) ?>">
        <input type="hidden" name="type<?= HtmlSanitizer::e($k) ?>" value="1">
        <input type="checkbox" name="check<?= HtmlSanitizer::e($k) ?>"<?= $wasChecked ? ' checked' : '' ?>>
    </td>
<?php else: ?>
    <td>
        <input type="hidden" name="index<?= HtmlSanitizer::e($k) ?>" value="<?= HtmlSanitizer::e($pid) ?>">
        <input type="hidden" name="contract<?= HtmlSanitizer::e($k) ?>" value="<?= HtmlSanitizer::e($contractAmount) ?>">
        <input type="hidden" name="type<?= HtmlSanitizer::e($k) ?>" value="1">
        <input type="hidden" name="check<?= HtmlSanitizer::e($k) ?>">
    </td>
<?php endif; ?>
    <td class="ibl-player-cell"><?= HtmlSanitizer::trusted($this->renderPlayerCellLink((int) $pid, $row['name'])) ?></td>
    <td><?= HtmlSanitizer::safeHtmlOutput($row['pos']) ?></td>
    <td><?= HtmlSanitizer::e($contractAmount) ?></td>
</tr>
            <?php
            $k++;
        }
        $html = (string) ob_get_clean();

        return $html;
    }

    /**
     * Build HTML rows for draft picks in the trade form
     *
     * @param list<TradingDraftPickRow> $picks Draft pick rows from repository
     * @param int $k Form field counter, advanced in place by one per row
     * @param array<string, true> $checkedItems Previously checked items keyed by "type:id"
     */
    private function buildPickRows(array $picks, int &$k, array $checkedItems = []): string
    {

        ob_start();
        foreach ($picks as $row) {
            $pickId = $row['pickid'];
            $pickYear = $row['year'];
            $pickTeamId = $row['teampick_id'];
            $pickRound = $row['round'];
            $pickNotes = $row['notes'];
            ?>
<?php $wasPickChecked = isset($checkedItems['0:' . $pickId]); ?>
<tr>
    <td>
        <input type="hidden" name="index<?= HtmlSanitizer::e($k) ?>" value="<?= HtmlSanitizer::e($pickId) ?>">
        <input type="hidden" name="type<?= HtmlSanitizer::e($k) ?>" value="0">
        <input type="checkbox" name="check<?= HtmlSanitizer::e($k) ?>"<?= $wasPickChecked ? ' checked' : '' ?>>
    </td>
    <td class="ibl-player-cell">
        <img src="images/logo/<?= HtmlSanitizer::safeHtmlOutput($row['teampick']) ?>.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">
        <div>
            <?= HtmlSanitizer::e($pickYear) ?> R<?= HtmlSanitizer::e($pickRound) ?> <a href="./modules.php?name=Team&amp;op=team&amp;teamid=<?= HtmlSanitizer::e($pickTeamId) ?>" class="trading-roster__pick-link"><?= HtmlSanitizer::safeHtmlOutput($row['teampick']) ?></a>
<?php if ($pickNotes !== null && $pickNotes !== ''):
?>
            <div class="draft-picks-list__notes"><?= HtmlSanitizer::safeHtmlOutput($pickNotes) ?></div>
<?php endif; ?>
        </div>
    </td>
</tr>
            <?php
            $k++;
        }
        $html = (string) ob_get_clean();

        return $html;
    }

    /**
     * Render the roster preview panel (hidden initially, shown via JS)
     */
    private function renderRosterPreview(int $userTeamId, int $partnerTeamId, string $userTeam, string $partnerTeam, string $userColor1, string $partnerColor1): string
    {
        $safeUserColor = \UI\TableStyles::sanitizeColor($userColor1);
        $safePartnerColor = \UI\TableStyles::sanitizeColor($partnerColor1);
        ob_start();
        ?>
<div id="trade-roster-preview" class="trade-roster-preview" style="--preview-user-color: #<?= HtmlSanitizer::e($safeUserColor) ?>; --preview-partner-color: #<?= HtmlSanitizer::e($safePartnerColor) ?>;" hidden>
    <div class="trade-roster-preview__header">
        <img src="images/logo/new<?= HtmlSanitizer::e($userTeamId) ?>.png" alt="<?= HtmlSanitizer::safeHtmlOutput($userTeam) ?>" class="trade-roster-preview__logo trade-roster-preview__logo--active" data-team-id="<?= HtmlSanitizer::e($userTeamId) ?>">
        <div class="trade-roster-preview__title">Roster Preview</div>
        <img src="images/logo/new<?= HtmlSanitizer::e($partnerTeamId) ?>.png" alt="<?= HtmlSanitizer::safeHtmlOutput($partnerTeam) ?>" class="trade-roster-preview__logo" data-team-id="<?= HtmlSanitizer::e($partnerTeamId) ?>">
    </div>
    <div class="trade-roster-preview__tabs ibl-tabs" role="tablist" style="<?= TableStyles::inlineTeamVars($safeUserColor, $safeUserColor) ?>">
        <button type="button" class="ibl-tab ibl-tab--active" data-display="ratings" role="tab">Ratings</button>
        <button type="button" class="ibl-tab" data-display="total_s" role="tab">Totals</button>
        <button type="button" class="ibl-tab" data-display="avg_s" role="tab">Averages</button>
        <button type="button" class="ibl-tab" data-display="per36mins" role="tab">Per 36</button>
        <button type="button" class="ibl-tab" data-display="contracts" role="tab">Contracts</button>
    </div>
    <div class="table-scroll-wrapper">
        <div class="table-scroll-container" tabindex="0" role="region" aria-label="Trade roster preview">
            <div class="trade-roster-preview__empty">Select players to preview roster changes</div>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render cash exchange table rows for one team
     *
     * @param array<int, int> $previousCash
     */
    private function renderCashRows(string $prefix, int $startYear, int $endYear, int $seasonEndingYear, string $teamName, array $previousCash): string
    {
        ob_start();
        for ($i = $startYear; $i <= $endYear; $i++):
            $yearLabel = ($seasonEndingYear - 2 + $i) . '-' . ($seasonEndingYear - 1 + $i);
            $prevCash = $previousCash[$i] ?? 0;
            ?>
                <tr>
                    <td>
                        <input type="number" name="<?= HtmlSanitizer::safeHtmlOutput($prefix) ?><?= HtmlSanitizer::e($i) ?>" value="<?= HtmlSanitizer::e($prevCash) ?>" min="0" max="2000" aria-label="<?= HtmlSanitizer::safeHtmlOutput($teamName) ?> cash for <?= HtmlSanitizer::safeHtmlOutput($yearLabel) ?>">
                        for <?= HtmlSanitizer::safeHtmlOutput($yearLabel) ?>
                    </td>
                </tr>
        <?php endfor;
        return (string) ob_get_clean();
    }
}
