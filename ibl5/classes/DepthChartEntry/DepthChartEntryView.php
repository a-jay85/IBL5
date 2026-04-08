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
     *
     * Label convention:
     *   0 → "—"  (unassigned; falls back to player's position string)
     *   1 → "S"  (starter; JSB's dc=1 pass-2 sort strictly dominates dc=2+)
     *   2+ → "#N" (successive backups in the per-slot ladder)
     */
    public function renderRolePriorityOptions(int $selectedValue, int $maxValue): void
    {
        for ($i = 0; $i <= $maxValue; $i++) {
            $selected = ($selectedValue === $i) ? ' SELECTED' : '';
            $label = match (true) {
                $i === 0 => '&mdash;',
                $i === 1 => 'S',
                default  => '#' . $i,
            };
            echo "<option value=\"{$i}\"{$selected}>{$label}</option>";
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
<ol>
<li>Each row in the table is one of your players.</li>
<li>The five columns – <strong>PG SG SF PF C</strong> – are the five lineup slots you fill.</li>
<li>For each slot, tell the sim who you want to play there:</li>
</ol>
<table class="ibl-data-table dc-help-table">
<thead><tr><th>Option</th><th>Meaning</th></tr></thead>
<tbody>
<tr><td><strong>S</strong></td><td>Starter</td></tr>
<tr><td><strong>#2</strong></td><td>Main backup</td></tr>
<tr><td><strong>#3</strong></td><td>Second backup</td></tr>
<tr><td><strong>&mdash;</strong></td><td>N/A (use for deep bench)</td></tr>
</tbody>
</table>
</p>
<p><strong>To put a player in the slot you want:</strong></p>
<ol>
<li>Set <strong>one</strong> player to <strong>S</strong> for each position.</li>
<li>Set <strong>#2</strong> for players you want to sub in first.</li>
<li>Set <strong>#3</strong> for player(s) after that.</li>
<li>You can pick different backups for each slot.</li>
<li>Set <strong>Min</strong> to control how long each player is on the floor.</li>
<li>Starters usually want 30&ndash;40; bench players want lower numbers.</li>
<li>Players with 0 minutes will only come in if everyone above them is unavailable.</li>
</ol>
<p><strong>Projected Lineup:</strong><br></p>
<p>If a name appears in <em>italic gray</em>, it means you didn&rsquo;t assign
enough players to that slot, so the sim is falling back on a backup automatically.
<br>Add a <strong>#2</strong> or <strong>#3</strong> to the player you actually want there.</p>
<p><strong>Note:</strong> a starter only plays their <strong>one</strong> slot –
starters are locked to that slot and removed from every other slot&rsquo;s
ladder. If you want one backup to cover multiple slots, set <strong>#2</strong> or
<strong>#3</strong> on them in several columns and leave <strong>S</strong> off &mdash;
then they&rsquo;ll appear as a backup in each slot&rsquo;s ladder.</p>
<p>The sim fills slots in order <strong>PG &rarr; SG &rarr; SF &rarr; PF &rarr; C</strong>,
so if two slots both have a viable <strong>S</strong> pick that includes the same player,
the earlier slot in that order claims them.</p>
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

        echo '              <th>Min</th>
                </tr>
            </thead>
            <tbody>';
    }

    /**
     * Compute the JSB production composite used by FUN_0040af90's roster sort.
     *
     * Per the verified formula at jsb560_decompiled.c:5723-5728 and the field
     * offset table in 00_MASTER_REFERENCE.md "Season Stats (0x60C-0x640+)":
     *
     *   production = 2 × FGM_2pt + 3 × FGM_3pt + FTM + ORB + DRB + AST + STL + BLK
     *
     * `ibl_plr` stores TOTAL FGM (2pt + 3pt combined) in `stats_fgm` and 3pt
     * FGM separately in `stats_3gm`, so the JSB-side `2 × FGM_2pt + 3 × FGM_3pt`
     * simplifies to `2 × stats_fgm + stats_3gm` (the algebra:
     * `2(stats_fgm − stats_3gm) + 3 × stats_3gm = 2 × stats_fgm + stats_3gm`).
     *
     * The result is the inner production sum only — the
     * `(dc_minutes + 100) ×` multiplier is applied client-side so it stays
     * live as the GM edits dc_minutes. The global scale `_DAT_00669ab8` is a
     * constant and doesn't affect sort order.
     *
     * @param PlayerRow $player
     */
    private function computeJsbProduction(array $player): int
    {
        /** @var int $fgm */
        $fgm = $player['stats_fgm'] ?? 0;
        /** @var int $tgm */
        $tgm = $player['stats_3gm'] ?? 0;
        /** @var int $ftm */
        $ftm = $player['stats_ftm'] ?? 0;
        /** @var int $orb */
        $orb = $player['stats_orb'] ?? 0;
        /** @var int $drb */
        $drb = $player['stats_drb'] ?? 0;
        /** @var int $ast */
        $ast = $player['stats_ast'] ?? 0;
        /** @var int $stl */
        $stl = $player['stats_stl'] ?? 0;
        /** @var int $blk */
        $blk = $player['stats_blk'] ?? 0;

        return 2 * $fgm + $tgm + $ftm + $orb + $drb + $ast + $stl + $blk;
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
        $jsbProduction = $this->computeJsbProduction($player);

        $player_name_html = HtmlSanitizer::safeHtmlOutput($player_name);

        $thumbnail = \Player\PlayerImageHelper::renderThumbnail($player_pid);

        echo "<tr data-pid=\"{$player_pid}\" data-pos=\"{$player_pos}\" data-jsb-production=\"{$jsbProduction}\">
            <td>{$player_pos}</td>
            <td class=\"ibl-player-cell\">
                <input type=\"hidden\" name=\"pid{$depthCount}\" value=\"{$player_pid}\">
                <input type=\"hidden\" name=\"Injury{$depthCount}\" value=\"{$player_inj}\">
                <input type=\"hidden\" name=\"Name{$depthCount}\" value=\"{$player_name_html}\">
                <input type=\"hidden\" name=\"pg{$depthCount}\" value=\"0\">
                <input type=\"hidden\" name=\"sg{$depthCount}\" value=\"0\">
                <input type=\"hidden\" name=\"sf{$depthCount}\" value=\"0\">
                <input type=\"hidden\" name=\"pf{$depthCount}\" value=\"0\">
                <input type=\"hidden\" name=\"c{$depthCount}\" value=\"0\">
                <a href=\"./modules.php?name=Player&amp;pa=showpage&amp;pid={$player_pid}\">{$thumbnail}{$player_name_html}</a>
            </td>";

        // Active status — hidden input submits "0" when checkbox is unchecked;
        // the checkbox submits "1" when checked. Two fields share the same
        // name so the form posts the right value regardless of checkbox state.
        /** @var int $dcActive */
        $dcActive = $player['dc_canPlayInGame'] ?? 0;
        $activeCheckedAttr = ($dcActive === 1) ? ' checked' : '';
        echo "<td class=\"dc-active-cell\">";
        echo "<input type=\"hidden\" name=\"canPlayInGame{$depthCount}\" value=\"0\">";
        echo "<input type=\"checkbox\" name=\"canPlayInGame{$depthCount}\" value=\"1\" class=\"dc-active-cb\"{$activeCheckedAttr} aria-label=\"Active status for {$player_name_html}\">";
        echo "</td>";

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

        // Minutes — number input constrained to 0-40 with native browser
        // stepper. The server sanitizes to the same 0-40 range in
        // DepthChartEntryProcessor::sanitizeMinutesValue().
        /** @var int $dcMinutes */
        $dcMinutes = $player['dc_minutes'] ?? 0;
        echo "<td class=\"dc-minutes-cell\"><input type=\"number\" name=\"min{$depthCount}\" value=\"{$dcMinutes}\" min=\"0\" max=\"40\" step=\"1\" class=\"dc-minutes-input\" aria-label=\"Minutes for {$player_name_html}\"></td>";

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

    // Reset role slot selects (BH/DI/OI/DF/OF) to 0
    var selects = form.getElementsByTagName('select');
    for (var i = 0; i < selects.length; i++) {
        selects[i].value = '0';
    }

    // Reset minutes number inputs to blank — the server's extractIntValue()
    // converts blank → 0 on submit, so this leaves the GM with an empty
    // field they can type into rather than a stale "0" they have to clear.
    var minInputs = form.querySelectorAll('input[type="number"][name^="min"]');
    for (var j = 0; j < minInputs.length; j++) {
        minInputs[j].value = '';
    }

    // Reset active checkboxes (canPlayInGame) to checked — both desktop
    // (.dc-active-cb) and mobile (.dc-card__active-cb) are covered by the
    // name prefix selector.
    var activeCheckboxes = form.querySelectorAll('input[type="checkbox"][name^="canPlayInGame"]');
    for (var k = 0; k < activeCheckboxes.length; k++) {
        activeCheckboxes[k].checked = true;
        var card = activeCheckboxes[k].closest('.dc-card');
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
                    <td colspan="9" class="depth-chart-buttons">
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
     * @param list<PlayerRow> $players
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
     * @param PlayerRow $player Player data from database
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
        $jsbProduction = $this->computeJsbProduction($player);

        $nameHtml = HtmlSanitizer::safeHtmlOutput($name);
        $posHtml = HtmlSanitizer::safeHtmlOutput($pos);
        $imageUrl = \Player\PlayerImageHelper::getImageUrl($pid);

        /** @var int $dcActive */
        $dcActive = $player['dc_canPlayInGame'] ?? 0;
        $checkedAttr = ($dcActive === 1) ? ' checked' : '';

        echo "<div class=\"dc-card\" data-pid=\"{$pid}\" data-pos=\"{$pos}\" data-jsb-production=\"{$jsbProduction}\">";

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
        // Active checkbox — native checkbox styled with an orange accent to
        // match the desktop view. Hidden input submits "0" when unchecked; the
        // checkbox submits "1" when checked. Both share the same field name so
        // the form posts the right value regardless of checkbox state.
        echo "<input type=\"hidden\" name=\"canPlayInGame{$depthCount}\" value=\"0\" disabled>";
        echo "<input type=\"checkbox\" name=\"canPlayInGame{$depthCount}\" value=\"1\" class=\"dc-card__active-cb\"{$checkedAttr} aria-label=\"Active status for {$nameHtml}\" disabled>";
        echo '</div>';

        // Body — minutes + role slots grid (6 columns)
        echo '<div class="dc-card__body">';
        echo '<div class="dc-card__settings-grid">';

        // Minutes — number input constrained to 0-40 with native stepper.
        /** @var int $dcMinutes */
        $dcMinutes = $player['dc_minutes'] ?? 0;
        echo "<div class=\"dc-card__field\">";
        echo '<span class="dc-card__field-label">Min</span>';
        echo "<input type=\"number\" name=\"min{$depthCount}\" value=\"{$dcMinutes}\" min=\"0\" max=\"40\" step=\"1\" class=\"dc-minutes-input\" aria-label=\"Minutes for {$nameHtml}\" disabled>";
        echo '</div>';

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
