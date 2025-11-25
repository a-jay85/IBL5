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
     * @param object $db Database connection
     * @param int $teamID Current team ID (defaults to Free Agents)
     * @return void
     */
    public static function display($db, int $teamID = \League::FREE_AGENTS_TEAMID): void
    {
        $team = \Team::initialize($db, $teamID);

        $teamCityQuery = "SELECT `team_city`,`team_name`,`teamid` FROM `ibl_team_info` ORDER BY `team_city` ASC";
        $teamCityResult = $db->sql_query($teamCityQuery);
        $teamNameQuery = "SELECT `team_city`,`team_name`,`teamid` FROM `ibl_team_info` ORDER BY `team_name` ASC";
        $teamNameResult = $db->sql_query($teamNameQuery);
        $teamIDQuery = "SELECT `team_city`,`team_name`,`teamid` FROM `ibl_team_info` ORDER BY `teamid` ASC";
        $teamIDResult = $db->sql_query($teamIDQuery);

        // Button styles
        $buttonStyle = "font: bold 11px Helvetica; text-decoration: none; " .
            "background-color: #" . htmlspecialchars($team->color2) . "; " .
            "color: #" . htmlspecialchars($team->color1) . "; " .
            "padding: 2px 6px; border: 1px solid #000000;";

        ob_start();
        ?>
<div style="text-align: center;">
    <table style="width: 400px; margin: 0 auto; border: 0;">
        <tr>
            <td colspan="6">
                <p>
                    <b>Team Pages:</b>
                    <select name="teamSelectCity" onchange="location = this.options[this.selectedIndex].value;">
                        <option value="">Location</option>
                        <?php while ($row = $db->sql_fetch_assoc($teamCityResult)): ?>
                        <option value="./modules.php?name=Team&amp;op=team&amp;teamID=<?= (int)$row["teamid"] ?>"><?= htmlspecialchars($row["team_city"]) ?> <?= htmlspecialchars($row["team_name"]) ?></option>
                        <?php endwhile; ?>
                    </select>

                    <select name="teamSelectName" onchange="location = this.options[this.selectedIndex].value;">
                        <option value="">Namesake</option>
                        <?php while ($row = $db->sql_fetch_assoc($teamNameResult)): ?>
                        <option value="./modules.php?name=Team&amp;op=team&amp;teamID=<?= (int)$row["teamid"] ?>"><?= htmlspecialchars($row["team_name"]) ?></option>
                        <?php endwhile; ?>
                    </select>

                    <select name="teamSelectID" onchange="location = this.options[this.selectedIndex].value;">
                        <option value="">ID#</option>
                        <?php while ($row = $db->sql_fetch_assoc($teamIDResult)): ?>
                        <option value="./modules.php?name=Team&amp;op=team&amp;teamID=<?= (int)$row["teamid"] ?>"><?= (int)$row["teamid"] ?> <?= htmlspecialchars($row["team_city"]) ?> <?= htmlspecialchars($row["team_name"]) ?></option>
                        <?php endwhile; ?>
                    </select>
                </p>
            </td>
        </tr>
        <tr>
            <td style="white-space: nowrap;"><a style="<?= $buttonStyle ?>" href="modules.php?name=Team&amp;op=team&amp;teamID=<?= $teamID ?>">Team Page</a></td>
            <td style="white-space: nowrap;"><a style="<?= $buttonStyle ?>" href="modules.php?name=Team_Schedule&amp;teamID=<?= $teamID ?>">Team Schedule</a></td>
            <td style="white-space: nowrap;"><a style="<?= $buttonStyle ?>" href="modules/Team/draftHistory.php?teamID=<?= $teamID ?>">Draft History</a></td>
            <td style="white-space: nowrap; vertical-align: middle;"><span style="font: bold 14px Helvetica;"> | </span></td>
            <td style="white-space: nowrap;"><a style="<?= $buttonStyle ?>" href="modules.php?name=Depth_Chart_Entry">Depth Chart Entry</a></td>
            <td style="white-space: nowrap;"><a style="<?= $buttonStyle ?>" href="modules.php?name=Trading&amp;op=reviewtrade">Trades/Waivers</a></td>
        </tr>
    </table>
</div>
<hr>
        <?php
        echo ob_get_clean();
    }
}
