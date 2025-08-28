<?php

require_once __DIR__ . '/BaseView.php';

class OverviewView extends BaseView {
    public function render() {
        // NOTE ALL-STAR WEEKEND APPEARANCES

        echo "<tr>
            <td colspan=3>
                <table align=left cellspacing=1 cellpadding=0 border=1>
                    <th colspan=2><center>All-Star Activity</center></th>
        </tr>";

        $allstarquery = $this->db->sql_query("SELECT * FROM ibl_awards WHERE name='" . $this->player->name . "' AND Award LIKE '%Conference All-Star'");
        $asg = $this->db->sql_numrows($allstarquery);
        echo "<tr>
            <td><b>All Star Games:</b></td>
            <td>$asg</td>
        </tr>";

        $allstarquery2 = $this->db->sql_query("SELECT * FROM ibl_awards WHERE name='" . $this->player->name . "' AND Award LIKE 'Three-Point Contest%'");
        $threepointcontests = $this->db->sql_numrows($allstarquery2);

        echo "<tr>
            <td><b>Three-Point<br>Contests:</b></td>
            <td>$threepointcontests</td>
        </tr>";

        $allstarquery3 = $this->db->sql_query("SELECT * FROM ibl_awards WHERE name='" . $this->player->name . "' AND Award LIKE 'Slam Dunk Competition%'");
        $dunkcontests = $this->db->sql_numrows($allstarquery3);

        echo "<tr>
            <td><b>Slam Dunk<br>Competitions:</b></td>
            <td>$dunkcontests</td>
        </tr>";

        $allstarquery4 = $this->db->sql_query("SELECT * FROM ibl_awards WHERE name='" . $this->player->name . "' AND Award LIKE 'Rookie-Sophomore Challenge'");
        $rooksoph = $this->db->sql_numrows($allstarquery4);

        echo "<tr>
            <td><b>Rookie-Sophomore<br>Challenges:</b></td>
            <td>$rooksoph</td>
        </tr>
        </table>";

        // END ALL-STAR WEEKEND ACTIVITY SCRIPT

        echo "<center>
        <table>
            <tr align=center>
                <td><b>Talent</b></td>
                <td><b>Skill</b></td>
                <td><b>Intangibles</b></td>
                <td><b>Clutch</b></td>
                <td><b>Consistency</b></td>
            </tr>
            <tr align=center>
                <td>" . $this->player->ratingTalent . "</td>
                <td>" . $this->player->ratingSkill . "</td>
                <td>" . $this->player->ratingIntangibles . "</td>
                <td>" . $this->player->ratingClutch . "</td>
                <td>" . $this->player->ratingConsistency . "</td>
            </tr>
        </table>
        <table>
            <tr>
                <td><b>Loyalty</b></td>
                <td><b>Play for Winner</b></td>
                <td><b>Playing Time</b></td>
                <td><b>Security</b></td>
                <td><b>Tradition</b></td>
            </tr>
            <tr align=center>
                <td>" . $this->player->freeAgencyLoyalty . "</td>
                <td>" . $this->player->freeAgencyPlayForWinner . "</td>
                <td>" . $this->player->freeAgencyPlayingTime . "</td>
                <td>" . $this->player->freeAgencySecurity . "</td>
                <td>" . $this->player->freeAgencyTradition . "</td>
            </tr>
        </table>
        </center>
        </td></tr></table>";
    }
}