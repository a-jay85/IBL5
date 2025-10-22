<?php

namespace DepthChart;

/**
 * Renders depth chart views and forms
 */
class DepthChartView
{
    private $processor;
    
    public function __construct(DepthChartProcessor $processor)
    {
        $this->processor = $processor;
    }
    
    /**
     * Renders position depth select options
     * 
     * @param int $selectedValue Currently selected value
     * @return void Echoes HTML
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
     * Renders offensive/defensive focus options
     * 
     * @param int $selectedValue Currently selected value
     * @return void Echoes HTML
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
     * Renders OI/DI/BH setting options
     * 
     * @param int $selectedValue Currently selected value
     * @return void Echoes HTML
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
     * Renders active/inactive options
     * 
     * @param int $selectedValue Currently selected value (1 or 0)
     * @return void Echoes HTML
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
     * Renders minutes options based on stamina
     * 
     * @param int $selectedValue Currently selected value
     * @param int $staminaCap Stamina cap for player
     * @return void Echoes HTML
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
     * Renders offensive set selector links
     * 
     * @param mixed $offenseSetsResult Database result with offensive sets
     * @param object $db Database object
     * @return void Echoes HTML
     */
    public function renderOffenseSetLinks($offenseSetsResult, $db): void
    {
        echo "SELECT OFFENSIVE SET TO USE: ";
        
        for ($i = 0; $i < 3; $i++) {
            $name_of_set = $db->sql_result($offenseSetsResult, $i, "offense_name");
            $setNumber = $i + 1;
            echo "<a href=\"modules.php?name=Depth_Chart_Entry&useset=$setNumber\">$name_of_set</a> | ";
        }
    }
    
