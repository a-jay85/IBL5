<?php

declare(strict_types=1);

namespace Trading;

use League\League;
use Trading\Contracts\TradingViewInterface;
use Player\PlayerImageHelper;
use UI\TableStyles;
use UI\TeamCellHelper;
use Utilities\HtmlSanitizer;
use Season\Season;

/**
 * @see TradingViewInterface
 *
 * @phpstan-import-type TradingPlayerRow from \Trading\Contracts\TradeFormRepositoryInterface
 * @phpstan-import-type TradingDraftPickRow from \Trading\Contracts\TradeFormRepositoryInterface
 */
class TradingView implements TradingViewInterface
{
    /**
     * @see TradingViewInterface::renderTradeOfferForm()
     *
     * @param array<string, mixed> $pageData
     */
    public function renderTradeOfferForm(array $pageData): string
    {
        /** @var array{userTeam: string, userTeamId: int, partnerTeam: string, partnerTeamId: int, userPlayers: list<TradingPlayerRow>, userPicks: list<TradingDraftPickRow>, userFutureSalary: array{player: array<int, int>, hold: array<int, int>}, partnerPlayers: list<TradingPlayerRow>, partnerPicks: list<TradingDraftPickRow>, partnerFutureSalary: array{player: array<int, int>, hold: array<int, int>}, seasonEndingYear: int, seasonPhase: string, cashStartYear: int, cashEndYear: int, userTeamColor1: string, userTeamColor2: string, partnerTeamColor1: string, partnerTeamColor2: string, result?: string, error?: string} $pageData */

        $userTeam = HtmlSanitizer::safeHtmlOutput($pageData['userTeam']);
        $userTeamId = $pageData['userTeamId'];
        $partnerTeam = HtmlSanitizer::safeHtmlOutput($pageData['partnerTeam']);
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
        $userPlayerRows = $this->buildPlayerRows($pageData['userPlayers'], $pageData['seasonPhase'], $k, $checkedItems);
        $k = $userPlayerRows['nextK'];
        $userPickRows = $this->buildPickRows($pageData['userPicks'], $k, $checkedItems);
        $k = $userPickRows['nextK'];

        $switchCounter = $k;

        $partnerPlayerRows = $this->buildPlayerRows($pageData['partnerPlayers'], $pageData['seasonPhase'], $k, $checkedItems);
        $k = $partnerPlayerRows['nextK'];
        $partnerPickRows = $this->buildPickRows($pageData['partnerPicks'], $k, $checkedItems);
        $k = $partnerPickRows['nextK'];
        $k--;

        ob_start();
        echo \UI\AlertRenderer::fromCode($pageData['result'] ?? null, [
            'offer_sent' => ['class' => 'ibl-alert--success', 'message' => 'Trade offer sent!'],
            'trade_accepted' => ['class' => 'ibl-alert--success', 'message' => 'Trade accepted!'],
            'trade_rejected' => ['class' => 'ibl-alert--info', 'message' => 'Trade offer rejected.'],
            'accept_error' => ['class' => 'ibl-alert--error', 'message' => 'Error processing trade.'],
            'already_processed' => ['class' => 'ibl-alert--warning', 'message' => 'This trade has already been accepted, declined, or withdrawn.'],
        ], $pageData['error'] ?? null);
        ?>
<form name="Trade_Offer" method="post" action="/ibl5/modules/Trading/maketradeoffer.php">
    <?= \Utilities\CsrfGuard::generateToken('trade_offer') ?>
    <input type="hidden" name="offeringTeam" value="<?= $userTeam ?>">
    <div class="trading-layout">
        <h2 class="ibl-title">Trading</h2>
        <div class="team-cards-row">
            <div class="trading-layout__card">
                <details class="trading-roster-details" open>
                    <summary class="trading-roster-details__summary" style="--team-color-primary: #<?= $userColor1 ?>; --team-color-secondary: #<?= $userColor2 ?>">
                        <img src="images/logo/<?= $userTeamId ?>.jpg" alt="<?= $userTeam ?>" class="trading-roster-details__logo">
                        <span class="trading-roster-details__chevron"></span>
                    </summary>
                    <div class="trading-roster-details__tabs ibl-tabs" role="tablist" style="--team-tab-bg-color: #<?= $userColor1 ?>; --team-tab-active-color: #<?= $userColor1 ?>">
                        <button type="button" class="ibl-tab ibl-tab--active" data-panel="players" role="tab">Players</button>
                        <button type="button" class="ibl-tab" data-panel="picks" role="tab">Picks</button>
                        <button type="button" class="ibl-tab" data-panel="cash" role="tab">Cash</button>
                    </div>
                    <div class="trading-roster-details__panel trading-roster-details__panel--active" data-panel-id="players">
                        <table class="ibl-data-table trading-roster team-table" data-team-id="<?= $userTeamId ?>" style="<?= TableStyles::inlineVars($pageData['userTeamColor1'], $pageData['userTeamColor2']) ?>">
                            <colgroup>
                                <col style="width: 50px;">
                                <col>
                                <col style="width: 40px;">
                                <col style="width: 40px;">
                            </colgroup>
                            <tbody>
                                <?= $userPlayerRows['html'] ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="trading-roster-details__panel" data-panel-id="picks">
                        <table class="ibl-data-table trading-roster" data-no-responsive>
                            <colgroup>
                                <col style="width: 50px;">
                                <col>
                            </colgroup>
                            <tbody>
                                <?= $userPickRows['html'] ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="trading-roster-details__panel" data-panel-id="cash">
                        <table class="ibl-data-table trading-cash-exchange" data-no-responsive data-side="user">
                            <tbody>
<?= $this->renderCashRows('userSendsCash', $cashStartYear, $cashEndYear, $seasonEndingYear, $pageData['userTeam'], $previousFormData['userSendsCash'] ?? []) ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
            <div class="trading-layout__card">
                <input type="hidden" name="switchCounter" value="<?= (int) $switchCounter ?>">
                <input type="hidden" name="listeningTeam" value="<?= $partnerTeam ?>">
                <details class="trading-roster-details" open>
                    <summary class="trading-roster-details__summary" style="--team-color-primary: #<?= $partnerColor1 ?>; --team-color-secondary: #<?= $partnerColor2 ?>">
                        <img src="images/logo/<?= $partnerTeamId ?>.jpg" alt="<?= $partnerTeam ?>" class="trading-roster-details__logo">
                        <span class="trading-roster-details__chevron"></span>
                    </summary>
                    <div class="trading-roster-details__tabs ibl-tabs" role="tablist" style="--team-tab-bg-color: #<?= $partnerColor1 ?>; --team-tab-active-color: #<?= $partnerColor1 ?>">
                        <button type="button" class="ibl-tab ibl-tab--active" data-panel="players" role="tab">Players</button>
                        <button type="button" class="ibl-tab" data-panel="picks" role="tab">Picks</button>
                        <button type="button" class="ibl-tab" data-panel="cash" role="tab">Cash</button>
                    </div>
                    <div class="trading-roster-details__panel trading-roster-details__panel--active" data-panel-id="players">
                        <table class="ibl-data-table trading-roster team-table" data-team-id="<?= $partnerTeamId ?>" style="<?= TableStyles::inlineVars($pageData['partnerTeamColor1'], $pageData['partnerTeamColor2']) ?>">
                            <colgroup>
                                <col style="width: 50px;">
                                <col>
                                <col style="width: 40px;">
                                <col style="width: 40px;">
                            </colgroup>
                            <tbody>
                                <?= $partnerPlayerRows['html'] ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="trading-roster-details__panel" data-panel-id="picks">
                        <table class="ibl-data-table trading-roster" data-no-responsive>
                            <colgroup>
                                <col style="width: 50px;">
                                <col>
                            </colgroup>
                            <tbody>
                                <?= $partnerPickRows['html'] ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="trading-roster-details__panel" data-panel-id="cash">
                        <table class="ibl-data-table trading-cash-exchange" data-no-responsive data-side="partner">
                            <tbody>
<?= $this->renderCashRows('partnerSendsCash', $cashStartYear, $cashEndYear, $seasonEndingYear, $pageData['partnerTeam'], $previousFormData['partnerSendsCash'] ?? []) ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </div>
<?= $this->renderRosterPreview($userTeamId, $partnerTeamId, $userTeam, $partnerTeam, $userColor1, $partnerColor1) ?>
        <div class="trading-layout__submit">
            <input type="hidden" name="fieldsCounter" value="<?= (int) $k ?>">
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
</form>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see TradingViewInterface::renderTradeReview()
     *
     * @param array<string, mixed> $pageData
     */
    public function renderTradeReview(array $pageData): string
    {
        /** @var array{userTeam: string, userTeamId: int, tradeOffers: array<int, array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>, previewData: array{fromPids: list<int>, toPids: list<int>, fromTeamId: int, toTeamId: int, fromColor1: string, toColor1: string, fromCash: array<int, int>, toCash: array<int, int>, cashStartYear: int, cashEndYear: int, seasonEndingYear: int}}>, teams: list<array{name: string, city: string, fullName: string, teamid: int, color1: string, color2: string}>, result?: string, error?: string} $pageData */

        $userTeam = HtmlSanitizer::safeHtmlOutput($pageData['userTeam']);
        $userTeamId = $pageData['userTeamId'];
        $tradeOffers = $pageData['tradeOffers'];
        $teams = $pageData['teams'];

        /** @var array<int, array<string, mixed>> $reviewConfigs */
        $reviewConfigs = [];

        ob_start();
        echo \UI\AlertRenderer::fromCode($pageData['result'] ?? null, [
            'offer_sent' => ['class' => 'ibl-alert--success', 'message' => 'Trade offer sent!'],
            'trade_accepted' => ['class' => 'ibl-alert--success', 'message' => 'Trade accepted!'],
            'trade_rejected' => ['class' => 'ibl-alert--info', 'message' => 'Trade offer rejected.'],
            'accept_error' => ['class' => 'ibl-alert--error', 'message' => 'Error processing trade.'],
            'already_processed' => ['class' => 'ibl-alert--warning', 'message' => 'This trade has already been accepted, declined, or withdrawn.'],
        ], $pageData['error'] ?? null);
        ?>
<div class="trading-layout__header">
    <h2 class="ibl-title">Trading</h2>
    <img src="images/logo/<?= $userTeamId ?>.jpg" alt="Team Logo" class="team-logo-banner">
</div>
<div class="trading-review-wrapper">
    <div class="trading-review-offers">
<?php if ($tradeOffers !== []): ?>
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
        <?= $this->renderTradeOfferCard((int) $offerId, $offer, $userTeam, $userTeamId) ?>
    <?php endforeach; ?>
<?php endif; ?>
    </div>
    <?= $this->renderTeamSelectionLinks($teams) ?>
</div>
<?php if ($reviewConfigs !== []): ?>
<script>window.IBL_TRADE_REVIEW_CONFIGS = <?= json_encode($reviewConfigs, JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;</script>
<script src="jslib/trade-review-preview.js" defer></script>
<?php endif; ?>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see TradingViewInterface::renderTradesClosed()
     */
    public function renderTradesClosed(Season $season): string
    {
        ob_start();
        echo 'Sorry, but trades are not allowed right now.';
        if ($season->areWaiversAllowed()) {
            echo '<br>Players may still be <a href="modules.php?name=Waivers&amp;action=add">Added From Waivers</a>';
            echo ' or they may be <a href="modules.php?name=Waivers&amp;action=waive">Waived</a>.';
        } else {
            echo '<br>The waiver wire is also closed.';
        }
        return (string) ob_get_clean();
    }

    /**
     * @see TradingViewInterface::renderTeamSelectionLinks()
     *
     * @param list<array{name: string, city: string, fullName: string, teamid: int, color1: string, color2: string}> $teams
     */
    public function renderTeamSelectionLinks(array $teams): string
    {
        /** @var list<array{name: string, city: string, fullName: string, teamid: int, color1: string, color2: string}> $teams */

        // Split by conference
        $western = [];
        $eastern = [];
        foreach ($teams as $team) {
            if (in_array($team['teamid'], League::WESTERN_CONFERENCE_TEAMIDS, true)) {
                $western[] = $team;
            } else {
                $eastern[] = $team;
            }
        }

        // Compute mobile order (by team name) before sorting by city for desktop
        $mobileOrder = [];
        $byName = $western;
        usort($byName, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        foreach ($byName as $i => $team) {
            $mobileOrder[$team['teamid']] = $i * 2; // even slots for West
        }
        $byName = $eastern;
        usort($byName, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        foreach ($byName as $i => $team) {
            $mobileOrder[$team['teamid']] = $i * 2 + 1; // odd slots for East
        }

        // Sort by city for desktop display
        usort($western, static fn(array $a, array $b): int => strcasecmp($a['city'], $b['city']));
        usort($eastern, static fn(array $a, array $b): int => strcasecmp($a['city'], $b['city']));

        // Interleave: West[0], East[0], West[1], East[1], ...
        $interleaved = [];
        $count = max(count($western), count($eastern));
        for ($i = 0; $i < $count; $i++) {
            if (isset($western[$i])) {
                $interleaved[] = $western[$i];
            }
            if (isset($eastern[$i])) {
                $interleaved[] = $eastern[$i];
            }
        }

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
<?php foreach ($interleaved as $team): ?>
        <?php
        $teamId = $team['teamid'];
        $teamName = HtmlSanitizer::safeHtmlOutput($team['name']);
        $partnerUrl = 'modules.php?name=Trading&amp;op=offertrade&amp;partner=' . $teamName;
        $cityHtml = HtmlSanitizer::safeHtmlOutput($team['city']);
        $nameHtml = '<span class="ibl-team-cell__city">' . $cityHtml . ' </span>' . $teamName;
        $cell = TeamCellHelper::renderTeamCell($teamId, $team['fullName'], $team['color1'], $team['color2'], '', $partnerUrl, $nameHtml);
        $cell = str_replace('style="', 'style="--mobile-order: ' . $mobileOrder[$teamId] . '; ', $cell);
        ?>
        <tr>
            <?= $cell ?>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Build HTML rows for players in the trade form
     *
     * @param list<TradingPlayerRow> $players Player rows from repository
     * @param string $seasonPhase Current season phase
     * @param int $startK Starting form field counter
     * @param array<string, true> $checkedItems Previously checked items keyed by "type:id"
     * @return array{html: string, nextK: int}
     */
    private function buildPlayerRows(array $players, string $seasonPhase, int $startK, array $checkedItems = []): array
    {
        $k = $startK;
        $isOffseason = ($seasonPhase === 'Playoffs' || $seasonPhase === 'Draft' || $seasonPhase === 'Free Agency');

        ob_start();
        foreach ($players as $row) {
            $pid = $row['pid'];
            $ordinal = $row['ordinal'] ?? 0;
            $contractYear = $row['cy'] ?? 0;
            $playerPosition = HtmlSanitizer::safeHtmlOutput($row['pos']);
            $resolved = PlayerImageHelper::resolvePlayerDisplay($pid, $row['name']);
            $playerName = HtmlSanitizer::safeHtmlOutput((string) $resolved['name']);
            /** @var string $thumbnail */
            $thumbnail = $resolved['thumbnail'];

            if ($isOffseason) {
                $contractYear++;
            }
            if ($contractYear === 0) {
                $contractYear = 1;
            }

            /** @var int $contractAmount */
            $contractAmount = ($contractYear < 7) ? ($row["cy{$contractYear}"] ?? 0) : 0;
            ?>
<tr>
<?php if ($contractAmount !== 0 && $ordinal <= \JSB::WAIVERS_ORDINAL):
    $wasChecked = isset($checkedItems['1:' . $pid]);
?>
    <td>
        <input type="hidden" name="index<?= $k ?>" value="<?= $pid ?>">
        <input type="hidden" name="contract<?= $k ?>" value="<?= $contractAmount ?>">
        <input type="hidden" name="type<?= $k ?>" value="1">
        <input type="checkbox" name="check<?= $k ?>"<?= $wasChecked ? ' checked' : '' ?>>
    </td>
<?php else: ?>
    <td>
        <input type="hidden" name="index<?= $k ?>" value="<?= $pid ?>">
        <input type="hidden" name="contract<?= $k ?>" value="<?= $contractAmount ?>">
        <input type="hidden" name="type<?= $k ?>" value="1">
        <input type="hidden" name="check<?= $k ?>">
    </td>
<?php endif; ?>
    <td class="ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=<?= $pid ?>"><?= $thumbnail ?><?= $playerName ?></a></td>
    <td><?= $playerPosition ?></td>
    <td><?= $contractAmount ?></td>
</tr>
            <?php
            $k++;
        }
        $html = (string) ob_get_clean();

        return ['html' => $html, 'nextK' => $k];
    }

    /**
     * Build HTML rows for draft picks in the trade form
     *
     * @param list<TradingDraftPickRow> $picks Draft pick rows from repository
     * @param int $startK Starting form field counter
     * @param array<string, true> $checkedItems Previously checked items keyed by "type:id"
     * @return array{html: string, nextK: int}
     */
    private function buildPickRows(array $picks, int $startK, array $checkedItems = []): array
    {
        $k = $startK;

        ob_start();
        foreach ($picks as $row) {
            $pickId = $row['pickid'];
            $pickYear = $row['year'];
            $pickTeam = HtmlSanitizer::safeHtmlOutput($row['teampick']);
            $pickTeamId = $row['teampick_id'];
            $pickRound = $row['round'];
            $pickNotes = $row['notes'];
            ?>
<?php $wasPickChecked = isset($checkedItems['0:' . $pickId]); ?>
<tr>
    <td>
        <input type="hidden" name="index<?= $k ?>" value="<?= $pickId ?>">
        <input type="hidden" name="type<?= $k ?>" value="0">
        <input type="checkbox" name="check<?= $k ?>"<?= $wasPickChecked ? ' checked' : '' ?>>
    </td>
    <td class="ibl-player-cell">
        <img src="images/logo/<?= $pickTeam ?>.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">
        <div>
            <?= $pickYear ?> R<?= $pickRound ?> <a href="./modules.php?name=Team&amp;op=team&amp;teamID=<?= $pickTeamId ?>" class="trading-roster__pick-link"><?= $pickTeam ?></a>
<?php if ($pickNotes !== null && $pickNotes !== ''):
    $pickNotesEscaped = HtmlSanitizer::safeHtmlOutput($pickNotes);
?>
            <div class="draft-picks-list__notes"><?= $pickNotesEscaped ?></div>
<?php endif; ?>
        </div>
    </td>
</tr>
            <?php
            $k++;
        }
        $html = (string) ob_get_clean();

        return ['html' => $html, 'nextK' => $k];
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
<div id="trade-roster-preview" class="trade-roster-preview" style="display: none; --preview-user-color: #<?= $safeUserColor ?>; --preview-partner-color: #<?= $safePartnerColor ?>;">
    <div class="trade-roster-preview__header">
        <img src="images/logo/new<?= $userTeamId ?>.png" alt="<?= $userTeam ?>" class="trade-roster-preview__logo trade-roster-preview__logo--active" data-team-id="<?= $userTeamId ?>">
        <div class="trade-roster-preview__title">Roster Preview</div>
        <img src="images/logo/new<?= $partnerTeamId ?>.png" alt="<?= $partnerTeam ?>" class="trade-roster-preview__logo" data-team-id="<?= $partnerTeamId ?>">
    </div>
    <div class="trade-roster-preview__tabs ibl-tabs" role="tablist" style="--team-tab-bg-color: #<?= $safeUserColor ?>; --team-tab-active-color: #<?= $safeUserColor ?>">
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
            $yearLabelEscaped = HtmlSanitizer::safeHtmlOutput($yearLabel);
            $prevCash = $previousCash[$i] ?? 0;
            ?>
                <tr>
                    <td>
                        <input type="number" name="<?= HtmlSanitizer::safeHtmlOutput($prefix) ?><?= $i ?>" value="<?= $prevCash ?>" min="0" max="2000" aria-label="<?= HtmlSanitizer::safeHtmlOutput($teamName) ?> cash for <?= $yearLabelEscaped ?>">
                        for <?= $yearLabelEscaped ?>
                    </td>
                </tr>
        <?php endfor;
        return (string) ob_get_clean();
    }

    /**
     * Render a single trade offer card with items, action buttons, and preview panel
     *
     * @param array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>, previewData: array{fromPids: list<int>, toPids: list<int>, fromTeamId: int, toTeamId: int, fromColor1: string, toColor1: string, fromCash: array<int, int>, toCash: array<int, int>, cashStartYear: int, cashEndYear: int, seasonEndingYear: int}} $offer
     */
    private function renderTradeOfferCard(int $offerId, array $offer, string $userTeam, int $userTeamId): string
    {
        $oppositeTeam = HtmlSanitizer::safeHtmlOutput($offer['oppositeTeam']);

        ob_start();
        ?>
<div class="trade-offer-card">
    <div class="trade-offer-card__header">
        <strong>Trade Offer #<?= $offerId ?></strong>
    </div>
    <div class="trade-offer-card__actions">
<?php if ($offer['hasHammer']): ?>
        <form name="tradeaccept" method="post" action="/ibl5/modules/Trading/accepttradeoffer.php" class="trade-offer-card__form">
            <?= \Utilities\CsrfGuard::generateToken('trade_accept') ?>
            <input type="hidden" name="offer" value="<?= $offerId ?>">
            <button type="submit" class="ibl-btn ibl-btn--success">Accept</button>
        </form>
<?php else: ?>
        <span class="trade-offer-card__awaiting">Awaiting Approval</span>
<?php endif; ?>
        <form name="tradereject" method="post" action="/ibl5/modules/Trading/rejecttradeoffer.php" class="trade-offer-card__form">
            <?= \Utilities\CsrfGuard::generateToken('trade_reject') ?>
            <input type="hidden" name="offer" value="<?= $offerId ?>">
            <input type="hidden" name="teamRejecting" value="<?= $userTeam ?>">
            <input type="hidden" name="teamReceiving" value="<?= $oppositeTeam ?>">
            <button type="submit" class="ibl-btn ibl-btn--danger">Reject</button>
        </form>
    </div>
    <div class="trade-offer-items">
<?php foreach ($offer['items'] as $item): ?>
    <?php if ($item['description'] !== ''):
        $descriptionEscaped = HtmlSanitizer::safeHtmlOutput($item['description']);
    ?>
        <p><?= $descriptionEscaped ?></p>
        <?php if ($item['notes'] !== null):
            $notesEscaped = HtmlSanitizer::safeHtmlOutput($item['notes']);
        ?>
            <p class="trade-offer-card__notes"><?= $notesEscaped ?></p>
        <?php endif; ?>
    <?php endif; ?>
<?php endforeach; ?>
    </div>
    <div class="trade-offer-card__preview-wrap">
        <button type="button" class="ibl-btn ibl-btn--neutral ibl-btn--sm" data-preview-offer="<?= $offerId ?>">Preview</button>
    </div>
</div>
<?= $this->renderReviewRosterPreview($offerId, $offer['previewData'], $userTeamId) ?>
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
<div id="trade-review-preview-<?= $offerId ?>" class="trade-roster-preview" style="display: none; --preview-user-color: #<?= $safeFromColor ?>; --preview-partner-color: #<?= $safeToColor ?>;">
    <div class="trade-roster-preview__header">
        <img src="images/logo/new<?= $fromTeamId ?>.png" alt="From Team" class="trade-roster-preview__logo<?= $initialTeamId === $fromTeamId ? ' trade-roster-preview__logo--active' : '' ?>" data-team-id="<?= $fromTeamId ?>">
        <div class="trade-roster-preview__title">Roster Preview</div>
        <img src="images/logo/new<?= $toTeamId ?>.png" alt="To Team" class="trade-roster-preview__logo<?= $initialTeamId === $toTeamId ? ' trade-roster-preview__logo--active' : '' ?>" data-team-id="<?= $toTeamId ?>">
    </div>
    <div class="trade-roster-preview__tabs ibl-tabs" role="tablist" style="--team-tab-bg-color: #<?= $initialColor ?>; --team-tab-active-color: #<?= $initialColor ?>">
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
