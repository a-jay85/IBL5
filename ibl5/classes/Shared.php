<?php

class Shared
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getDiscordIDFromTeamname($teamname)
    {
        $queryDiscordIDFromTeamname = $this->db->sql_query("SELECT discordID
            FROM ibl_team_info
            WHERE team_name = '$teamname'
            LIMIT 1;");

        return $this->db->sql_result($queryDiscordIDFromTeamname, 0, 'discordID');
    }

    public function getDiscordIDFromUsername($username)
    {
        $queryDiscordIDFromUsername = $this->db->sql_query("SELECT discordID
            FROM ibl_team_info
            INNER JOIN nuke_users ON ibl_team_info.team_name = nuke_users.user_ibl_team
            WHERE username = '$username'
            LIMIT 1;");

        return $this->db->sql_result($queryDiscordIDFromUsername, 0, 'discordID');
    }

    public function getNumberOfTitles($teamname, $titleName)
    {
        $queryNumberOfTitles = $this->db->sql_query("SELECT COUNT(name)
        	FROM ibl_team_awards
        	WHERE name = '$teamname'
        	  AND Award LIKE '%$titleName%';");

        return $this->db->sql_result($queryNumberOfTitles, 0, 'COUNT(name)');
    }

    public function getCurrentOwnerOfDraftPick($draftYear, $draftRound, $teamNameOfDraftPickOrigin)
    {
        $queryCurrentOwnerOfDraftPick = $this->db->sql_query("SELECT ownerofpick
            FROM ibl_draft_picks
            WHERE year = '$draftYear'
              AND round = '$draftRound'
              AND teampick = '$teamNameOfDraftPickOrigin'
            LIMIT 1;");

        return $this->db->sql_result($queryCurrentOwnerOfDraftPick, 0, 'ownerofpick');
    }
    
    public function getPlayerIDFromPlayerName($playerName)
    {
        $queryPlayerIDFromPlayerName = $this->db->sql_query("SELECT pid
            FROM ibl_plr
            WHERE name = '$playerName'
            LIMIT 1;");

        return $this->db->sql_result($queryPlayerIDFromPlayerName, 0, 'pid');
    }

    public function getTeamnameFromTid($tid)
    {
        $queryTeamnameFromTid = $this->db->sql_query("SELECT team_name
            FROM ibl_team_info
            WHERE teamid = $tid
            LIMIT 1;");

        return $this->db->sql_result($queryTeamnameFromTid, 0, 'team_name');
    }

    public function getTeamnameFromUsername($username)
    {
        if ($username) {
            $queryTeamnameFromUsername = $this->db->sql_query("SELECT user_ibl_team
                FROM nuke_users
                WHERE username = '$username'
                LIMIT 1;");

            return $this->db->sql_result($queryTeamnameFromUsername, 0, 'user_ibl_team');
        } else {
            return "Free Agents";
        }
    }

    public function getTidFromTeamname($teamname)
    {
        $queryTidFromTeamname = $this->db->sql_query("SELECT teamid
            FROM ibl_team_info
            WHERE team_name = '$teamname'
            LIMIT 1;");

        return $this->db->sql_result($queryTidFromTeamname, 0, 'teamid');
    }

    public function isFreeAgencyModuleActive()
    {
        $queryIsFreeAgencyModuleActive = $this->db->sql_query("SELECT title, active
            FROM nuke_modules
            WHERE title = 'Free_Agency'
            LIMIT 1");

        return $this->db->sql_result($queryIsFreeAgencyModuleActive, 0, "active");
    }
}