    /**
     * Renders the depth chart form header
     * 
     * @param string $teamLogo Team name
     * @param int $teamID Team ID
     * @param string $offenseName Offensive set name
     * @param array $slotNames Names of the 5 position slots
     * @return void Echoes HTML
     */
    public function renderFormHeader(string $teamLogo, int $teamID, string $offenseName, array $slotNames): void
    {
        echo "<form name=\"Depth_Chart\" method=\"post\" action=\"modules.php?name=Depth_Chart_Entry&op=submit\">
            <input type=\"hidden\" name=\"Team_Name\" value=\"$teamLogo\">
            <input type=\"hidden\" name=\"Set_Name\" value=\"$offenseName\">
        <center><img src=\"images/logo/$teamID.jpg\"><br>";
        
        echo "<p><table>
            <tr>
                <th colspan=14><center>DEPTH CHART ENTRY - Offensive Set: $offenseName</center></th>
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
     * Renders a player row in the depth chart form
     * 
     * @param array $player Player data from database
     * @param int $depthCount Row counter
     * @param array $slotRanges Min/max position values for each slot
     * @return void Echoes HTML
     */
    public function renderPlayerRow(array $player, int $depthCount, array $slotRanges): void
    {
        $player_pid = $player['pid'];
        $player_pos = $player['pos'];
        $player_name = $player['name'];
        $player_inj = $player['injured'];
        
        $player_staminacap = $player['sta'] + 40;
        if ($player_staminacap > 40) {
            $player_staminacap = 40;
        }
        
        echo "<tr>
            <td>$player_pos</td>
            <td nowrap>
                <input type=\"hidden\" name=\"Injury$depthCount\" value=\"$player_inj\">
                <input type=\"hidden\" name=\"Name$depthCount\" value=\"$player_name\">
                <a href=\"./modules.php?name=Player&pa=showpage&pid=$player_pid\">$player_name</a>
            </td>";
        
        // Render each position slot
        $positions = ['pg', 'sg', 'sf', 'pf', 'c'];
        foreach ($positions as $index => $posKey) {
            $this->renderPositionCell(
                $player,
                $posKey,
                $depthCount,
                $slotRanges[$index]['min'],
                $slotRanges[$index]['max']
            );
        }
        
        // Render active dropdown
        echo "<td><select name=\"active$depthCount\">";
        $this->renderActiveOptions($player['dc_active']);
        echo "</select></td>";
        
        // Render minutes dropdown
        echo "<td><select name=\"min$depthCount\">";
        $this->renderMinutesOptions($player['dc_minutes'], $player_staminacap);
        echo "</select></td>";
        
        // Render OF dropdown
        echo "<td><select name=\"OF$depthCount\">";
        $this->renderOffDefOptions($player['dc_of']);
        echo "</select></td>";
        
        // Render DF dropdown
        echo "<td><select name=\"DF$depthCount\">";
        $this->renderOffDefOptions($player['dc_df']);
        echo "</select></td>";
        
        // Render OI dropdown
        echo "<td><select name=\"OI$depthCount\">";
        $this->renderSettingOptions($player['dc_oi']);
        echo "</select></td>";
        
        // Render DI dropdown
        echo "<td><select name=\"DI$depthCount\">";
        $this->renderSettingOptions($player['dc_di']);
        echo "</select></td>";
        
        // Render BH dropdown
        echo "<td><select name=\"BH$depthCount\">";
        $this->renderSettingOptions($player['dc_bh']);
        echo "</select></td></tr>";
    }
    
    /**
     * Renders a position cell (either dropdown or hidden input)
     * 
     * @param array $player Player data
     * @param string $posKey Position key (pg, sg, sf, pf, c)
     * @param int $depthCount Row counter
     * @param int $slotMin Minimum position value
     * @param int $slotMax Maximum position value
     * @return void Echoes HTML
     */
    private function renderPositionCell(array $player, string $posKey, int $depthCount, int $slotMin, int $slotMax): void
    {
        $fieldName = $posKey . $depthCount;
        $dcField = 'dc_' . strtoupper($posKey) . 'Depth';
        $currentValue = $player[$dcField];
        
        $canPlay = $this->processor->canPlayAtPosition(
            $player['pos'],
            $slotMin,
            $slotMax,
            $player['injured']
        );
        
        if ($canPlay) {
            echo "<td><select name=\"$fieldName\">";
            $this->renderPositionOptions($currentValue);
            echo "</select></td>";
        } else {
            echo "<td><input type=\"hidden\" name=\"$fieldName\" value=\"0\">no</td>";
        }
    }
    
    /**
     * Renders the form footer with submit button
     * 
     * @return void Echoes HTML
     */
    public function renderFormFooter(): void
    {
        echo "<tr>
            <th colspan=14><input type=\"radio\" checked> Submit Depth Chart? <input type=\"submit\" value=\"Submit\"></th>
        </tr></form></table></center>";
    }
    
    /**
     * Renders the submission result page
     * 
     * @param string $teamName Team name
     * @param array $playerData Player data
     * @param bool $success Whether submission was successful
     * @param string $errorHtml Error messages HTML (if any)
     * @return void Echoes HTML
     */
    public function renderSubmissionResult(string $teamName, array $playerData, bool $success, string $errorHtml = ''): void
    {
        if (!$success) {
            echo "<center><u>Your lineup has <b>not</b> been submitted:</u></center><br>";
            echo $errorHtml;
        } else {
            echo "<center><u>Your depth chart has been submitted and e-mailed successfully. Thank you.</u></center><p>";
        }
        
        // Display submitted data
        echo "$teamName Depth Chart Submission<br><table>";
        echo "<tr>
            <td><b>Name</td>
            <td><b>PG</td>
            <td><b>SG</td>
            <td><b>SF</td>
            <td><b>PF</td>
            <td><b>C</td>
            <td><b>Active</td>
            <td><b>Min</td>
            <td><b>OF</td>
            <td><b>DF</td>
            <td><b>OI</td>
            <td><b>DI</td>
            <td><b>BH</td>
        </tr>";
        
        foreach ($playerData as $player) {
            echo "<tr>
                <td>{$player['name']}</td>
                <td>{$player['pg']}</td>
                <td>{$player['sg']}</td>
                <td>{$player['sf']}</td>
                <td>{$player['pf']}</td>
                <td>{$player['c']}</td>
                <td>{$player['active']}</td>
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
