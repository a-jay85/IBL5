<?php

namespace DepthChart;

use Services\DatabaseService;

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
     * Renders the team logo image for the given team ID.
     *
     * @param int $teamID The team ID used to locate the logo image.
     * @return void Echoes the HTML for the team logo image.
     */
    public function renderTeamLogo(int $teamID): void
    {
        ob_start();
        ?>
<div style="text-align: center;"><img src="images/logo/<?= $teamID ?>.jpg"></div><br>
        <?php
        echo ob_get_clean();
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
        
        ob_start();
        foreach ($options as $value => $label) {
            $selected = ($selectedValue == $value) ? ' selected' : '';
            ?>
<option value="<?= $value ?>"<?= $selected ?>><?= htmlspecialchars($label) ?></option>
            <?php
        }
        echo ob_get_clean();
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
        
        ob_start();
        foreach ($options as $value => $label) {
            $selected = ($selectedValue == $value) ? ' selected' : '';
            ?>
<option value="<?= $value ?>"<?= $selected ?>><?= htmlspecialchars($label) ?></option>
            <?php
        }
        echo ob_get_clean();
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
        
        ob_start();
        foreach ($options as $value) {
            $selected = ($selectedValue == $value) ? ' selected' : '';
            $label = ($value == 0) ? '-' : $value;
            ?>
<option value="<?= $value ?>"<?= $selected ?>><?= htmlspecialchars((string)$label) ?></option>
            <?php
        }
        echo ob_get_clean();
    }
    
    /**
     * Renders active/inactive options
     * 
     * @param int $selectedValue Currently selected value (1 or 0)
     * @return void Echoes HTML
     */
    public function renderActiveOptions(int $selectedValue): void
    {
        ob_start();
        ?>
        <?php if ($selectedValue == 1): ?>
<option value="1" selected>Yes</option><option value="0">No</option>
        <?php else: ?>
<option value="1">Yes</option><option value="0" selected>No</option>
        <?php endif; ?>
        <?php
        echo ob_get_clean();
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
        $html = '<option value="0"' . ($selectedValue == 0 ? ' selected' : '') . '>Auto</option>';
        for ($i = 1; $i <= $staminaCap; $i++) {
            $selected = ($selectedValue == $i) ? ' selected' : '';
            $html .= '<option value="' . $i . '"' . $selected . '>' . $i . '</option>';
        }
        echo $html;
    }
    
    /**
     * Renders the depth chart form header
     * 
     * @param string $teamLogo Team name
     * @param int $teamID Team ID
     * @param array $slotNames Names of the 5 position slots
     * @return void Echoes HTML
     */
    public function renderFormHeader(string $teamLogo, int $teamID, array $slotNames): void
    {
        ob_start();
        ?>
<div style="display: flex; justify-content: center; margin: 10px 0;">
<form name="Depth_Chart" method="post" action="modules.php?name=Depth_Chart_Entry&op=submit">
    <input type="hidden" name="Team_Name" value="<?= htmlspecialchars($teamLogo) ?>">
<table>
    <tr>
        <th colspan="14"><div style="text-align: center;">DEPTH CHART ENTRY</div></th>
    </tr>
    <tr>
        <th>Pos</th>
        <th>Player</th>
        <th><?= htmlspecialchars($slotNames[0]) ?></th>
        <th><?= htmlspecialchars($slotNames[1]) ?></th>
        <th><?= htmlspecialchars($slotNames[2]) ?></th>
        <th><?= htmlspecialchars($slotNames[3]) ?></th>
        <th><?= htmlspecialchars($slotNames[4]) ?></th>
        <th>active</th>
        <th>min</th>
        <th>OF</th>
        <th>DF</th>
        <th>OI</th>
        <th>DI</th>
        <th>BH</th>
    </tr>
        <?php
        echo ob_get_clean();
    }
    
    /**
     * Renders a player row in the depth chart form
     * 
     * @param array $player Player data from database
     * @param int $depthCount Row counter
     * @return void Echoes HTML
     */
    public function renderPlayerRow(array $player, int $depthCount): void
    {
        $player_pid = $player['pid'];
        $player_pos = $player['pos'];
        $player_name = $player['name'];
        $player_inj = $player['injured'];
        
        // Safely escape player name for HTML attribute and display
        $player_name_html = DatabaseService::safeHtmlOutput($player_name);
        
        $player_staminacap = $player['sta'] + 40;
        if ($player_staminacap > 40) {
            $player_staminacap = 40;
        }
        
        ob_start();
        ?>
<tr>
    <td><?= htmlspecialchars($player_pos) ?></td>
    <td style="white-space: nowrap;">
        <input type="hidden" name="Injury<?= $depthCount ?>" value="<?= htmlspecialchars((string)$player_inj) ?>">
        <input type="hidden" name="Name<?= $depthCount ?>" value="<?= $player_name_html ?>">
        <a href="./modules.php?name=Player&pa=showpage&pid=<?= $player_pid ?>"><?= $player_name_html ?></a>
    </td>
        <?php
        foreach (\JSB::PLAYER_POSITIONS as $posKey) {
            $this->renderPositionCell($player, $posKey, $depthCount);
        }
        ?>
    <td><select name="active<?= $depthCount ?>">
        <?php $this->renderActiveOptions($player['dc_active']); ?>
    </select></td>
    <td><select name="min<?= $depthCount ?>">
        <?php $this->renderMinutesOptions($player['dc_minutes'], $player_staminacap); ?>
    </select></td>
    <td><select name="OF<?= $depthCount ?>">
        <?php $this->renderOffDefOptions($player['dc_of']); ?>
    </select></td>
    <td><select name="DF<?= $depthCount ?>">
        <?php $this->renderOffDefOptions($player['dc_df']); ?>
    </select></td>
    <td><select name="OI<?= $depthCount ?>">
        <?php $this->renderSettingOptions($player['dc_oi']); ?>
    </select></td>
    <td><select name="DI<?= $depthCount ?>">
        <?php $this->renderSettingOptions($player['dc_di']); ?>
    </select></td>
    <td><select name="BH<?= $depthCount ?>">
        <?php $this->renderSettingOptions($player['dc_bh']); ?>
    </select></td>
</tr>
        <?php
        echo ob_get_clean();
    }
    
    /**
     * Renders a position cell with dropdown
     * 
     * @param array $player Player data
     * @param string $posKey Position key (pg, sg, sf, pf, c)
     * @param int $depthCount Row counter
     * @return void Echoes HTML
     */
    private function renderPositionCell(array $player, string $posKey, int $depthCount): void
    {
        $fieldName = $posKey . $depthCount;
        $dcField = 'dc_' . strtoupper($posKey) . 'Depth';
        $currentValue = $player[$dcField];
        
        ob_start();
        ?>
<td><select name="<?= htmlspecialchars($fieldName) ?>">
    <?php $this->renderPositionOptions($currentValue); ?>
</select></td>
        <?php
        echo ob_get_clean();
    }
    
    /**
     * Renders the form footer with submit button
     * 
     * @return void Echoes HTML
     */
    public function renderFormFooter(): void
    {
        ob_start();
        ?>
    <tr>
        <th colspan="14"><input type="radio" checked> Submit Depth Chart? <input type="submit" value="Submit"></th>
    </tr>
</table>
</form>
</div>
        <?php
        echo ob_get_clean();
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
        ob_start();
        
        ?>
        <?php if (!$success): ?>
<div style="text-align: center;"><u>Your lineup has <b>not</b> been submitted:</u></div><br>
<?= $errorHtml ?>
        <?php else: ?>
<div style="text-align: center;"><u>Your depth chart has been submitted and e-mailed successfully. Thank you.</u></div><p>
        <?php endif; ?>

<?= htmlspecialchars($teamName) ?> Depth Chart Submission<br><table>
<tr>
    <td><b>Name</td>
    <?php foreach (\JSB::PLAYER_POSITIONS as $position): ?>
    <td><b><?= htmlspecialchars($position) ?></td>
    <?php endforeach; ?>
    <td><b>Active</td>
    <td><b>Min</td>
    <td><b>OF</td>
    <td><b>DF</td>
    <td><b>OI</td>
    <td><b>DI</td>
    <td><b>BH</td>
</tr>
        <?php foreach ($playerData as $player): ?>
<tr>
    <td><?= htmlspecialchars($player['name']) ?></td>
            <?php foreach (\JSB::PLAYER_POSITIONS as $position): ?>
                <?php $posKey = strtolower($position); ?>
    <td><?= htmlspecialchars((string)$player[$posKey]) ?></td>
            <?php endforeach; ?>
    <td><?= htmlspecialchars((string)$player['active']) ?></td>
    <td><?= htmlspecialchars((string)$player['min']) ?></td>
    <td><?= htmlspecialchars((string)$player['of']) ?></td>
    <td><?= htmlspecialchars((string)$player['df']) ?></td>
    <td><?= htmlspecialchars((string)$player['oi']) ?></td>
    <td><?= htmlspecialchars((string)$player['di']) ?></td>
    <td><?= htmlspecialchars((string)$player['bh']) ?></td>
</tr>
        <?php endforeach; ?>
</table>
        <?php
        echo ob_get_clean();
    }
}
