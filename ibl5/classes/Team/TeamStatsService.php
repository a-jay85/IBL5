<?php

namespace Team;

use Player\Player;

/**
 * TeamStatsService - Handles statistical calculations for teams
 * 
 * This service processes player statistics and generates team-level statistics
 * and last sim's starting lineup information.
 */
class TeamStatsService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get last sim's starting lineup for a team
     * Returns HTML table with starting 5 players
     */
    public function getLastSimsStarters($result, $team)
    {
        $num = $this->db->sql_numrows($result);
        $i = 0;
        
        // Initialize starters - will be null if position not found
        $startingPG = $startingPGpid = null;
        $startingSG = $startingSGpid = null;
        $startingSF = $startingSFpid = null;
        $startingPF = $startingPFpid = null;
        $startingC = $startingCpid = null;
        
        while ($i < $num) {
            if ($this->db->sql_result($result, $i, "PGDepth") == 1) {
                $startingPG = $this->db->sql_result($result, $i, "name");
                $startingPGpid = $this->db->sql_result($result, $i, "pid");
            }
            if ($this->db->sql_result($result, $i, "SGDepth") == 1) {
                $startingSG = $this->db->sql_result($result, $i, "name");
                $startingSGpid = $this->db->sql_result($result, $i, "pid");
            }
            if ($this->db->sql_result($result, $i, "SFDepth") == 1) {
                $startingSF = $this->db->sql_result($result, $i, "name");
                $startingSFpid = $this->db->sql_result($result, $i, "pid");
            }
            if ($this->db->sql_result($result, $i, "PFDepth") == 1) {
                $startingPF = $this->db->sql_result($result, $i, "name");
                $startingPFpid = $this->db->sql_result($result, $i, "pid");
            }
            if ($this->db->sql_result($result, $i, "CDepth") == 1) {
                $startingC = $this->db->sql_result($result, $i, "name");
                $startingCpid = $this->db->sql_result($result, $i, "pid");
            }
            $i++;
        }

        // Note: NULL values for starter names/pids will render empty in HTML but structure is preserved
        // This matches original behavior where missing starters would show empty cells
        $starters_table = "<table align=\"center\" border=1 cellpadding=1 cellspacing=1>
            <tr bgcolor=$team->color1>
                <td colspan=5><font color=$team->color2><center><b>Last Sim's Starters</b></center></font></td>
            </tr>
            <tr>
                <td><center><b>PG</b><br><img src=\"./images/player/$startingPGpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingPGpid\">$startingPG</a></td>
                <td><center><b>SG</b><br><img src=\"./images/player/$startingSGpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingSGpid\">$startingSG</a></td>
                <td><center><b>SF</b><br><img src=\"./images/player/$startingSFpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingSFpid\">$startingSF</a></td>
                <td><center><b>PF</b><br><img src=\"./images/player/$startingPFpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingPFpid\">$startingPF</a></td>
                <td><center><b>C</b><br><img src=\"./images/player/$startingCpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingCpid\">$startingC</a></td>
            </tr>
        </table>";

        return $starters_table;
    }
}
