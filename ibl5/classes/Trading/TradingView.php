<?php

declare(strict_types=1);

namespace Trading;

use Trading\Contracts\TradingViewInterface;
use Player\PlayerImageHelper;
use UI\TableStyles;
use UI\TeamCellHelper;
use Utilities\HtmlSanitizer;

/**
 * @see TradingViewInterface
 *
 * @phpstan-import-type TradingPlayerRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type TradingDraftPickRow from \Trading\Contracts\TradingRepositoryInterface
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
        /** @var array{userTeam: string, userTeamId: int, partnerTeam: string, partnerTeamId: int, userPlayers: list<TradingPlayerRow>, userPicks: list<TradingDraftPickRow>, userFutureSalary: array{player: array<int, int>, hold: array<int, int>}, partnerPlayers: list<TradingPlayerRow>, partnerPicks: list<TradingDraftPickRow>, partnerFutureSalary: array{player: array<int, int>, hold: array<int, int>}, seasonEndingYear: int, seasonPhase: string, cashStartYear: int, cashEndYear: int, userTeamColor1: string, userTeamColor2: string, partnerTeamColor1: string, partnerTeamColor2: string, userPlayerContracts: array<int, list<int>>, partnerPlayerContracts: array<int, list<int>>, comparisonDropdownGroups: array<string, array<string, string>>, result?: string, error?: string} $pageData */

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
        echo $this->renderResultBanner($pageData['result'] ?? null, $pageData['error'] ?? null);
        ?>
<form name="Trade_Offer" method="post" action="/ibl5/modules/Trading/maketradeoffer.php">
    <input type="hidden" name="offeringTeam" value="<?= $userTeam ?>">
    <div class="trading-layout">
        <h2 class="ibl-title">Trading</h2>
        <div class="team-cards-row">
            <div class="trading-layout__card">
                <table class="ibl-data-table trading-roster team-table" style="--team-color-primary: #<?= $userColor1 ?>; --team-color-secondary: #<?= $userColor2 ?>;">
                    <colgroup>
                        <col style="width: 50px;">
                        <col style="width: 40px;">
                        <col>
                        <col style="width: 70px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th colspan="4"><img src="images/logo/<?= $userTeamId ?>.jpg" alt="<?= $userTeam ?>" class="team-logo-banner" style="margin-bottom: 0;"></th>
                        </tr>
                        <tr>
                            <th></th>
                            <th>Pos</th>
                            <th>Name</th>
                            <th>Salary</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?= $userPlayerRows['html'] ?>
                        <?= $userPickRows['html'] ?>
                    </tbody>
                </table>
            </div>
            <div class="trading-layout__card">
                <input type="hidden" name="switchCounter" value="<?= (int) $switchCounter ?>">
                <input type="hidden" name="listeningTeam" value="<?= $partnerTeam ?>">
                <table class="ibl-data-table trading-roster team-table" style="--team-color-primary: #<?= $partnerColor1 ?>; --team-color-secondary: #<?= $partnerColor2 ?>;">
                    <colgroup>
                        <col style="width: 50px;">
                        <col style="width: 40px;">
                        <col>
                        <col style="width: 70px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th colspan="4"><img src="images/logo/<?= $partnerTeamId ?>.jpg" alt="<?= $partnerTeam ?>" class="team-logo-banner" style="margin-bottom: 0;"></th>
                        </tr>
                        <tr>
                            <th></th>
                            <th>Pos</th>
                            <th>Name</th>
                            <th>Salary</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?= $partnerPlayerRows['html'] ?>
                        <?= $partnerPickRows['html'] ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="trade-comparison-panel" class="trade-comparison" style="display: none;">
            <div class="trade-comparison__header">Player Comparison</div>
<?php
/** @var array<string, array<string, string>> $comparisonDropdownGroups */
$comparisonDropdownGroups = $pageData['comparisonDropdownGroups'] ?? [];
if ($comparisonDropdownGroups !== []):
?>
            <div class="trade-comparison__shared-dropdown">
                <select id="trade-comparison-display" class="ibl-view-select">
