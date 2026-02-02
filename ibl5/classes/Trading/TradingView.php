<?php

declare(strict_types=1);

namespace Trading;

use Trading\Contracts\TradingViewInterface;
use Player\PlayerImageHelper;
use UI\TableStyles;
use Utilities\HtmlSanitizer;

/**
 * @see TradingViewInterface
 */
class TradingView implements TradingViewInterface
{
    /**
     * @see TradingViewInterface::renderTradeOfferForm()
     */
    public function renderTradeOfferForm(array $pageData): string
    {
        $userTeam = HtmlSanitizer::safeHtmlOutput($pageData['userTeam']);
        $userTeamId = (int) $pageData['userTeamId'];
        $partnerTeam = HtmlSanitizer::safeHtmlOutput($pageData['partnerTeam']);
        $partnerTeamId = (int) $pageData['partnerTeamId'];
        $seasonEndingYear = (int) $pageData['seasonEndingYear'];
        $seasonPhase = $pageData['seasonPhase'];
        $cashStartYear = (int) $pageData['cashStartYear'];
        $cashEndYear = (int) $pageData['cashEndYear'];

        $userColor1 = TableStyles::sanitizeColor($pageData['userTeamColor1'] ?? '000000');
        $userColor2 = TableStyles::sanitizeColor($pageData['userTeamColor2'] ?? 'ffffff');
        $partnerColor1 = TableStyles::sanitizeColor($pageData['partnerTeamColor1'] ?? '000000');
        $partnerColor2 = TableStyles::sanitizeColor($pageData['partnerTeamColor2'] ?? 'ffffff');

        // Build player + pick rows for both teams, tracking the form field counter
        $k = 0;
        $userPlayerRows = $this->buildPlayerRows($pageData['userPlayers'], $pageData['seasonPhase'], $k);
        $k = $userPlayerRows['nextK'];
        $userPickRows = $this->buildPickRows($pageData['userPicks'], $k);
        $k = $userPickRows['nextK'];

        $switchCounter = $k;

        $partnerPlayerRows = $this->buildPlayerRows($pageData['partnerPlayers'], $pageData['seasonPhase'], $k);
        $k = $partnerPlayerRows['nextK'];
        $partnerPickRows = $this->buildPickRows($pageData['partnerPicks'], $k);
        $k = $partnerPickRows['nextK'];
        $k--;

        ob_start();
        echo $this->renderResultBanner($pageData['result'] ?? null, $pageData['error'] ?? null);
        ?>
<form name="Trade_Offer" method="post" action="/ibl5/modules/Trading/maketradeoffer.php">
    <input type="hidden" name="offeringTeam" value="<?= $userTeam ?>">
    <div style="text-align: center;">
        <img src="images/logo/<?= $userTeamId ?>.jpg" alt="Team Logo" class="team-logo-banner"><br>
        <h2 class="ibl-title">Trading</h2>
        <table class="trading-layout">
            <tr>
                <td style="vertical-align: top;">
                    <table class="ibl-data-table trading-roster team-table" style="--team-color-primary: #<?= $userColor1 ?>; --team-color-secondary: #<?= $userColor2 ?>;">
                        <thead>
                            <tr>
                                <th colspan="4"><img src="images/logo/<?= $userTeamId ?>.jpg" alt="<?= $userTeam ?>" class="team-logo-banner" style="margin-bottom: 0;"></th>
                            </tr>
                            <tr>
                                <th>Select</th>
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
                </td>
                <td style="vertical-align: top;">
                    <input type="hidden" name="switchCounter" value="<?= (int) $switchCounter ?>">
                    <input type="hidden" name="listeningTeam" value="<?= $partnerTeam ?>">
                    <table class="ibl-data-table trading-roster team-table" style="--team-color-primary: #<?= $partnerColor1 ?>; --team-color-secondary: #<?= $partnerColor2 ?>;">
                        <thead>
                            <tr>
                                <th colspan="4"><img src="images/logo/<?= $partnerTeamId ?>.jpg" alt="<?= $partnerTeam ?>" class="team-logo-banner" style="margin-bottom: 0;"></th>
                            </tr>
                            <tr>
                                <th>Select</th>
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
                </td>
            </tr>
<?= $this->renderCapTotals($pageData, $seasonEndingYear, $userTeam, $partnerTeam) ?>
<?= $this->renderCashExchange($seasonEndingYear, $seasonPhase, $cashStartYear, $cashEndYear, $userTeam, $partnerTeam) ?>
            <tr>
                <td colspan="2" style="text-align: center; padding: 1rem;">
                    <input type="hidden" name="fieldsCounter" value="<?= (int) $k ?>">
                    <button type="submit" class="ibl-btn ibl-btn--primary">Make Trade Offer</button>
                </td>
            </tr>
        </table>
    </div>
</form>
        <?php
        return ob_get_clean();
    }

