<?php

declare(strict_types=1);

namespace DepthChartEntry;

use DepthChartEntry\Contracts\DepthChartEntryViewInterface;
use Utilities\HtmlSanitizer;

/**
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type ProcessedPlayerData from Contracts\DepthChartEntryProcessorInterface
 *
 * @see DepthChartEntryViewInterface
 */
class DepthChartEntryView implements DepthChartEntryViewInterface
{
    /**
     * Role slot display labels mapped to their HTML form field names.
     * PG slot = dc_bh (form field BH), SG slot = dc_di (form field DI), etc.
     */
    private const ROLE_SLOTS = [
        ['label' => 'PG', 'field' => 'BH', 'dbKey' => 'dc_bh', 'max' => 2],
        ['label' => 'SG', 'field' => 'DI', 'dbKey' => 'dc_di', 'max' => 2],
        ['label' => 'SF', 'field' => 'OI', 'dbKey' => 'dc_oi', 'max' => 2],
        ['label' => 'PF', 'field' => 'DF', 'dbKey' => 'dc_df', 'max' => 3],
        ['label' => 'C',  'field' => 'OF', 'dbKey' => 'dc_of', 'max' => 3],
    ];

    /**
     * @see DepthChartEntryViewInterface::renderTeamLogo()
     */
    public function renderTeamLogo(int $teamID): void
    {
        /** @var \League\LeagueContext $leagueContext */
        global $leagueContext;

        $leagueConfig = $leagueContext->getConfig();
        /** @var string $imagesPath */
        $imagesPath = $leagueConfig['images_path'];

        echo '<div class="depth-chart-logo"><img src="./' . $imagesPath . 'logo/' . $teamID . '.jpg" alt="Team Logo"></div>';
    }

    /**
     * Render role priority dropdown options (0 to max).
     * Unified for all 5 role slots: BH/DI/OI use max=2, DF/OF use max=3.
     */
    public function renderRolePriorityOptions(int $selectedValue, int $maxValue): void
    {
        for ($i = 0; $i <= $maxValue; $i++) {
            $selected = ($selectedValue === $i) ? ' SELECTED' : '';
            $label = ($i === 0) ? '&mdash;' : (string) $i;
            echo "<option value=\"{$i}\"{$selected}>{$label}</option>";
        }
    }

    /**
     * @see DepthChartEntryViewInterface::renderActiveOptions()
     */
    public function renderActiveOptions(int $selectedValue): void
    {
        if ($selectedValue === 1) {
            echo '<option value="1" SELECTED>Yes</option><option value="0">No</option>';
        } else {
            echo '<option value="1">Yes</option><option value="0" SELECTED>No</option>';
        }
    }

    /**
     * Render the help section explaining how depth charts work.
     */
    public function renderHelpSection(): void
    {
        echo '<details class="dc-help-section">
<summary>How Depth Charts Work</summary>
<div class="dc-help-section__content">
<p>Assign players to role slots (PG through C) to control who starts and who subs in.
The engine selects the highest-quality assigned player for each slot as the starter.
Remaining assigned players form the bench, with higher values subbing in first.</p>
<table class="ibl-data-table dc-help-table">
<thead><tr><th>Slot</th><th>Play Style</th></tr></thead>
<tbody>
<tr><td>PG</td><td>Outside shots &amp; drives (perimeter-heavy)</td></tr>
<tr><td>SG</td><td>Mostly perimeter, some inside</td></tr>
<tr><td>SF</td><td>Balanced inside/outside</td></tr>
<tr><td>PF</td><td>Mostly inside, some outside</td></tr>
<tr><td>C</td><td>Heavily inside/post-oriented</td></tr>
</tbody>
</table>
<p>Set slot values to &mdash; (0) for deep bench players. A player\'s inherent position
(Pos column) is used as a fallback when no slot is assigned.</p>
</div>
</details>';
    }

    /**
     * Render the empty container for the live lineup preview grid.
     * JavaScript populates this based on current form values.
     */
    public function renderLineupPreview(): void
    {
        echo '<div id="dc-lineup-preview" class="dc-lineup-preview"></div>';
    }