<?php foreach ($comparisonDropdownGroups as $groupLabel => $options): ?>
                    <optgroup label="<?= HtmlSanitizer::safeHtmlOutput($groupLabel) ?>">
<?php foreach ($options as $value => $label): ?>
                        <option value="<?= HtmlSanitizer::safeHtmlOutput($value) ?>"<?= $value === 'ratings' ? ' selected' : '' ?>><?= HtmlSanitizer::safeHtmlOutput($label) ?></option>
<?php endforeach; ?>
                    </optgroup>
<?php endforeach; ?>
                </select>
            </div>
<?php endif; ?>
            <div class="trade-comparison__grid">
                <div class="trade-comparison__team" data-side="user">
                    <div class="trade-comparison__team-name"><?= $userTeam ?> sends</div>
                    <div class="table-scroll-wrapper"><div class="table-scroll-container">
                        <div class="trade-comparison__empty">No players selected</div>
                    </div></div>
                </div>
                <div class="trade-comparison__team" data-side="partner">
                    <div class="trade-comparison__team-name"><?= $partnerTeam ?> sends</div>
                    <div class="table-scroll-wrapper"><div class="table-scroll-container">
                        <div class="trade-comparison__empty">No players selected</div>
                    </div></div>
                </div>
            </div>
        </div>
<?= $this->renderRosterPreview($userTeamId, $partnerTeamId, $userTeam, $partnerTeam) ?>
<?= $this->renderCashExchange($seasonEndingYear, $seasonPhase, $cashStartYear, $cashEndYear, $userTeam, $partnerTeam, $userColor1, $userColor2, $partnerColor1, $partnerColor2, $previousFormData) ?>
<?= $this->renderCapTotals($pageData, $seasonEndingYear, $userTeam, $partnerTeam, $userColor1, $userColor2, $partnerColor1, $partnerColor2) ?>
        <div style="text-align: center; padding: 1rem;">
            <input type="hidden" name="fieldsCounter" value="<?= (int) $k ?>">
            <button type="submit" class="ibl-btn ibl-btn--primary">Make Trade Offer</button>
        </div>
    </div>