    /**
     * @see TradingViewInterface::renderTradeReview()
     */
    public function renderTradeReview(array $pageData): string
    {
        $userTeam = HtmlSanitizer::safeHtmlOutput($pageData['userTeam']);
        $userTeamId = (int) $pageData['userTeamId'];
        $tradeOffers = $pageData['tradeOffers'];
        $teams = $pageData['teams'];

        ob_start();
        echo $this->renderResultBanner($pageData['result'] ?? null, $pageData['error'] ?? null);
        ?>
<div style="text-align: center;">
    <img src="images/logo/<?= $userTeamId ?>.jpg" alt="Team Logo" class="team-logo-banner">
    <h2 class="ibl-title">Trading</h2>
</div>
<table class="trading-layout" style="margin: 0 auto;">
    <tr>
        <td style="vertical-align: top;">
<?php if (empty($tradeOffers)): ?>
            <p style="padding: 1rem;">No pending trade offers.</p>
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
        return ob_get_clean();
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
            echo ' or they may be <a href="modules.php?name=Waivers&amp;action=drop">Dropped to Waivers</a>.';
        } else {
            echo '<br>The waiver wire is also closed.';
        }
        return ob_get_clean();
    }

    /**
     * @see TradingViewInterface::renderTeamSelectionLinks()
     */
    public function renderTeamSelectionLinks(array $teams): string
    {
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
        $teamId = (int) $team['teamid'];
        $teamName = HtmlSanitizer::safeHtmlOutput($team['name']);
        $fullName = HtmlSanitizer::safeHtmlOutput($team['fullName']);
        $color1 = HtmlSanitizer::safeHtmlOutput($team['color1']);
        $color2 = HtmlSanitizer::safeHtmlOutput($team['color2']);
        ?>
        <tr>
            <td class="ibl-team-cell--colored" style="background-color: #<?= $color1 ?>;">
                <a href="modules.php?name=Trading&amp;op=offertrade&amp;partner=<?= $teamName ?>" class="ibl-team-cell__name" style="color: #<?= $color2 ?>;">
                    <img src="images/logo/new<?= $teamId ?>.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">
                    <span class="ibl-team-cell__text"><?= $fullName ?></span>
                </a>
            </td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
        <?php
        return ob_get_clean();
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
            return '<div class="ibl-alert ibl-alert--error">' . HtmlSanitizer::safeHtmlOutput($error) . '</div>';
        }

        if ($result === null) {
            return '';
        }

        $banners = [
            'offer_sent' => ['class' => 'ibl-alert--success', 'message' => 'Trade offer sent!'],
            'trade_accepted' => ['class' => 'ibl-alert--success', 'message' => 'Trade accepted!'],
            'trade_rejected' => ['class' => 'ibl-alert--info', 'message' => 'Trade offer rejected.'],
            'accept_error' => ['class' => 'ibl-alert--error', 'message' => 'Error processing trade.'],
        ];

        if (!isset($banners[$result])) {
            return '';
        }

        $banner = $banners[$result];
        return '<div class="ibl-alert ' . $banner['class'] . '">' . HtmlSanitizer::safeHtmlOutput($banner['message']) . '</div>';
    }

    /**
     * Build HTML rows for players in the trade form
     *
     * @param array $players Player rows from repository
     * @param string $seasonPhase Current season phase
     * @param int $startK Starting form field counter
     * @return array{html: string, nextK: int}
     */
    private function buildPlayerRows(array $players, string $seasonPhase, int $startK): array
    {
        $k = $startK;
        $isOffseason = ($seasonPhase === 'Playoffs' || $seasonPhase === 'Draft' || $seasonPhase === 'Free Agency');

        ob_start();
        foreach ($players as $row) {
            $pid = (int) $row['pid'];
            $ordinal = (int) $row['ordinal'];
            $contractYear = (int) $row['cy'];
            $playerPosition = HtmlSanitizer::safeHtmlOutput($row['pos']);
            $rawName = (string) $row['name'];
            $hasPipe = str_contains($rawName, '|');
            if ($hasPipe) {
                $rawName = str_replace('|', '', strip_tags($rawName));
            }
            $playerName = HtmlSanitizer::safeHtmlOutput($rawName);
            $thumbnail = $hasPipe ? '' : PlayerImageHelper::renderThumbnail($pid);

            if ($isOffseason) {
                $contractYear++;
            }
            if ($contractYear === 0) {
                $contractYear = 1;
            }

            $contractAmount = ($contractYear < 7) ? (int) $row["cy{$contractYear}"] : 0;
            ?>
<tr>
<?php if ($contractAmount !== 0 && $ordinal <= \JSB::WAIVERS_ORDINAL): ?>
    <td>
        <input type="hidden" name="index<?= $k ?>" value="<?= $pid ?>">
        <input type="hidden" name="contract<?= $k ?>" value="<?= $contractAmount ?>">
        <input type="hidden" name="type<?= $k ?>" value="1">
        <input type="checkbox" name="check<?= $k ?>">
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
        $html = ob_get_clean();

        return ['html' => $html, 'nextK' => $k];
    }

    /**
     * Build HTML rows for draft picks in the trade form
     *
     * @param array $picks Draft pick rows from repository
     * @param int $startK Starting form field counter
     * @return array{html: string, nextK: int}
     */
    private function buildPickRows(array $picks, int $startK): array
    {
        $k = $startK;

        ob_start();
        foreach ($picks as $row) {
            $pickId = (int) $row['pickid'];
            $pickYear = (int) $row['year'];
            $pickTeam = HtmlSanitizer::safeHtmlOutput($row['teampick']);
            $pickRound = (int) $row['round'];
            $pickNotes = $row['notes'] ?? null;
            ?>
<tr>
    <td>
        <input type="hidden" name="index<?= $k ?>" value="<?= $pickId ?>">
        <input type="hidden" name="type<?= $k ?>" value="0">
        <input type="checkbox" name="check<?= $k ?>">
    </td>
    <td></td>
    <td class="ibl-player-cell">
        <img src="images/logo/<?= $pickTeam ?>.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">
        <div>
            <?= $pickYear ?> R<?= $pickRound ?> <span class="ibl-team-cell__text"><?= $pickTeam ?></span>
<?php if ($pickNotes !== null && $pickNotes !== ''): ?>
            <div class="draft-picks-list__notes"><?= HtmlSanitizer::safeHtmlOutput($pickNotes) ?></div>
<?php endif; ?>
        </div>
    </td>
    <td></td>
</tr>
            <?php
            $k++;
        }
        $html = ob_get_clean();

        return ['html' => $html, 'nextK' => $k];
    }

    /**
     * Render the cap totals section of the trade form
     */
    private function renderCapTotals(array $pageData, int $seasonEndingYear, string $userTeam, string $partnerTeam): string
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
<tr><td colspan="2"><table class="ibl-data-table trading-cap-totals" data-no-responsive style="width: 100%; margin-top: 1rem;">
    <thead><tr><th colspan="2">Cap Totals</th></tr></thead>
    <tbody>
<?php for ($z = 0; $z < $seasonsToDisplay; $z++): ?>
    <?php $yearLabel = ($displayEndingYear + $z - 1) . '-' . ($displayEndingYear + $z); ?>
    <tr>
        <td style="text-align: left;"><strong><?= $userTeam ?></strong> in <?= HtmlSanitizer::safeHtmlOutput($yearLabel) ?>: <?= (int) $userFutureSalary['player'][$z] ?></td>
        <td style="text-align: right;"><strong><?= $partnerTeam ?></strong> in <?= HtmlSanitizer::safeHtmlOutput($yearLabel) ?>: <?= (int) $partnerFutureSalary['player'][$z] ?></td>
    </tr>
<?php endfor; ?>
    </tbody>
</table></td></tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the cash exchange section of the trade form
     */
    private function renderCashExchange(int $seasonEndingYear, string $seasonPhase, int $cashStartYear, int $cashEndYear, string $userTeam, string $partnerTeam): string
    {
        ob_start();
        ?>
<tr><td colspan="2"><table class="ibl-data-table trading-cash-exchange" data-no-responsive style="width: 100%; margin-top: 1rem;">
    <thead><tr><th colspan="2">Cash Exchange</th></tr></thead>
    <tbody>
<?php for ($i = $cashStartYear; $i <= $cashEndYear; $i++): ?>
    <?php $yearLabel = ($seasonEndingYear - 2 + $i) . '-' . ($seasonEndingYear - 1 + $i); ?>
    <tr>
        <td style="text-align: left;">
            <strong><?= $userTeam ?></strong> send
            <input type="number" name="userSendsCash<?= $i ?>" value="0" min="0" max="2000" style="width: 80px;">
            for <?= HtmlSanitizer::safeHtmlOutput($yearLabel) ?>
        </td>
        <td style="text-align: right;">
            <strong><?= $partnerTeam ?></strong> send
            <input type="number" name="partnerSendsCash<?= $i ?>" value="0" min="0" max="2000" style="width: 80px;">
            for <?= HtmlSanitizer::safeHtmlOutput($yearLabel) ?>
        </td>
    </tr>
<?php endfor; ?>
    </tbody>
</table></td></tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a single trade offer card with items and action buttons
     */
    private function renderTradeOfferCard(int $offerId, array $offer, string $userTeam): string
    {
        $oppositeTeam = HtmlSanitizer::safeHtmlOutput($offer['oppositeTeam']);

        ob_start();
        ?>
<div class="trade-offer-card" style="margin-bottom: 1rem; padding: 1rem; border: 1px solid var(--gray-200); border-radius: var(--radius-md); background: white;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
        <strong>Trade Offer #<?= $offerId ?></strong>
        <div style="display: flex; gap: 0.5rem;">
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
    </div>
    <div class="trade-offer-items">
<?php foreach ($offer['items'] as $item): ?>
    <?php if ($item['description'] !== ''): ?>
        <p><?= HtmlSanitizer::safeHtmlOutput($item['description']) ?></p>
        <?php if ($item['notes'] !== null): ?>
            <p style="margin-left: 1rem; font-style: italic; color: var(--gray-600);"><?= HtmlSanitizer::safeHtmlOutput($item['notes']) ?></p>
        <?php endif; ?>
    <?php endif; ?>
<?php endforeach; ?>
    </div>
</div>
        <?php
        return ob_get_clean();
    }
}
