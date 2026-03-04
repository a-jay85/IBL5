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
<html>
<head>
    <title>IBLv5 Control Panel</title>
</head>
<body>
<?php echo $this->renderLeagueSwitcher($leagueConfig, $currentLeague); ?>
<?php echo $this->renderFlashMessage($resultMessage, $resultSuccess); ?>
<form action="leagueControlPanel.php" method="POST">
<input type="hidden" name="current_phase" value="<?= HtmlSanitizer::e($panelData['phase']) ?>">
<?php echo $this->renderSeasonPhaseControls($currentLeague, $panelData); ?>
<a href="/ibl5/modules.php?name=SeasonHighs">Season Highs</a><p>
<?php echo $this->renderPhaseControls($currentLeague, $panelData); ?>
<?php echo $this->renderTriviaControls(); ?>
</form>
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
    <strong>Current League:</strong>
    <span class="league-badge <?= HtmlSanitizer::e($badgeClass) ?>"><?= HtmlSanitizer::e(strtoupper($leagueConfig['short_name'])) ?></span>
    <span style="margin-left: 20px;">Switch to: </span>
    <select onchange="window.location.href=this.value" style="padding: 5px; font-size: 14px;">
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
    <b><?= HtmlSanitizer::e($message) ?></b>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int} $panelData
     */
    private function renderSeasonPhaseControls(string $currentLeague, array $panelData): string
    {
        if ($currentLeague !== 'ibl') {
            return '';
        }

        $phases = ['Preseason', 'HEAT', 'Regular Season', 'Playoffs', 'Draft', 'Free Agency'];

        ob_start();
        ?>
<select name="SeasonPhase">
<?php foreach ($phases as $phase): ?>
    <option value="<?= HtmlSanitizer::e($phase) ?>"<?= $panelData['phase'] === $phase ? ' selected' : '' ?>><?= HtmlSanitizer::e($phase) ?></option>
<?php endforeach; ?>
</select>
<button type="submit" name="action" value="set_season_phase">Set Season Phase</button><p>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int} $panelData
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
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int} $panelData
     */
    private function renderPreseasonControls(array $panelData): string
    {
        ob_start();
        ?>
<a href="/ibl5/scripts/updateAllTheThings.php">Update All The Things</a>
<br><b>(upload .asw .car .his .lge .plr .rcb .sch .sco .trn before running!)</b><p>
<?= $this->renderWaiversSelect($panelData) ?>
<button type="submit" name="action" value="set_waivers_to_free_agents">Set all players on waivers to Free Agents and reset their Bird years</button><p>
<button type="submit" name="action" value="reset_contract_extensions">Reset All Contract Extensions</button><p>
<button type="submit" name="action" value="reset_mles_lles">Reset All MLEs/LLEs</button><p>
        <?php
        return (string) ob_get_clean();
    }

    private function renderHeatControls(): string
    {
        ob_start();
        ?>
<a href="/ibl5/scripts/updateAllTheThings.php">Update All The Things</a>
<br><b>(upload .asw .car .his .lge .plr .rcb .sch .sco .trn before running!)</b><p>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int} $panelData
     */
    private function renderRegularSeasonControls(string $currentLeague, array $panelData): string
    {
        ob_start();
        ?>
<a href="/ibl5/scripts/updateAllTheThings.php">Update All The Things</a>
<br><b>(upload .asw .car .his .lge .plr .rcb .sch .sco .trn before running!)</b><p>
<input type="number" name="SimLengthInDays" min="1" max="180" size="3" value="<?= HtmlSanitizer::e((string) $panelData['simLengthInDays']) ?>">
<button type="submit" name="action" value="set_sim_length">Set Sim Length in Days</button> <i>
<br>(you HAVE to CLICK to set the days -- you unfortunately can't just hit Return/Enter)</i><p>
<?php if ($currentLeague === 'ibl'): ?>
<button type="submit" name="action" value="reset_asg_voting">Reset All-Star Voting</button><p>
<button type="submit" name="action" value="reset_eoy_voting">Reset End of the Year Voting</button><p>
<?= $this->renderTradesSelect($panelData) ?>
<?= $this->renderDraftLinkSelect($panelData) ?>
<?php endif; ?>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int} $panelData
     */
    private function renderPlayoffsControls(array $panelData): string
    {
        ob_start();
        ?>
<a href="/ibl5/scripts/updateAllTheThings.php">Update All The Things</a>
<br><b>(upload .asw .car .his .lge .plr .rcb .sch .sco .trn before running!)</b><p>
<button type="submit" name="action" value="reset_eoy_voting">Reset End of the Year Voting</button><p>
<?= $this->renderTradesSelect($panelData) ?>
<?= $this->renderDraftLinkSelect($panelData) ?>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int} $panelData
     */
    private function renderDraftControls(array $panelData): string
    {
        return $this->renderWaiversSelect($panelData);
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int} $panelData
     */
    private function renderFreeAgencyControls(array $panelData): string
    {
        ob_start();
        ?>
<button type="submit" name="action" value="reset_contract_extensions">Reset All Contract Extensions</button><p>
<button type="submit" name="action" value="reset_mles_lles">Reset All MLEs/LLEs</button><p>
<button type="submit" name="action" value="set_fa_factors_pfw">Set Free Agency factors for PFW</button><p>
<a href="/ibl5/scripts/tradition.php">Set Free Agency factors for Tradition</a><p>
<?= $this->renderFaNotificationsSelect($panelData) ?>
<?= $this->renderWaiversSelect($panelData) ?>
<button type="submit" name="action" value="set_waivers_to_free_agents">Set all players on waivers to Free Agents and reset their Bird years</button><p>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int} $panelData
     */
    private function renderWaiversSelect(array $panelData): string
    {
        ob_start();
        ?>
<select name="Waivers">
    <option value="Yes"<?= $panelData['allowWaivers'] === 'Yes' ? ' selected' : '' ?>>Yes</option>
    <option value="No"<?= $panelData['allowWaivers'] === 'No' ? ' selected' : '' ?>>No</option>
</select>
<button type="submit" name="action" value="set_allow_waivers">Set Allow Waiver Moves Status</button><p>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int} $panelData
     */
    private function renderTradesSelect(array $panelData): string
    {
        ob_start();
        ?>
<select name="Trades">
    <option value="Yes"<?= $panelData['allowTrades'] === 'Yes' ? ' selected' : '' ?>>Yes</option>
    <option value="No"<?= $panelData['allowTrades'] === 'No' ? ' selected' : '' ?>>No</option>
</select>
<button type="submit" name="action" value="set_allow_trades">Set Allow Trades Status</button><p>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int} $panelData
     */
    private function renderDraftLinkSelect(array $panelData): string
    {
        ob_start();
        ?>
<select name="ShowDraftLink">
    <option value="On"<?= $panelData['showDraftLink'] === 'On' ? ' selected' : '' ?>>On</option>
    <option value="Off"<?= $panelData['showDraftLink'] === 'Off' ? ' selected' : '' ?>>Off</option>
</select>
<button type="submit" name="action" value="set_show_draft_link">Set Show Draft Link Status</button><p>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int} $panelData
     */
    private function renderFaNotificationsSelect(array $panelData): string
    {
        ob_start();
        ?>
<select name="FANotifs">
    <option value="On"<?= $panelData['freeAgencyNotifications'] === 'On' ? ' selected' : '' ?>>On</option>
    <option value="Off"<?= $panelData['freeAgencyNotifications'] === 'Off' ? ' selected' : '' ?>>Off</option>
</select>
<button type="submit" name="action" value="toggle_fa_notifications">Toggle Free Agency Notifications</button><p>
        <?php
        return (string) ob_get_clean();
    }

    private function renderTriviaControls(): string
    {
        ob_start();
        ?>
<button type="submit" name="action" value="activate_trivia">Deactivate Player and Season Leaders modules for Trivia</button><p>
<button type="submit" name="action" value="deactivate_trivia">Activate Player and Season Leaders modules after Trivia</button><p>
        <?php
        return (string) ob_get_clean();
    }
}
