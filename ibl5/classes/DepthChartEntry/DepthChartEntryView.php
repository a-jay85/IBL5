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
    public function __construct(private readonly DepthChartEntryProcessor $processor)
    {
    }

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
        /** @var string $teamLogoEscaped */
        $teamLogoEscaped = HtmlSanitizer::safeHtmlOutput($teamLogo);
        /** @var string $slot0 */
        $slot0 = HtmlSanitizer::safeHtmlOutput($slotNames[0]);
        /** @var string $slot1 */
        $slot1 = HtmlSanitizer::safeHtmlOutput($slotNames[1]);
        /** @var string $slot2 */
        $slot2 = HtmlSanitizer::safeHtmlOutput($slotNames[2]);
        /** @var string $slot3 */
        $slot3 = HtmlSanitizer::safeHtmlOutput($slotNames[3]);
        /** @var string $slot4 */
        $slot4 = HtmlSanitizer::safeHtmlOutput($slotNames[4]);
        echo '<form name="DepthChartEntry" method="post" action="modules.php?name=DepthChartEntry&amp;op=submit" class="depth-chart-form">
            <input type="hidden" name="Team_Name" value="' . $teamLogoEscaped . '">';

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
                    <th>active</th>
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

        /** @var string $player_name_html */
        $player_name_html = HtmlSanitizer::safeHtmlOutput($player_name);

        $player_staminacap = ($player['sta'] ?? 0) + 40;
        if ($player_staminacap > 40) {
            $player_staminacap = 40;
        }

        echo "<tr>
            <td>{$player_pos}</td>
            <td nowrap>
                <input type=\"hidden\" name=\"Injury{$depthCount}\" value=\"{$player_inj}\">
                <input type=\"hidden\" name=\"Name{$depthCount}\" value=\"{$player_name_html}\">
                <a href=\"./modules.php?name=Player&pa=showpage&pid={$player_pid}\">{$player_name_html}</a>
            </td>";

        $positions = ['pg', 'sg', 'sf', 'pf', 'c'];
        foreach ($positions as $posKey) {
            $this->renderPositionCell($player, $posKey, $depthCount);
        }

        $dcActive = $player['dc_active'] ?? 0;
        $dcMinutes = $player['dc_minutes'] ?? 0;
        $dcOf = $player['dc_of'] ?? 0;
        $dcDf = $player['dc_df'] ?? 0;
        $dcOi = $player['dc_oi'] ?? 0;
        $dcDi = $player['dc_di'] ?? 0;
        $dcBh = $player['dc_bh'] ?? 0;

        echo "<td><select name=\"active{$depthCount}\">";
        $this->renderActiveOptions($dcActive);
        echo "</select></td>";

        echo "<td><select name=\"min{$depthCount}\">";
        $this->renderMinutesOptions($dcMinutes, $player_staminacap);
        echo "</select></td>";

        echo "<td><select name=\"OF{$depthCount}\">";
        $this->renderOffDefOptions($dcOf);
        echo "</select></td>";

        echo "<td><select name=\"DF{$depthCount}\">";
        $this->renderOffDefOptions($dcDf);
        echo "</select></td>";

        echo "<td><select name=\"OI{$depthCount}\">";
        $this->renderSettingOptions($dcOi);
        echo "</select></td>";

        echo "<td><select name=\"DI{$depthCount}\">";
        $this->renderSettingOptions($dcDi);
        echo "</select></td>";

        echo "<td><select name=\"BH{$depthCount}\">";
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

        echo "<td><select name=\"{$fieldName}\">";
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
        
        if (name.match(/^active\d+$/)) {
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
        </table></div></form>';
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

        /** @var string $teamNameHtml */
        $teamNameHtml = HtmlSanitizer::safeHtmlOutput($teamName);
        echo $teamNameHtml . ' Depth Chart Submission<br><table class="ibl-data-table">';
        echo '<thead><tr>
            <th>Name</th>';
        foreach (\JSB::PLAYER_POSITIONS as $position) {
            /** @var string $posHtml */
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
            /** @var string $nameHtml */
            $nameHtml = HtmlSanitizer::safeHtmlOutput($player['name']);
            echo '<tr>
                <td>' . $nameHtml . '</td>';
            foreach (\JSB::PLAYER_POSITIONS as $position) {
                $posKey = strtolower($position);
                echo '<td>' . $player[$posKey] . '</td>';
            }
            echo '<td>' . $player['active'] . '</td>
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
}