    /**
     * @see DepthChartEntryViewInterface::renderFormHeader()
     */
    public function renderFormHeader(string $teamLogo, int $teamID, array $slotNames): void
    {
        $teamLogoEscaped = HtmlSanitizer::safeHtmlOutput($teamLogo);
        echo '<form name="DepthChartEntry" method="post" action="modules.php?name=DepthChartEntry&amp;op=submit" class="depth-chart-form">
            ' . \Utilities\CsrfGuard::generateToken('depth_chart') . '
            <input type="hidden" name="Team_Name" value="' . $teamLogoEscaped . '">
            <input type="hidden" name="loaded_dc_id" id="loaded_dc_id" value="0">';

        echo '<div class="text-center"><table class="depth-chart-table ibl-data-table" data-no-responsive>
            <thead>
                <tr>
                    <th>Pos</th>
                    <th>Player</th>
                    <th>Active</th>';

        foreach (self::ROLE_SLOTS as $slot) {
            $labelHtml = HtmlSanitizer::safeHtmlOutput($slot['label']);
            echo '<th>' . $labelHtml . '</th>';
        }

        echo '          </tr>
            </thead>
            <tbody>';
    }

    /**
     * @see DepthChartEntryViewInterface::renderPlayerRow()
     * @param PlayerRow $player
     */
    public function renderPlayerRow(array $player, int $depthCount): void
    {
        $player_pid = $player['pid'];
        $player_pos = $player['pos'];
        $player_name = $player['name'];
        $player_inj = $player['injured'] ?? 0;

        $player_name_html = HtmlSanitizer::safeHtmlOutput($player_name);

        /** @var float $qualityScore */
        $qualityScore = $player['quality_score'] ?? 0.0;

        echo "<tr data-pid=\"{$player_pid}\" data-quality=\"{$qualityScore}\" data-pos=\"{$player_pos}\">
            <td>{$player_pos}<span class=\"dc-quality-debug\"></span></td>
            <td nowrap>
                <input type=\"hidden\" name=\"pid{$depthCount}\" value=\"{$player_pid}\">
                <input type=\"hidden\" name=\"Injury{$depthCount}\" value=\"{$player_inj}\">
                <input type=\"hidden\" name=\"Name{$depthCount}\" value=\"{$player_name_html}\">
                <input type=\"hidden\" name=\"pg{$depthCount}\" value=\"0\">
                <input type=\"hidden\" name=\"sg{$depthCount}\" value=\"0\">
                <input type=\"hidden\" name=\"sf{$depthCount}\" value=\"0\">
                <input type=\"hidden\" name=\"pf{$depthCount}\" value=\"0\">
                <input type=\"hidden\" name=\"c{$depthCount}\" value=\"0\">
                <input type=\"hidden\" name=\"min{$depthCount}\" value=\"0\">
                <a href=\"./modules.php?name=Player&pa=showpage&pid={$player_pid}\">{$player_name_html}</a>
            </td>";

        // Active status
        $dcActive = $player['dc_canPlayInGame'] ?? 0;
        echo "<td><select name=\"canPlayInGame{$depthCount}\" aria-label=\"Active status for {$player_name_html}\">";
        $this->renderActiveOptions($dcActive);
        echo "</select></td>";

        // Role slot columns (PG/SG/SF/PF/C mapped to BH/DI/OI/DF/OF form fields)
        foreach (self::ROLE_SLOTS as $slot) {
            /** @var int $dcValue */
            $dcValue = $player[$slot['dbKey']] ?? 0;
            // Clamp negative legacy values to 0
            if ($dcValue < 0) {
                $dcValue = 0;
            }
            $fieldName = $slot['field'] . $depthCount;
            $ariaLabel = $slot['label'] . ' slot for ' . $player_name_html;

            echo "<td><select name=\"{$fieldName}\" aria-label=\"{$ariaLabel}\">";
            $this->renderRolePriorityOptions($dcValue, $slot['max']);
            echo "</select><span class=\"dc-score-debug\"></span></td>";
        }

        echo "</tr>";
    }

