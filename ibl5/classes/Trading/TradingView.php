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
        /** @var array{userTeam: string, userTeamId: int, partnerTeam: string, partnerTeamId: int, userPlayers: list<TradingPlayerRow>, userPicks: list<TradingDraftPickRow>, userFutureSalary: array{player: array<int, int>, hold: array<int, int>}, partnerPlayers: list<TradingPlayerRow>, partnerPicks: list<TradingDraftPickRow>, partnerFutureSalary: array{player: array<int, int>, hold: array<int, int>}, seasonEndingYear: int, seasonPhase: string, cashStartYear: int, cashEndYear: int, userTeamColor1: string, userTeamColor2: string, partnerTeamColor1: string, partnerTeamColor2: string, result?: string, error?: string} $pageData */

        /** @var string $userTeam */
        $userTeam = HtmlSanitizer::safeHtmlOutput($pageData['userTeam']);
        $userTeamId = $pageData['userTeamId'];
        /** @var string $partnerTeam */
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
    <div class="trading-layout">
        <h2 class="ibl-title">Trading</h2>
        <div class="trading-layout__rosters">
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
            </div>
        </div>
<?= $this->renderCapTotals($pageData, $seasonEndingYear, $userTeam, $partnerTeam) ?>
<?= $this->renderCashExchange($seasonEndingYear, $seasonPhase, $cashStartYear, $cashEndYear, $userTeam, $partnerTeam) ?>
        <div style="text-align: center; padding: 1rem;">
            <input type="hidden" name="fieldsCounter" value="<?= (int) $k ?>">
            <button type="submit" class="ibl-btn ibl-btn--primary">Make Trade Offer</button>
        </div>
    </div>
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

        /** @var string $userTeam */
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
        /** @var string $teamName */
        $teamName = HtmlSanitizer::safeHtmlOutput($team['name']);
        /** @var string $fullName */
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
            /** @var string $errorEscaped */
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
        /** @var string $messageEscaped */
        $messageEscaped = HtmlSanitizer::safeHtmlOutput($banner['message']);
        return '<div class="ibl-alert ' . $banner['class'] . '">' . $messageEscaped . '</div>';
    }

    /**
     * Build HTML rows for players in the trade form
     *
     * @param list<TradingPlayerRow> $players Player rows from repository
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
            $pid = $row['pid'];
            $ordinal = $row['ordinal'] ?? 0;
            $contractYear = $row['cy'] ?? 0;
            /** @var string $playerPosition */
            $playerPosition = HtmlSanitizer::safeHtmlOutput($row['pos']);
            $resolved = PlayerImageHelper::resolvePlayerDisplay($pid, $row['name']);
            /** @var string $playerName */
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
        $html = (string) ob_get_clean();

        return ['html' => $html, 'nextK' => $k];
    }

    /**
     * Build HTML rows for draft picks in the trade form
     *
     * @param list<TradingDraftPickRow> $picks Draft pick rows from repository
     * @param int $startK Starting form field counter
     * @return array{html: string, nextK: int}
     */
    private function buildPickRows(array $picks, int $startK): array
    {
        $k = $startK;

        ob_start();
        foreach ($picks as $row) {
            $pickId = $row['pickid'];
            $pickYear = $row['year'];
            /** @var string $pickTeam */
            $pickTeam = HtmlSanitizer::safeHtmlOutput($row['teampick']);
            $pickTeamId = $row['teampick_id'];
            $pickRound = $row['round'];
            $pickNotes = $row['notes'];
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
            <?= $pickYear ?> R<?= $pickRound ?> <a href="./modules.php?name=Team&amp;op=team&amp;teamID=<?= $pickTeamId ?>"><span class="ibl-team-cell__text"><?= $pickTeam ?></span></a>
<?php if ($pickNotes !== null && $pickNotes !== ''):
    /** @var string $pickNotesEscaped */
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
<table class="ibl-data-table trading-cap-totals" data-no-responsive style="width: 100%; margin-top: 1rem;">
    <thead><tr><th colspan="2">Cap Totals</th></tr></thead>
    <tbody>
<?php for ($z = 0; $z < $seasonsToDisplay; $z++):
    $yearLabel = ($displayEndingYear + $z - 1) . '-' . ($displayEndingYear + $z);
    /** @var string $yearLabelEscaped */
    $yearLabelEscaped = HtmlSanitizer::safeHtmlOutput($yearLabel);
?>
    <tr>
        <td style="text-align: left;"><strong><?= $userTeam ?></strong> in <?= $yearLabelEscaped ?>: <?= $userFutureSalary['player'][$z] ?></td>
        <td style="text-align: right;"><strong><?= $partnerTeam ?></strong> in <?= $yearLabelEscaped ?>: <?= $partnerFutureSalary['player'][$z] ?></td>
    </tr>
<?php endfor; ?>
    </tbody>
</table>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the cash exchange section of the trade form
     */
    private function renderCashExchange(int $seasonEndingYear, string $seasonPhase, int $cashStartYear, int $cashEndYear, string $userTeam, string $partnerTeam): string
    {
        ob_start();
        ?>
<table class="ibl-data-table trading-cash-exchange" data-no-responsive style="width: 100%; margin-top: 1rem;">
    <thead><tr><th colspan="2">Cash Exchange</th></tr></thead>
    <tbody>
<?php for ($i = $cashStartYear; $i <= $cashEndYear; $i++):
    $yearLabel = ($seasonEndingYear - 2 + $i) . '-' . ($seasonEndingYear - 1 + $i);
    /** @var string $yearLabelEscaped */
    $yearLabelEscaped = HtmlSanitizer::safeHtmlOutput($yearLabel);
?>
    <tr>
        <td style="text-align: left;">
            <strong><?= $userTeam ?></strong> send
            <input type="number" name="userSendsCash<?= $i ?>" value="0" min="0" max="2000" style="width: 80px;">
            for <?= $yearLabelEscaped ?>
        </td>
        <td style="text-align: right;">
            <strong><?= $partnerTeam ?></strong> send
            <input type="number" name="partnerSendsCash<?= $i ?>" value="0" min="0" max="2000" style="width: 80px;">
            for <?= $yearLabelEscaped ?>
        </td>
    </tr>
<?php endfor; ?>
    </tbody>
</table>
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
        /** @var string $oppositeTeam */
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
    <?php if ($item['description'] !== ''):
        /** @var string $descriptionEscaped */
        $descriptionEscaped = HtmlSanitizer::safeHtmlOutput($item['description']);
    ?>
        <p><?= $descriptionEscaped ?></p>
        <?php if ($item['notes'] !== null):
            /** @var string $notesEscaped */
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
