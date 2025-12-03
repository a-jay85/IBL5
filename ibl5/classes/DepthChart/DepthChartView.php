<?php

declare(strict_types=1);

namespace DepthChart;

use DepthChart\Contracts\DepthChartViewInterface;
use Services\DatabaseService;

/**
 * @see DepthChartViewInterface
 */
class DepthChartView implements DepthChartViewInterface
{
    private $processor;

    public function __construct(DepthChartProcessor $processor)
    {
        $this->processor = $processor;
    }

    /**
     * @see DepthChartViewInterface::renderTeamLogo()
     */
    public function renderTeamLogo(int $teamID): void
    {
        echo "<center><img src=\"images/logo/$teamID.jpg\"></center><br>";
    }

    /**
     * @see DepthChartViewInterface::renderPositionOptions()
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
            $selected = ($selectedValue == $value) ? ' SELECTED' : '';
            echo "<option value=\"$value\"$selected>$label</option>";
        }
    }

    /**
     * @see DepthChartViewInterface::renderOffDefOptions()
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
            $selected = ($selectedValue == $value) ? ' SELECTED' : '';
            echo "<option value=\"$value\"$selected>$label</option>";
        }
    }

    /**
     * @see DepthChartViewInterface::renderSettingOptions()
     */
    public function renderSettingOptions(int $selectedValue): void
    {
        $options = [2, 1, 0, -1, -2];

        foreach ($options as $value) {
            $selected = ($selectedValue == $value) ? ' SELECTED' : '';
            $label = ($value == 0) ? '-' : $value;
            echo "<option value=\"$value\"$selected>$label</option>";
        }
    }

    /**
     * @see DepthChartViewInterface::renderActiveOptions()
     */
    public function renderActiveOptions(int $selectedValue): void
    {
        if ($selectedValue == 1) {
            echo '<option value="1" SELECTED>Yes</option><option value="0">No</option>';
        } else {
            echo '<option value="1">Yes</option><option value="0" SELECTED>No</option>';
        }
    }

    /**
     * @see DepthChartViewInterface::renderMinutesOptions()
     */
    public function renderMinutesOptions(int $selectedValue, int $staminaCap): void
    {
        echo '<option value="0"' . ($selectedValue == 0 ? ' SELECTED' : '') . '>Auto</option>';

        for ($i = 1; $i <= $staminaCap; $i++) {
            $selected = ($selectedValue == $i) ? ' SELECTED' : '';
            echo "<option value=\"$i\"$selected>$i</option>";
        }
    }

    /**
     * @see DepthChartViewInterface::renderFormHeader()
     */
    public function renderFormHeader(string $teamLogo, int $teamID, array $slotNames): void
    {
        echo "<form name=\"Depth_Chart\" method=\"post\" action=\"modules.php?name=Depth_Chart_Entry&op=submit\">
            <input type=\"hidden\" name=\"Team_Name\" value=\"$teamLogo\">";

        echo "<p><center><table>
            <tr>
                <th colspan=14><center>DEPTH CHART ENTRY</center></th>
            </tr>
            <tr>
                <th>Pos</th>
                <th>Player</th>
                <th>{$slotNames[0]}</th>
                <th>{$slotNames[1]}</th>
                <th>{$slotNames[2]}</th>
                <th>{$slotNames[3]}</th>
                <th>{$slotNames[4]}</th>
                <th>active</th>
                <th>min</th>
                <th>OF</th>
                <th>DF</th>
                <th>OI</th>
                <th>DI</th>
                <th>BH</th>
            </tr>";
    }