    /**
     * @see DepthChartEntryViewInterface::renderFormFooter()
     */
    public function renderFormFooter(): void
    {
        $resetScript = <<<'JAVASCRIPT'
<script type="text/javascript">
function resetDepthChart() {
    if (!confirm('Are you sure you want to reset all fields to their default values? This will discard any changes you have made.')) {
        return false;
    }

    var form = document.forms['DepthChartEntry'];
    if (!form) return;

    var selects = form.getElementsByTagName('select');

    for (var i = 0; i < selects.length; i++) {
        var select = selects[i];
        var name = select.name;

        var defaultValue = '0';

        if (name.match(/^canPlayInGame\d+$/)) {
            defaultValue = '1';
        }

        select.value = defaultValue;
    }

    // Also reset mobile card checkboxes
    var checkboxes = form.querySelectorAll('.dc-card__active-cb');
    for (var j = 0; j < checkboxes.length; j++) {
        checkboxes[j].checked = true;
        var card = checkboxes[j].closest('.dc-card');
        if (card) card.classList.remove('dc-card--inactive');
    }

    if (typeof window.IBL_recalculateDepthChartGlows === 'function') {
        window.IBL_recalculateDepthChartGlows();
    }
    if (typeof window.IBL_recalculateLineupPreview === 'function') {
        window.IBL_recalculateLineupPreview();
    }

    return false;
}
</script>
JAVASCRIPT;

        echo $resetScript;
        echo '</tbody>
            <tfoot>
                <tr>
                    <td colspan="8" class="depth-chart-buttons">
                        <input type="button" value="Reset" onclick="resetDepthChart();" class="depth-chart-reset-btn">
                        <input type="submit" value="Submit Depth Chart" class="depth-chart-submit-btn">
                    </td>
                </tr>
            </tfoot>
        </table></div>';
    }

    /**
     * Render the saved depth chart dropdown selector
     *
     * @param list<array{id: int, label: string, isActive: bool}> $options
     */
    public function renderSavedDepthChartDropdown(array $options, string $currentLiveLabel): void
    {
        echo '<div class="saved-dc-dropdown-container">';
        echo '<label for="saved-dc-select" class="saved-dc-label">Load Saved Depth Chart:</label>';
        echo '<div class="saved-dc-select-wrapper">';
        echo '<select id="saved-dc-select" class="saved-dc-select">';
        $currentLiveLabelHtml = HtmlSanitizer::safeHtmlOutput($currentLiveLabel);
        echo '<option value="0">' . $currentLiveLabelHtml . '</option>';
        foreach ($options as $option) {
            $labelHtml = HtmlSanitizer::safeHtmlOutput($option['label']);
            echo '<option value="' . $option['id'] . '">' . $labelHtml . '</option>';
        }
        echo '</select>';
        echo '<button type="button" id="saved-dc-rename-btn" class="saved-dc-rename-btn" title="Rename selected depth chart" style="display:none;">&#9998;</button>';
        echo '</div>';
        echo '<div id="saved-dc-loading" class="saved-dc-loading" style="display:none;">Loading...</div>';
        echo '</div>';
    }

