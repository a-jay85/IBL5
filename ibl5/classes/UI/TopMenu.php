<?php

namespace UI;

/**
 * TopMenu - Displays the team navigation menu at the top of pages
 */
class TopMenu
{
    /**
     * Display the top menu with team navigation
     *
     * @param \mysqli $db Mysqli database connection
     * @param int $teamID Current team ID (defaults to Free Agents)
     * @return void
     */
    public static function display(\mysqli $db, int $teamID = \League::FREE_AGENTS_TEAMID): void
    {
        $team = \Team::initialize($db, $teamID);

        // Fetch team data once and sort in PHP for each dropdown
        $teamQuery = "SELECT `team_city`,`team_name`,`teamid` FROM `ibl_team_info`";
        $teamResult = $db->query($teamQuery);
        
        $teams = [];
        while ($row = $teamResult->fetch_assoc()) {
            $teams[] = $row;
        }
        
        // Sort by city for location dropdown
        $teamsByCity = $teams;
        usort($teamsByCity, fn($a, $b) => strcasecmp($a['team_city'], $b['team_city']));
        
        // Sort by name for namesake dropdown
        $teamsByName = $teams;
        usort($teamsByName, fn($a, $b) => strcasecmp($a['team_name'], $b['team_name']));
        
        // Sort by ID for ID dropdown
        $teamsById = $teams;
        usort($teamsById, fn($a, $b) => $a['teamid'] <=> $b['teamid']);

        // Button styles
        $buttonStyle = "font: bold 11px Helvetica; text-decoration: none; " .
            "background-color: #" . htmlspecialchars($team->color2) . "; " .
            "color: #" . htmlspecialchars($team->color1) . "; " .
            "padding: 2px 6px; border: 1px solid #000000;";

        ob_start();
        ?>
<div style="text-align: center; margin: 0 auto; margin-bottom: 1rem;">
    <div style="margin-bottom: 1rem;">
        <p style="margin: 0.5rem 0;">
            <b>Team Pages:</b>
            <select name="teamSelectCity" onchange="location = this.options[this.selectedIndex].value;">
                <option value="">Location</option>
                <?php foreach ($teamsByCity as $row): ?>
                <option value="./modules.php?name=Team&amp;op=team&amp;teamID=<?= (int)$row["teamid"] ?>"><?= htmlspecialchars($row["team_city"]) ?> <?= htmlspecialchars($row["team_name"]) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="teamSelectName" onchange="location = this.options[this.selectedIndex].value;">
                <option value="">Namesake</option>
                <?php foreach ($teamsByName as $row): ?>
                <option value="./modules.php?name=Team&amp;op=team&amp;teamID=<?= (int)$row["teamid"] ?>"><?= htmlspecialchars($row["team_name"]) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="teamSelectID" onchange="location = this.options[this.selectedIndex].value;">
                <option value="">ID#</option>
                <?php foreach ($teamsById as $row): ?>
                <option value="./modules.php?name=Team&amp;op=team&amp;teamID=<?= (int)$row["teamid"] ?>"><?= (int)$row["teamid"] ?> <?= htmlspecialchars($row["team_city"]) ?> <?= htmlspecialchars($row["team_name"]) ?></option>
                <?php endforeach; ?>
            </select>
        </p>
    </div>
    <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 0.25rem;">
        <span style="white-space: nowrap;"><a style="<?= $buttonStyle ?>" href="modules.php?name=Team&amp;op=team&amp;teamID=<?= $teamID ?>">Team Page</a></span>
        <span style="white-space: nowrap;"><a style="<?= $buttonStyle ?>" href="modules.php?name=Team_Schedule&amp;teamID=<?= $teamID ?>">Team Schedule</a></span>
        <span style="white-space: nowrap;"><a style="<?= $buttonStyle ?>" href="modules/Team/draftHistory.php?teamID=<?= $teamID ?>">Draft History</a></span>
        <span style="white-space: nowrap; display: flex; align-items: center;"><span style="font: bold 14px Helvetica;"> | </span></span>
        <span style="white-space: nowrap;"><a style="<?= $buttonStyle ?>" href="modules.php?name=Depth_Chart_Entry">Depth Chart Entry</a></span>
        <span style="white-space: nowrap;"><a style="<?= $buttonStyle ?>" href="modules.php?name=Trading&amp;op=reviewtrade">Trades/Waivers</a></span>
    </div>
</div>
<hr>
        <?php
        echo ob_get_clean();
    }
}