<?php
$tradeConfig = [
    'apiBaseUrl' => 'modules.php?name=Trading&op=comparison-api',
    'rosterPreviewApiBaseUrl' => 'modules.php?name=Trading&op=roster-preview-api',
    'userTeam' => $pageData['userTeam'],
    'partnerTeam' => $pageData['partnerTeam'],
    'userTeamId' => $userTeamId,
    'partnerTeamId' => $partnerTeamId,
    'switchCounter' => $switchCounter,
    'userPlayerContracts' => $pageData['userPlayerContracts'],
    'partnerPlayerContracts' => $pageData['partnerPlayerContracts'],
    'userFutureSalary' => $pageData['userFutureSalary']['player'],
    'partnerFutureSalary' => $pageData['partnerFutureSalary']['player'],
    'hardCap' => \League::HARD_CAP_MAX,
    'seasonEndingYear' => $seasonEndingYear,
    'seasonPhase' => $seasonPhase,
    'cashStartYear' => $cashStartYear,
    'cashEndYear' => $cashEndYear,
];
?>
<script>window.IBL_TRADE_CONFIG = <?= json_encode($tradeConfig, JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;</script>
<script src="jslib/trade-comparison.js" defer></script>
<script src="jslib/trade-roster-preview.js" defer></script>
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
        /** @var array{userTeam: string, userTeamId: int, tradeOffers: array<int, array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>}>, teams: list<array{name: string, city: string, fullName: string, teamid: int, color1: string, color2: string}>, result?: string, error?: string} $pageData */

        $userTeam = HtmlSanitizer::safeHtmlOutput($pageData['userTeam']);
        $userTeamId = $pageData['userTeamId'];
        $tradeOffers = $pageData['tradeOffers'];
        $teams = $pageData['teams'];

        ob_start();
        echo $this->renderResultBanner($pageData['result'] ?? null, $pageData['error'] ?? null);
        ?>
<div style="text-align: center;">
    <h2 class="ibl-title">Trading</h2>
    <img src="images/logo/<?= $userTeamId ?>.jpg" alt="Team Logo" class="team-logo-banner">
</div>
<table class="trading-layout" style="margin: 0 auto;">
    <tr>
        <td style="vertical-align: top;">
<?php if ($tradeOffers === []): ?>
            <p style="padding: 1rem; text-align: center;">No pending trade offers.</p>
<?php else: ?>
    <?php foreach ($tradeOffers as $offerId => $offer): ?>
            <?= $this->renderTradeOfferCard((int) $offerId, $offer, $userTeam) ?>
    <?php endforeach; ?>
<?php endif; ?>
        </td>
    </tr>
    <tr>
        <td>
            <?= $this->renderTeamSelectionLinks($teams) ?>
        </td>
    </tr>
</table>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see TradingViewInterface::renderTradesClosed()
     */
    public function renderTradesClosed(\Season $season): string
    {
        ob_start();
        echo 'Sorry, but trades are not allowed right now.';
        if ($season->allowWaivers === 'Yes') {
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
        ob_start();
        ?>
<table class="ibl-data-table trading-team-select">
    <thead>
        <tr>
            <th>Make Trade Offer To...</th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($teams as $team): ?>
        <?php
        $teamId = $team['teamid'];
        $teamName = HtmlSanitizer::safeHtmlOutput($team['name']);
        $fullName = HtmlSanitizer::safeHtmlOutput($team['fullName']);
        $partnerUrl = 'modules.php?name=Trading&amp;op=offertrade&amp;partner=' . $teamName;
        ?>
        <tr>
            <?= TeamCellHelper::renderTeamCell($teamId, $team['fullName'], $team['color1'], $team['color2'], '', $partnerUrl) ?>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render a result banner from PRG redirect query params
     *
     * @param string|null $result Result code from query parameter
     * @param string|null $error Error message from query parameter
     * @return string HTML alert banner or empty string
     */
    private function renderResultBanner(?string $result, ?string $error): string
    {
        if ($error !== null) {
            $errorEscaped = HtmlSanitizer::safeHtmlOutput($error);
            return '<div class="ibl-alert ibl-alert--error">' . $errorEscaped . '</div>';
        }

        if ($result === null) {
            return '';
        }

        $banners = [
            'offer_sent' => ['class' => 'ibl-alert--success', 'message' => 'Trade offer sent!'],
            'trade_accepted' => ['class' => 'ibl-alert--success', 'message' => 'Trade accepted!'],
            'trade_rejected' => ['class' => 'ibl-alert--info', 'message' => 'Trade offer rejected.'],
            'accept_error' => ['class' => 'ibl-alert--error', 'message' => 'Error processing trade.'],
            'already_processed' => ['class' => 'ibl-alert--warning', 'message' => 'This trade has already been accepted, declined, or withdrawn.'],
        ];

        if (!isset($banners[$result])) {
            return '';
        }

        $banner = $banners[$result];
        $messageEscaped = HtmlSanitizer::safeHtmlOutput($banner['message']);
        return '<div class="ibl-alert ' . $banner['class'] . '">' . $messageEscaped . '</div>';
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
    <td><?= $playerPosition ?></td>
    <td class="ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=<?= $pid ?>"><?= $thumbnail ?><?= $playerName ?></a></td>
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
    <td></td>
    <td class="ibl-player-cell">
        <img src="images/logo/<?= $pickTeam ?>.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">
        <div>
            <?= $pickYear ?> R<?= $pickRound ?> <a href="./modules.php?name=Team&amp;op=team&amp;teamID=<?= $pickTeamId ?>"><span class="ibl-team-cell__text"><?= $pickTeam ?></span></a>
<?php if ($pickNotes !== null && $pickNotes !== ''):
    $pickNotesEscaped = HtmlSanitizer::safeHtmlOutput($pickNotes);
?>
            <div class="draft-picks-list__notes"><?= $pickNotesEscaped ?></div>
<?php endif; ?>
        </div>
    </td>
    <td></td>
</tr>
            <?php
            $k++;
        }
        $html = (string) ob_get_clean();

        return ['html' => $html, 'nextK' => $k];
    }

    /**
     * Render the cap totals section of the trade form
     *
     * @param array{userFutureSalary: array{player: array<int, int>, hold: array<int, int>}, partnerFutureSalary: array{player: array<int, int>, hold: array<int, int>}, seasonPhase: string} $pageData
     */
    private function renderCapTotals(array $pageData, int $seasonEndingYear, string $userTeam, string $partnerTeam, string $userColor1, string $userColor2, string $partnerColor1, string $partnerColor2): string
    {
        $userFutureSalary = $pageData['userFutureSalary'];
        $partnerFutureSalary = $pageData['partnerFutureSalary'];
        $seasonPhase = $pageData['seasonPhase'];

        $displayEndingYear = $seasonEndingYear;
        $seasonsToDisplay = 6;
        $isOffseason = ($seasonPhase === 'Playoffs' || $seasonPhase === 'Draft' || $seasonPhase === 'Free Agency');
        if ($isOffseason) {
            $displayEndingYear++;
            $seasonsToDisplay--;
        }

        ob_start();
        ?>
<div class="team-cards-row">
    <div class="team-card" style="--team-color-primary: #<?= $userColor1 ?>; --team-color-secondary: #<?= $userColor2 ?>;">
        <div class="team-card__header"><h3 class="team-card__title"><?= $userTeam ?> &mdash; Cap Totals</h3></div>
        <div class="team-card__body--flush">
            <table class="ibl-data-table trading-cap-totals" data-no-responsive data-side="user">
                <tbody>
<?php for ($z = 0; $z < $seasonsToDisplay; $z++):
    $yearLabel = ($displayEndingYear + $z - 1) . '-' . ($displayEndingYear + $z);
    $yearLabelEscaped = HtmlSanitizer::safeHtmlOutput($yearLabel);
?>
                <tr>
                    <td><?= $yearLabelEscaped ?>: <?= $userFutureSalary['player'][$z] ?></td>
                </tr>
<?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="team-card" style="--team-color-primary: #<?= $partnerColor1 ?>; --team-color-secondary: #<?= $partnerColor2 ?>;">
        <div class="team-card__header"><h3 class="team-card__title"><?= $partnerTeam ?> &mdash; Cap Totals</h3></div>
        <div class="team-card__body--flush">
            <table class="ibl-data-table trading-cap-totals" data-no-responsive data-side="partner">
                <tbody>
<?php for ($z = 0; $z < $seasonsToDisplay; $z++):
    $yearLabel = ($displayEndingYear + $z - 1) . '-' . ($displayEndingYear + $z);
    $yearLabelEscaped = HtmlSanitizer::safeHtmlOutput($yearLabel);
?>
                <tr>
                    <td><?= $yearLabelEscaped ?>: <?= $partnerFutureSalary['player'][$z] ?></td>
                </tr>
<?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the roster preview panel (hidden initially, shown via JS)
     */
    private function renderRosterPreview(int $userTeamId, int $partnerTeamId, string $userTeam, string $partnerTeam): string
    {
        ob_start();
        ?>
<div id="trade-roster-preview" class="trade-roster-preview" style="display: none;">
    <div class="trade-roster-preview__header">
        <img src="images/logo/<?= $userTeamId ?>.jpg" alt="<?= $userTeam ?>" class="trade-roster-preview__logo trade-roster-preview__logo--active" data-team-id="<?= $userTeamId ?>">
        <div class="trade-roster-preview__title">Roster Preview</div>
        <img src="images/logo/<?= $partnerTeamId ?>.jpg" alt="<?= $partnerTeam ?>" class="trade-roster-preview__logo" data-team-id="<?= $partnerTeamId ?>">
    </div>
    <div class="table-scroll-wrapper">
        <div class="table-scroll-container">
            <div class="trade-roster-preview__empty">Select players to preview roster changes</div>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the cash exchange section of the trade form
     *
     * @param array{checkedItems: array<string, true>, userSendsCash: array<int, int>, partnerSendsCash: array<int, int>}|null $previousFormData
     */
    private function renderCashExchange(int $seasonEndingYear, string $seasonPhase, int $cashStartYear, int $cashEndYear, string $userTeam, string $partnerTeam, string $userColor1, string $userColor2, string $partnerColor1, string $partnerColor2, ?array $previousFormData = null): string
    {
        ob_start();
        ?>
<div class="team-cards-row">
    <div class="team-card" style="--team-color-primary: #<?= $userColor1 ?>; --team-color-secondary: #<?= $userColor2 ?>;">
        <div class="team-card__header"><h3 class="team-card__title"><?= $userTeam ?> &mdash; Cash Exchange</h3></div>
        <div class="team-card__body--flush">
            <table class="ibl-data-table trading-cash-exchange" data-no-responsive data-side="user">
                <tbody>
<?php for ($i = $cashStartYear; $i <= $cashEndYear; $i++):
    $yearLabel = ($seasonEndingYear - 2 + $i) . '-' . ($seasonEndingYear - 1 + $i);
    $yearLabelEscaped = HtmlSanitizer::safeHtmlOutput($yearLabel);
    $prevUserCash = $previousFormData['userSendsCash'][$i] ?? 0;
?>
                <tr>
                    <td>
                        <input type="number" name="userSendsCash<?= $i ?>" value="<?= $prevUserCash ?>" min="0" max="2000">
                        for <?= $yearLabelEscaped ?>
                    </td>
                </tr>
<?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="team-card" style="--team-color-primary: #<?= $partnerColor1 ?>; --team-color-secondary: #<?= $partnerColor2 ?>;">
        <div class="team-card__header"><h3 class="team-card__title"><?= $partnerTeam ?> &mdash; Cash Exchange</h3></div>
        <div class="team-card__body--flush">
            <table class="ibl-data-table trading-cash-exchange" data-no-responsive data-side="partner">
                <tbody>
<?php for ($i = $cashStartYear; $i <= $cashEndYear; $i++):
    $yearLabel = ($seasonEndingYear - 2 + $i) . '-' . ($seasonEndingYear - 1 + $i);
    $yearLabelEscaped = HtmlSanitizer::safeHtmlOutput($yearLabel);
    $prevPartnerCash = $previousFormData['partnerSendsCash'][$i] ?? 0;
?>
                <tr>
                    <td>
                        <input type="number" name="partnerSendsCash<?= $i ?>" value="<?= $prevPartnerCash ?>" min="0" max="2000">
                        for <?= $yearLabelEscaped ?>
                    </td>
                </tr>
<?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render a single trade offer card with items and action buttons
     *
     * @param array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>} $offer
     */
    private function renderTradeOfferCard(int $offerId, array $offer, string $userTeam): string
    {
        $oppositeTeam = HtmlSanitizer::safeHtmlOutput($offer['oppositeTeam']);

        ob_start();
        ?>
<div class="trade-offer-card" style="margin-bottom: 1rem; padding: 1rem; border: 1px solid var(--gray-200); border-radius: var(--radius-md); background: white;">
    <div style="margin-bottom: 0.5rem;">
        <strong>Trade Offer #<?= $offerId ?></strong>
    </div>
    <div style="display: flex; justify-content: center; gap: 0.5rem; margin-bottom: 0.5rem;">
<?php if ($offer['hasHammer']): ?>
        <form name="tradeaccept" method="post" action="/ibl5/modules/Trading/accepttradeoffer.php" style="margin: 0;">
            <input type="hidden" name="offer" value="<?= $offerId ?>">
            <button type="submit" class="ibl-btn ibl-btn--success" onclick="this.disabled=true;this.textContent='Submitting...'; this.form.submit();">Accept</button>
        </form>
<?php else: ?>
        <span style="color: var(--gray-500); font-style: italic;">Awaiting Approval</span>
<?php endif; ?>
        <form name="tradereject" method="post" action="/ibl5/modules/Trading/rejecttradeoffer.php" style="margin: 0;">
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
            <p style="margin-left: 1rem; font-style: italic; color: var(--gray-600);"><?= $notesEscaped ?></p>
        <?php endif; ?>
    <?php endif; ?>
<?php endforeach; ?>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