    /**
     * @see DepthChartEntryViewInterface::renderSubmissionResult()
     * @param list<ProcessedPlayerData> $playerData
     */
    public function renderSubmissionResult(
        string $teamName,
        array $playerData,
        bool $success,
        string $errorHtml = ''
    ): void {
        if (!$success) {
            echo '<div class="text-center"><span class="underline">Your lineup has <strong>not</strong> been submitted:</span></div><br>';
            echo $errorHtml;
        } else {
            echo '<div class="text-center"><span class="underline">Your depth chart has been submitted and e-mailed successfully. Thank you.</span></div><p>';
        }

        $teamNameHtml = HtmlSanitizer::safeHtmlOutput($teamName);
        echo $teamNameHtml . ' Depth Chart Submission<br><table class="ibl-data-table">';
        echo '<thead><tr>
            <th>Name</th>
            <th>Active</th>';

        foreach (self::ROLE_SLOTS as $slot) {
            $labelHtml = HtmlSanitizer::safeHtmlOutput($slot['label']);
            echo '<th>' . $labelHtml . '</th>';
        }

        echo '</tr></thead><tbody>';

        foreach ($playerData as $player) {
            $nameHtml = HtmlSanitizer::safeHtmlOutput($player['name']);
            echo '<tr>
                <td>' . $nameHtml . '</td>
                <td>' . $player['canPlayInGame'] . '</td>
                <td>' . $player['bh'] . '</td>
                <td>' . $player['di'] . '</td>
                <td>' . $player['oi'] . '</td>
                <td>' . $player['df'] . '</td>
                <td>' . $player['of'] . '</td>
            </tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * @see DepthChartEntryViewInterface::renderMobileView()
     * @param list<array<string, mixed>> $players
     * @param array<string> $slotNames
     */
    public function renderMobileView(array $players, array $slotNames): void
    {
        echo '<div class="dc-mobile-cards" id="dc-mobile-cards" aria-hidden="true">';

        $depthCount = 1;
        foreach ($players as $player) {
            $this->renderMobilePlayerCard($player, $depthCount);
            $depthCount++;
        }

        echo '<div class="dc-mobile-cards__footer">
            <input type="button" value="Reset" onclick="resetDepthChart();" class="depth-chart-reset-btn">
            <input type="submit" value="Submit Depth Chart" class="depth-chart-submit-btn">
        </div>';
        echo '</div></form>';
    }

    /**
     * Render a single mobile card for a player
     *
     * @param array<string, mixed> $player Player data from database
     * @param int $depthCount Row counter for form field names
     */
    private function renderMobilePlayerCard(array $player, int $depthCount): void
    {
        /** @var int $pid */
        $pid = $player['pid'];
        /** @var string $pos */
        $pos = $player['pos'];
        $name = $player['name'];
        /** @var int $injured */
        $injured = $player['injured'] ?? 0;

        $nameHtml = HtmlSanitizer::safeHtmlOutput($name);
        $posHtml = HtmlSanitizer::safeHtmlOutput($pos);
        $imageUrl = \Player\PlayerImageHelper::getImageUrl($pid);

        /** @var int $dcActive */
        $dcActive = $player['dc_canPlayInGame'] ?? 0;
        $checkedAttr = ($dcActive === 1) ? ' checked' : '';

        /** @var float $qualityScore */
        $qualityScore = $player['quality_score'] ?? 0.0;

        echo "<div class=\"dc-card\" data-pid=\"{$pid}\" data-quality=\"{$qualityScore}\" data-pos=\"{$pos}\">";

        // Header: photo + pos badge + name + active toggle
        echo '<div class="dc-card__header">';
        echo "<img class=\"dc-card__photo\" src=\"{$imageUrl}\" alt=\"\" width=\"48\" height=\"48\" loading=\"lazy\">";
        echo "<span class=\"dc-card__pos-badge\">{$posHtml}</span>";
        echo "<a href=\"./modules.php?name=Player&amp;pa=showpage&amp;pid={$pid}\" class=\"dc-card__name\">{$nameHtml}</a>";

        // Hidden fields (disabled — JS enables on mobile)
        echo "<input type=\"hidden\" name=\"pid{$depthCount}\" value=\"{$pid}\" disabled>";
        echo "<input type=\"hidden\" name=\"Injury{$depthCount}\" value=\"{$injured}\" disabled>";
        echo "<input type=\"hidden\" name=\"Name{$depthCount}\" value=\"{$nameHtml}\" disabled>";
        echo "<input type=\"hidden\" name=\"pg{$depthCount}\" value=\"0\" disabled>";
        echo "<input type=\"hidden\" name=\"sg{$depthCount}\" value=\"0\" disabled>";
        echo "<input type=\"hidden\" name=\"sf{$depthCount}\" value=\"0\" disabled>";
        echo "<input type=\"hidden\" name=\"pf{$depthCount}\" value=\"0\" disabled>";
        echo "<input type=\"hidden\" name=\"c{$depthCount}\" value=\"0\" disabled>";
        echo "<input type=\"hidden\" name=\"min{$depthCount}\" value=\"0\" disabled>";

        // Active checkbox toggle
        echo "<label class=\"dc-card__active-toggle\" aria-label=\"Active status for {$nameHtml}\">";
        echo "<input type=\"hidden\" name=\"canPlayInGame{$depthCount}\" value=\"0\" disabled>";
        echo "<input type=\"checkbox\" name=\"canPlayInGame{$depthCount}\" value=\"1\" class=\"dc-card__active-cb\"{$checkedAttr} disabled>";
        echo '<span class="dc-card__active-pill">Active</span>';
        echo '</label>';
        echo '</div>';

        // Body — single role slots grid (5 columns)
        echo '<div class="dc-card__body">';
        echo '<div class="dc-card__settings-grid">';

        foreach (self::ROLE_SLOTS as $slot) {
            /** @var int $dcValue */
            $dcValue = $player[$slot['dbKey']] ?? 0;
            if ($dcValue < 0) {
                $dcValue = 0;
            }
            $fieldName = $slot['field'] . $depthCount;
            $labelHtml = HtmlSanitizer::safeHtmlOutput($slot['label']);

            echo "<div class=\"dc-card__field\">";
            echo "<span class=\"dc-card__field-label\">{$labelHtml}</span>";
            echo "<select name=\"{$fieldName}\" aria-label=\"{$labelHtml} slot for {$nameHtml}\" disabled>";
            $this->renderRolePriorityOptions($dcValue, $slot['max']);
            echo '</select></div>';
        }

        echo '</div>'; // end settings grid
        echo '</div>'; // end body
        echo '</div>'; // end card
    }
}