    /**
     * @see DepthChartViewInterface::renderPlayerRow()
     */
    public function renderPlayerRow(array $player, int $depthCount): void
    {
        $player_pid = $player['pid'];
        $player_pos = $player['pos'];
        $player_name = $player['name'];
        $player_inj = $player['injured'];

        $player_name_html = DatabaseService::safeHtmlOutput($player_name);

        $player_staminacap = (int)$player['sta'] + 40;
        if ($player_staminacap > 40) {
            $player_staminacap = 40;
        }

        echo "<tr>
            <td>$player_pos</td>
            <td nowrap>
                <input type=\"hidden\" name=\"Injury$depthCount\" value=\"$player_inj\">
                <input type=\"hidden\" name=\"Name$depthCount\" value=\"$player_name_html\">
                <a href=\"./modules.php?name=Player&pa=showpage&pid=$player_pid\">$player_name_html</a>
            </td>";

        $positions = ['pg', 'sg', 'sf', 'pf', 'c'];
        foreach ($positions as $posKey) {
            $this->renderPositionCell($player, $posKey, $depthCount);
        }

        echo "<td><select name=\"active$depthCount\">";
        $this->renderActiveOptions((int)$player['dc_active']);
        echo "</select></td>";

        echo "<td><select name=\"min$depthCount\">";
        $this->renderMinutesOptions((int)$player['dc_minutes'], $player_staminacap);
        echo "</select></td>";

        echo "<td><select name=\"OF$depthCount\">";
        $this->renderOffDefOptions((int)$player['dc_of']);
        echo "</select></td>";

        echo "<td><select name=\"DF$depthCount\">";
        $this->renderOffDefOptions((int)$player['dc_df']);
        echo "</select></td>";

        echo "<td><select name=\"OI$depthCount\">";
        $this->renderSettingOptions((int)$player['dc_oi']);
        echo "</select></td>";

        echo "<td><select name=\"DI$depthCount\">";
        $this->renderSettingOptions((int)$player['dc_di']);
        echo "</select></td>";

        echo "<td><select name=\"BH$depthCount\">";
        $this->renderSettingOptions((int)$player['dc_bh']);
        echo "</select></td></tr>";
    }

    private function renderPositionCell(array $player, string $posKey, int $depthCount): void
    {
        $fieldName = $posKey . $depthCount;
        $dcField = 'dc_' . strtoupper($posKey) . 'Depth';
        $currentValue = (int)$player[$dcField];

        echo "<td><select name=\"$fieldName\">";
        $this->renderPositionOptions($currentValue);
        echo "</select></td>";
    }

    /**
     * @see DepthChartViewInterface::renderFormFooter()
     */
    public function renderFormFooter(): void
    {
        $resetScript = <<<'JAVASCRIPT'
<script type="text/javascript">
function resetDepthChart() {
    if (!confirm('Are you sure you want to reset all fields to their default values? This will discard any changes you have made.')) {
        return false;
    }
    
    var form = document.forms['Depth_Chart'];
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
        echo "<tr>
            <th colspan=14>
                <input type=\"button\" value=\"Reset\" onclick=\"resetDepthChart();\" style=\"margin-right: 20px; background-color: #f0f0f0; color: #666; border: 1px solid #999; padding: 6px 12px; cursor: pointer;\">
                <input type=\"submit\" value=\"Submit Depth Chart\" style=\"background-color: #28a745; color: white; border: 2px solid #1e7e34; padding: 8px 20px; cursor: pointer; font-weight: bold;\">
            </th>
        </tr></form></table></center>";
    }

    /**
     * @see DepthChartViewInterface::renderSubmissionResult()
     */
    public function renderSubmissionResult(
        string $teamName,
        array $playerData,
        bool $success,
        string $errorHtml = ''
    ): void {
        if (!$success) {
            echo "<center><u>Your lineup has <b>not</b> been submitted:</u></center><br>";
            echo $errorHtml;
        } else {
            echo "<center><u>Your depth chart has been submitted and e-mailed successfully. Thank you.</u></center><p>";
        }

        echo "$teamName Depth Chart Submission<br><table>";
        echo "<tr>
            <td><b>Name</td>";
        foreach (\JSB::PLAYER_POSITIONS as $position) {
            echo "<td><b>$position</td>";
        }
        echo "<td><b>Active</td>
            <td><b>Min</td>
            <td><b>OF</td>
            <td><b>DF</td>
            <td><b>OI</td>
            <td><b>DI</td>
            <td><b>BH</td>
        </tr>";

        foreach ($playerData as $player) {
            echo "<tr>
                <td>{$player['name']}</td>";
            foreach (\JSB::PLAYER_POSITIONS as $position) {
                $posKey = strtolower($position);
                echo "<td>{$player[$posKey]}</td>";
            }
            echo "<td>{$player['active']}</td>
                <td>{$player['min']}</td>
                <td>{$player['of']}</td>
                <td>{$player['df']}</td>
                <td>{$player['oi']}</td>
                <td>{$player['di']}</td>
                <td>{$player['bh']}</td>
            </tr>";
        }

        echo "</table>";
    }
}
