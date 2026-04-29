<?php

declare(strict_types=1);

namespace LeagueControlPanel;

use LeagueControlPanel\Contracts\LeagueControlPanelViewInterface;
use Utilities\HtmlSanitizer;

/**
 * @see LeagueControlPanelViewInterface
 */
class LeagueControlPanelView implements LeagueControlPanelViewInterface
{
    /**
     * @see LeagueControlPanelViewInterface::render()
     */
    public function render(array $leagueConfig, string $currentLeague, array $panelData, ?string $resultMessage, bool $resultSuccess): string
    {
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IBLv5 Control Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700;800&family=Barlow:wght@400;500;600;700&display=block" rel="stylesheet">
    <link rel="stylesheet" href="/ibl5/themes/IBL/style/style.css">
</head>
<body>
<div class="updater">
    <h1 class="updater__title">League Control Panel</h1>

    <?= $this->renderFlashMessage($resultMessage, $resultSuccess) ?>
    <?= $this->renderLeagueSwitcher($leagueConfig, $currentLeague) ?>

    <form action="leagueControlPanel.php" method="POST">
        <input type="hidden" name="current_phase" value="<?= HtmlSanitizer::e($panelData['phase']) ?>">

        <?= $this->renderSeasonPhaseControls($currentLeague, $panelData) ?>
        <?= $this->renderPhaseControls($currentLeague, $panelData) ?>

        <section class="updater-section">
            <div class="updater-section__label">Quick Links</div>
            <div class="lcp-control-row">
                <a href="/ibl5/modules.php?name=SeasonHighs">Season Highs</a>
            </div>
        </section>

        <?= $this->renderTriviaControls() ?>
    </form>

    <a href="/ibl5/index.php" class="updater__return underline">Return to IBL</a>
</div>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{short_name: string, full_name: string} $leagueConfig
     */
    private function renderLeagueSwitcher(array $leagueConfig, string $currentLeague): string
    {
        $badgeClass = $currentLeague === 'ibl' ? 'league-badge-ibl' : 'league-badge-olympics';
        $iblSelected = $currentLeague === 'ibl' ? ' selected' : '';
        $olympicsSelected = $currentLeague === 'olympics' ? ' selected' : '';

        ob_start();
        ?>
<div class="league-switcher-admin">
    <label>Current League:</label>
    <span class="league-badge <?= HtmlSanitizer::e($badgeClass) ?>"><?= HtmlSanitizer::e(strtoupper($leagueConfig['short_name'])) ?></span>
    <label>Switch to:</label>
    <select onchange="window.location.href=this.value" class="ibl-select ibl-select--auto">
        <option value="leagueControlPanel.php?league=ibl"<?= $iblSelected ?>>IBL</option>
        <option value="leagueControlPanel.php?league=olympics"<?= $olympicsSelected ?>>Olympics</option>
    </select>
</div>
        <?php
        return (string) ob_get_clean();
    }

    private function renderFlashMessage(?string $message, bool $success): string
    {
        if ($message === null) {
            return '';
        }

        $alertClass = $success ? 'ibl-alert ibl-alert--success' : 'ibl-alert ibl-alert--error';

        ob_start();
        ?>
<div class="<?= HtmlSanitizer::e($alertClass) ?>">
    <strong><?= HtmlSanitizer::e($message) ?></strong>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int, hasFinalsMvp: bool} $panelData
     */
    private function renderSeasonPhaseControls(string $currentLeague, array $panelData): string
    {
        if ($currentLeague !== 'ibl') {
            return '';
        }

        $phases = ['Preseason', 'HEAT', 'Regular Season', 'Playoffs', 'Draft', 'Free Agency'];

        ob_start();
        ?>
<section class="updater-section">
    <div class="updater-section__label">Season Phase</div>
    <div class="lcp-control-row">
        <select name="SeasonPhase" class="ibl-select ibl-select--auto">
        <?php foreach ($phases as $phase): ?>
            <option value="<?= HtmlSanitizer::e($phase) ?>"<?= $panelData['phase'] === $phase ? ' selected' : '' ?>><?= HtmlSanitizer::e($phase) ?></option>
        <?php endforeach; ?>
        </select>
        <button type="submit" name="action" value="set_season_phase" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Set Season Phase</button>
    </div>
</section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int, hasFinalsMvp: bool} $panelData
     */
    private function renderPhaseControls(string $currentLeague, array $panelData): string
    {
        return match ($panelData['phase']) {
            'Preseason' => $this->renderPreseasonControls($panelData),
            'HEAT' => $this->renderHeatControls(),
            'Regular Season' => $this->renderRegularSeasonControls($currentLeague, $panelData),
            'Playoffs' => $this->renderPlayoffsControls($panelData),
            'Draft' => $this->renderDraftControls($panelData),
            'Free Agency' => $this->renderFreeAgencyControls($panelData),
            default => '',
        };
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int, hasFinalsMvp: bool} $panelData
     */
    private function renderPreseasonControls(array $panelData): string
    {
        ob_start();
        ?>
<section class="updater-section">
    <div class="updater-section__label">Preseason Operations</div>
    <div class="lcp-control-row">
        <a href="/ibl5/scripts/updateAllTheThings.php">Update All The Things</a>
    </div>
    <div class="lcp-note">Upload sim backup to <strong>backups/</strong> before running</div>
    <?= $this->renderWaiversSelect($panelData) ?>
    <div class="lcp-control-row">
        <button type="submit" name="action" value="set_waivers_to_free_agents" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Set all players on waivers to Free Agents and reset their Bird years</button>
    </div>
    <div class="lcp-control-row">
        <button type="submit" name="action" value="delete_outdated_buyouts_cash" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Delete All Outdated Buyouts and Cash Considerations</button>
    </div>
    <div class="lcp-control-row">
        <button type="submit" name="action" value="reset_contract_extensions" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Reset All Contract Extensions</button>
    </div>
    <div class="lcp-control-row">
        <button type="submit" name="action" value="reset_mles_lles" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Reset All MLEs/LLEs</button>
    </div>
</section>
        <?php
        return (string) ob_get_clean();
    }

    private function renderHeatControls(): string
    {
        ob_start();
        ?>
<section class="updater-section">
    <div class="updater-section__label">HEAT Operations</div>
    <div class="lcp-control-row">
        <a href="/ibl5/scripts/updateAllTheThings.php">Update All The Things</a>
    </div>
    <div class="lcp-note">Upload sim backup to <strong>backups/</strong> before running</div>
</section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int, hasFinalsMvp: bool} $panelData
     */
    private function renderRegularSeasonControls(string $currentLeague, array $panelData): string
    {
        ob_start();
        ?>
<section class="updater-section">
    <div class="updater-section__label">Regular Season Operations</div>
    <div class="lcp-control-row">
        <a href="/ibl5/scripts/updateAllTheThings.php">Update All The Things</a>
    </div>
    <div class="lcp-note">Upload sim backup to <strong>backups/</strong> before running</div>
    <div class="lcp-control-row">
        <input type="number" name="SimLengthInDays" min="1" max="180" size="3" value="<?= HtmlSanitizer::e((string) $panelData['simLengthInDays']) ?>" class="ibl-input ibl-input--sm w-20">
        <button type="submit" name="action" value="set_sim_length" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Set Sim Length in Days</button>
    </div>
    <div class="lcp-note">You must click the button — pressing Enter will not work</div>
<?php if ($currentLeague === 'ibl'): ?>
    <div class="lcp-control-row">
        <button type="submit" name="action" value="reset_asg_voting" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Reset All-Star Voting</button>
    </div>
    <div class="lcp-control-row">
        <button type="submit" name="action" value="reset_eoy_voting" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Reset End of the Year Voting</button>
    </div>
    <?= $this->renderTradesSelect($panelData) ?>
    <?= $this->renderDraftLinkSelect($panelData) ?>
<?php endif; ?>
</section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int, hasFinalsMvp: bool} $panelData
     */
    private function renderPlayoffsControls(array $panelData): string
    {
        ob_start();
        ?>
<section class="updater-section">
    <div class="updater-section__label">Playoffs Operations</div>
    <div class="lcp-control-row">
        <a href="/ibl5/scripts/updateAllTheThings.php">Update All The Things</a>
    </div>
    <div class="lcp-note">Upload sim backup to <strong>backups/</strong> before running</div>
    <div class="lcp-control-row">
        <button type="submit" name="action" value="reset_eoy_voting" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Reset End of the Year Voting</button>
    </div>
    <div class="lcp-control-row">
        <a href="/ibl5/import-demands.php">Free Agency Demands CSV Uploader</a>
    </div>
    <?= $this->renderDraftLinkSelect($panelData) ?>
    <?= $this->renderAwardsControls($panelData) ?>
</section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int, hasFinalsMvp: bool} $panelData
     */
    private function renderDraftControls(array $panelData): string
    {
        ob_start();
        ?>
<section class="updater-section">
    <div class="updater-section__label">Draft Operations</div>
    <?= $this->renderWaiversSelect($panelData) ?>
    <?= $this->renderAwardsControls($panelData) ?>
</section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int, hasFinalsMvp: bool} $panelData
     */
    private function renderFreeAgencyControls(array $panelData): string
    {
        ob_start();
        ?>
<section class="updater-section">
    <div class="updater-section__label">Free Agency Operations</div>
    <div class="lcp-control-row">
        <button type="submit" name="action" value="delete_draft_placeholders" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Delete Draft Player Placeholders</button>
    </div>
    <div class="lcp-control-row">
        <button type="submit" name="action" value="delete_outdated_buyouts_cash" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Delete All Outdated Buyouts and Cash Considerations</button>
    </div>
    <div class="lcp-control-row">
        <button type="submit" name="action" value="reset_contract_extensions" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Reset All Contract Extensions</button>
    </div>
    <div class="lcp-control-row">
        <button type="submit" name="action" value="reset_mles_lles" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Reset All MLEs/LLEs</button>
    </div>
    <div class="lcp-control-row">
        <button type="submit" name="action" value="set_fa_factors_pfw" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Set Free Agency factors for PFW</button>
    </div>
    <div class="lcp-control-row">
        <a href="/ibl5/scripts/tradition.php">Set Free Agency factors for Tradition</a>
    </div>
    <div class="lcp-control-row">
        <a href="/ibl5/import-demands.php">Free Agency Demands CSV Uploader</a>
    </div>
    <?= $this->renderFaNotificationsSelect($panelData) ?>
    <?= $this->renderWaiversSelect($panelData) ?>
    <div class="lcp-control-row">
        <button type="submit" name="action" value="set_waivers_to_free_agents" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Set all players on waivers to Free Agents and reset their Bird years</button>
    </div>
</section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int, hasFinalsMvp: bool} $panelData
     */
    private function renderWaiversSelect(array $panelData): string
    {
        ob_start();
        ?>
<div class="lcp-control-row">
    <select name="Waivers" class="ibl-select ibl-select--auto">
        <option value="Yes"<?= $panelData['allowWaivers'] === 'Yes' ? ' selected' : '' ?>>Yes</option>
        <option value="No"<?= $panelData['allowWaivers'] === 'No' ? ' selected' : '' ?>>No</option>
    </select>
    <button type="submit" name="action" value="set_allow_waivers" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Set Allow Waiver Moves Status</button>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int, hasFinalsMvp: bool} $panelData
     */
    private function renderTradesSelect(array $panelData): string
    {
        ob_start();
        ?>
<div class="lcp-control-row">
    <select name="Trades" class="ibl-select ibl-select--auto">
        <option value="Yes"<?= $panelData['allowTrades'] === 'Yes' ? ' selected' : '' ?>>Yes</option>
        <option value="No"<?= $panelData['allowTrades'] === 'No' ? ' selected' : '' ?>>No</option>
    </select>
    <button type="submit" name="action" value="set_allow_trades" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Set Allow Trades Status</button>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int, hasFinalsMvp: bool} $panelData
     */
    private function renderDraftLinkSelect(array $panelData): string
    {
        ob_start();
        ?>
<div class="lcp-control-row">
    <select name="ShowDraftLink" class="ibl-select ibl-select--auto">
        <option value="On"<?= $panelData['showDraftLink'] === 'On' ? ' selected' : '' ?>>On</option>
        <option value="Off"<?= $panelData['showDraftLink'] === 'Off' ? ' selected' : '' ?>>Off</option>
    </select>
    <button type="submit" name="action" value="set_show_draft_link" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Set Show Draft Link Status</button>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int, hasFinalsMvp: bool} $panelData
     */
    private function renderFaNotificationsSelect(array $panelData): string
    {
        ob_start();
        ?>
<div class="lcp-control-row">
    <select name="FANotifs" class="ibl-select ibl-select--auto">
        <option value="On"<?= $panelData['freeAgencyNotifications'] === 'On' ? ' selected' : '' ?>>On</option>
        <option value="Off"<?= $panelData['freeAgencyNotifications'] === 'Off' ? ' selected' : '' ?>>Off</option>
    </select>
    <button type="submit" name="action" value="toggle_fa_notifications" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Toggle Free Agency Notifications</button>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int, hasFinalsMvp: bool} $panelData
     */
    private function renderAwardsControls(array $panelData): string
    {
        ob_start();
        ?>
<div class="updater-section__label">Season Awards</div>
<div class="lcp-control-row">
    <button type="submit" name="action" value="generate_awards" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Generate Season Awards</button>
</div>
<div class="lcp-note">Requires Leaders.htm and completed EOY voting</div>
<?php if (!$panelData['hasFinalsMvp']): ?>
<div class="lcp-control-row">
    <input type="text" name="finals_mvp_name" placeholder="Finals MVP name" class="ibl-input ibl-input--sm" maxlength="32">
    <button type="submit" name="action" value="set_finals_mvp" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Set Finals MVP</button>
</div>
<?php endif; ?>
        <?php
        return (string) ob_get_clean();
    }

    private function renderTriviaControls(): string
    {
        ob_start();
        ?>
<section class="updater-section">
    <div class="updater-section__label">Trivia Management</div>
    <div class="lcp-control-row">
        <button type="submit" name="action" value="activate_trivia" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Deactivate Player and Season Leaders modules for Trivia</button>
    </div>
    <div class="lcp-control-row">
        <button type="submit" name="action" value="deactivate_trivia" class="ibl-btn ibl-btn--secondary ibl-btn--sm">Activate Player and Season Leaders modules after Trivia</button>
    </div>
</section>
        <?php
        return (string) ob_get_clean();
    }
}
