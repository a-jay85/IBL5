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
     * @see DepthChartEntryViewInterface::renderPositionOptions()
     */
    public function renderPositionOptions(int $selectedValue): void
    {
        $options = [
            0 => 'No',
            1 => '1st',
            2 => '2nd',
            3 => '3rd',
            4 => '4th',
            5 => 'ok'
        ];

        foreach ($options as $value => $label) {
            $selected = ($selectedValue === $value) ? ' SELECTED' : '';
            echo "<option value=\"$value\"$selected>$label</option>";
        }
    }

    /**
     * @see DepthChartEntryViewInterface::renderOffDefOptions()
     */
    public function renderOffDefOptions(int $selectedValue): void
    {
        $options = [
            0 => 'Auto',
            1 => 'Outside',
            2 => 'Drive',
            3 => 'Post'
        ];

        foreach ($options as $value => $label) {
            $selected = ($selectedValue === $value) ? ' SELECTED' : '';
            echo "<option value=\"$value\"$selected>$label</option>";
        }
    }

    /**
     * @see DepthChartEntryViewInterface::renderSettingOptions()
     */
    public function renderSettingOptions(int $selectedValue): void
    {
        $options = [2, 1, 0, -1, -2];

        foreach ($options as $value) {
            $selected = ($selectedValue === $value) ? ' SELECTED' : '';
            $label = ($value === 0) ? '-' : $value;
            echo "<option value=\"$value\"$selected>$label</option>";
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
     * @see DepthChartEntryViewInterface::renderMinutesOptions()
     */
    public function renderMinutesOptions(int $selectedValue, int $staminaCap): void
    {
        echo '<option value="0"' . ($selectedValue === 0 ? ' SELECTED' : '') . '>Auto</option>';

        for ($i = 1; $i <= $staminaCap; $i++) {
            $selected = ($selectedValue === $i) ? ' SELECTED' : '';
            echo "<option value=\"$i\"$selected>$i</option>";
        }
    }

    /**
     * @see DepthChartEntryViewInterface::renderFormHeader()
     */
    public function renderFormHeader(string $teamLogo, int $teamID, array $slotNames): void
    {
        $teamLogoEscaped = HtmlSanitizer::safeHtmlOutput($teamLogo);
        $slot0 = HtmlSanitizer::safeHtmlOutput($slotNames[0]);
        $slot1 = HtmlSanitizer::safeHtmlOutput($slotNames[1]);
        $slot2 = HtmlSanitizer::safeHtmlOutput($slotNames[2]);
        $slot3 = HtmlSanitizer::safeHtmlOutput($slotNames[3]);
        $slot4 = HtmlSanitizer::safeHtmlOutput($slotNames[4]);
        echo '<form name="DepthChartEntry" method="post" action="modules.php?name=DepthChartEntry&amp;op=submit" class="depth-chart-form">
            <input type="hidden" name="Team_Name" value="' . $teamLogoEscaped . '">
            <input type="hidden" name="loaded_dc_id" id="loaded_dc_id" value="0">';

        echo '<div class="text-center"><table class="depth-chart-table ibl-data-table" data-no-responsive>
            <thead>
                <tr>
                    <th>Pos</th>
                    <th>Player</th>
                    <th>' . $slot0 . '</th>
                    <th>' . $slot1 . '</th>
                    <th>' . $slot2 . '</th>
                    <th>' . $slot3 . '</th>
                    <th>' . $slot4 . '</th>
                    <th>active?</th>
                    <th>min</th>
                    <th>OF</th>
                    <th>DF</th>
                    <th>OI</th>
                    <th>DI</th>
                    <th>BH</th>
                </tr>
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

        $player_staminacap = ($player['sta'] ?? 0) + 40;
        if ($player_staminacap > 40) {
            $player_staminacap = 40;
        }

        echo "<tr data-pid=\"{$player_pid}\">
            <td>{$player_pos}</td>
            <td nowrap>
                <input type=\"hidden\" name=\"pid{$depthCount}\" value=\"{$player_pid}\">
                <input type=\"hidden\" name=\"Injury{$depthCount}\" value=\"{$player_inj}\">
                <input type=\"hidden\" name=\"Name{$depthCount}\" value=\"{$player_name_html}\">
                <a href=\"./modules.php?name=Player&pa=showpage&pid={$player_pid}\">{$player_name_html}</a>
            </td>";

        $positions = ['pg', 'sg', 'sf', 'pf', 'c'];
        foreach ($positions as $posKey) {
            $this->renderPositionCell($player, $posKey, $depthCount);
        }

        $dcActive = $player['dc_canPlayInGame'] ?? 0;
        $dcMinutes = $player['dc_minutes'] ?? 0;
        $dcOf = $player['dc_of'] ?? 0;
        $dcDf = $player['dc_df'] ?? 0;
        $dcOi = $player['dc_oi'] ?? 0;
        $dcDi = $player['dc_di'] ?? 0;
        $dcBh = $player['dc_bh'] ?? 0;

        echo "<td><select name=\"canPlayInGame{$depthCount}\" aria-label=\"Active status for {$player_name_html}\">";
        $this->renderActiveOptions($dcActive);
        echo "</select></td>";

        echo "<td><select name=\"min{$depthCount}\" aria-label=\"Minutes for {$player_name_html}\">";
        $this->renderMinutesOptions($dcMinutes, $player_staminacap);
        echo "</select></td>";

        echo "<td><select name=\"OF{$depthCount}\" aria-label=\"Offense for {$player_name_html}\">";
        $this->renderOffDefOptions($dcOf);
        echo "</select></td>";

        echo "<td><select name=\"DF{$depthCount}\" aria-label=\"Defense for {$player_name_html}\">";
        $this->renderOffDefOptions($dcDf);
        echo "</select></td>";

        echo "<td><select name=\"OI{$depthCount}\" aria-label=\"Offensive intensity for {$player_name_html}\">";
        $this->renderSettingOptions($dcOi);
        echo "</select></td>";

        echo "<td><select name=\"DI{$depthCount}\" aria-label=\"Defensive intensity for {$player_name_html}\">";
        $this->renderSettingOptions($dcDi);
        echo "</select></td>";

        echo "<td><select name=\"BH{$depthCount}\" aria-label=\"Ball handling for {$player_name_html}\">";
        $this->renderSettingOptions($dcBh);
        echo "</select></td></tr>";
    }

    /**
     * @param PlayerRow $player
     */
    private function renderPositionCell(array $player, string $posKey, int $depthCount): void
    {
        $fieldName = $posKey . $depthCount;
        $dcField = 'dc_' . strtoupper($posKey) . 'Depth';
        /** @var int $currentValue */
        $currentValue = $player[$dcField];

        $posLabel = strtoupper($posKey);
        $playerNameHtml = HtmlSanitizer::safeHtmlOutput($player['name']);
        echo "<td><select name=\"{$fieldName}\" aria-label=\"{$posLabel} depth for {$playerNameHtml}\">";
        $this->renderPositionOptions($currentValue);
        echo "</select></td>";
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
        } else if (name.match(/^(pg|sg|sf|pf|c)\d+$/)) {
            defaultValue = '0';
        } else if (name.match(/^(min|OF|DF)\d+$/)) {
            defaultValue = '0';
        } else if (name.match(/^(OI|DI|BH)\d+$/)) {
            defaultValue = '0';
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

    return false;
}
</script>
JAVASCRIPT;

        echo $resetScript;
        echo '</tbody>
            <tfoot>
                <tr>
                    <td colspan="14" class="depth-chart-buttons">
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
            <th>Name</th>';
        foreach (\JSB::PLAYER_POSITIONS as $position) {
            $posHtml = HtmlSanitizer::safeHtmlOutput($position);
            echo '<th>' . $posHtml . '</th>';
        }
        echo '<th>Active</th>
            <th>Min</th>
            <th>OF</th>
            <th>DF</th>
            <th>OI</th>
            <th>DI</th>
            <th>BH</th>
        </tr></thead><tbody>';

        foreach ($playerData as $player) {
            $nameHtml = HtmlSanitizer::safeHtmlOutput($player['name']);
            echo '<tr>
                <td>' . $nameHtml . '</td>';
            foreach (\JSB::PLAYER_POSITIONS as $position) {
                $posKey = strtolower($position);
                echo '<td>' . $player[$posKey] . '</td>';
            }
            echo '<td>' . $player['canPlayInGame'] . '</td>
                <td>' . $player['min'] . '</td>
                <td>' . $player['of'] . '</td>
                <td>' . $player['df'] . '</td>
                <td>' . $player['oi'] . '</td>
                <td>' . $player['di'] . '</td>
                <td>' . $player['bh'] . '</td>
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
            $this->renderMobilePlayerCard($player, $depthCount, $slotNames);
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
     * @param array<string> $slotNames Position slot names
     */
    private function renderMobilePlayerCard(array $player, int $depthCount, array $slotNames): void
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

        /** @var int $sta */
        $sta = $player['sta'] ?? 0;
        $staminaCap = $sta + 40;
        if ($staminaCap > 40) {
            $staminaCap = 40;
        }

        echo "<div class=\"dc-card\" data-pid=\"{$pid}\">";

        // Header: photo + pos badge + name + active toggle
        echo '<div class="dc-card__header">';
        echo "<img class=\"dc-card__photo\" src=\"{$imageUrl}\" alt=\"\" width=\"48\" height=\"48\" loading=\"lazy\">";
        echo "<span class=\"dc-card__pos-badge\">{$posHtml}</span>";
        echo "<a href=\"./modules.php?name=Player&amp;pa=showpage&amp;pid={$pid}\" class=\"dc-card__name\">{$nameHtml}</a>";

        // Hidden fields (disabled — JS enables on mobile)
        echo "<input type=\"hidden\" name=\"pid{$depthCount}\" value=\"{$pid}\" disabled>";
        echo "<input type=\"hidden\" name=\"Injury{$depthCount}\" value=\"{$injured}\" disabled>";
        echo "<input type=\"hidden\" name=\"Name{$depthCount}\" value=\"{$nameHtml}\" disabled>";

        // Active checkbox toggle
        echo "<label class=\"dc-card__active-toggle\" aria-label=\"Active status for {$nameHtml}\">";
        echo "<input type=\"hidden\" name=\"canPlayInGame{$depthCount}\" value=\"0\" disabled>";
        echo "<input type=\"checkbox\" name=\"canPlayInGame{$depthCount}\" value=\"1\" class=\"dc-card__active-cb\"{$checkedAttr} disabled>";
        echo '<span class="dc-card__active-pill">Active</span>';
        echo '</label>';
        echo '</div>';

        // Body
        echo '<div class="dc-card__body">';

        // Position slots grid (5 columns)
        echo '<div class="dc-card__pos-grid">';
        $positions = ['pg', 'sg', 'sf', 'pf', 'c'];
        foreach ($positions as $idx => $posKey) {
            $dcField = 'dc_' . strtoupper($posKey) . 'Depth';
            /** @var int $currentValue */
            $currentValue = $player[$dcField];
            $label = $slotNames[$idx] ?? strtoupper($posKey);
            $labelHtml = HtmlSanitizer::safeHtmlOutput($label);

            echo "<div class=\"dc-card__field\">";
            echo "<span class=\"dc-card__field-label\">{$labelHtml}</span>";
            echo "<select name=\"{$posKey}{$depthCount}\" aria-label=\"{$labelHtml} depth for {$nameHtml}\" disabled>";
            $this->renderPositionOptions($currentValue);
            echo '</select></div>';
        }
        echo '</div>';

        echo '<hr class="dc-card__divider">';

        // Settings grid (6 columns)
        echo '<div class="dc-card__settings-grid">';

        /** @var int $dcMinutes */
        $dcMinutes = $player['dc_minutes'] ?? 0;
        /** @var int $dcOf */
        $dcOf = $player['dc_of'] ?? 0;
        /** @var int $dcDf */
        $dcDf = $player['dc_df'] ?? 0;
        /** @var int $dcOi */
        $dcOi = $player['dc_oi'] ?? 0;
        /** @var int $dcDi */
        $dcDi = $player['dc_di'] ?? 0;
        /** @var int $dcBh */
        $dcBh = $player['dc_bh'] ?? 0;

        // Minutes
        echo "<div class=\"dc-card__field\">";
        echo '<span class="dc-card__field-label">Min</span>';
        echo "<select name=\"min{$depthCount}\" aria-label=\"Minutes for {$nameHtml}\" disabled>";
        $this->renderMinutesOptions($dcMinutes, $staminaCap);
        echo '</select></div>';

        // OF
        echo "<div class=\"dc-card__field\">";
        echo '<span class="dc-card__field-label">OF</span>';
        echo "<select name=\"OF{$depthCount}\" aria-label=\"Offense for {$nameHtml}\" disabled>";
        $this->renderOffDefOptions($dcOf);
        echo '</select></div>';

        // DF
        echo "<div class=\"dc-card__field\">";
        echo '<span class="dc-card__field-label">DF</span>';
        echo "<select name=\"DF{$depthCount}\" aria-label=\"Defense for {$nameHtml}\" disabled>";
        $this->renderOffDefOptions($dcDf);
        echo '</select></div>';

        // OI
        echo "<div class=\"dc-card__field\">";
        echo '<span class="dc-card__field-label">OI</span>';
        echo "<select name=\"OI{$depthCount}\" aria-label=\"Offensive intensity for {$nameHtml}\" disabled>";
        $this->renderSettingOptions($dcOi);
        echo '</select></div>';

        // DI
        echo "<div class=\"dc-card__field\">";
        echo '<span class="dc-card__field-label">DI</span>';
        echo "<select name=\"DI{$depthCount}\" aria-label=\"Defensive intensity for {$nameHtml}\" disabled>";
        $this->renderSettingOptions($dcDi);
        echo '</select></div>';

        // BH
        echo "<div class=\"dc-card__field\">";
        echo '<span class="dc-card__field-label">BH</span>';
        echo "<select name=\"BH{$depthCount}\" aria-label=\"Ball handling for {$nameHtml}\" disabled>";
        $this->renderSettingOptions($dcBh);
        echo '</select></div>';

        echo '</div>'; // end settings grid
        echo '</div>'; // end body
        echo '</div>'; // end card
    }
}
