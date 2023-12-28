<?php

class UI
{
    public static function decoratePlayerName($name, $tid, $ordinal, $currentContractYear, $totalYearsOnContract)
    {
        if ($tid == 0) {
            $playerNameDecorated = "$name";
        } elseif ($ordinal >= 960) { // on waivers
            $playerNameDecorated = "($name)*";
        } elseif ($currentContractYear == $totalYearsOnContract) { // eligible for Free Agency at the end of this season
            $playerNameDecorated = "$name^";
        } else {
            $playerNameDecorated = "$name";
        }
        return $playerNameDecorated;
    }

    public static function playerMenu()
    {
        echo "<center><b>
            <a href=\"modules.php?name=Player_Search\">Player Search</a>  |
            <a href=\"modules.php?name=Player_Awards\">Awards Search</a> |
            <a href=\"modules.php?name=One-on-One\">One-on-One Game</a> |
            <a href=\"modules.php?name=Leaderboards\">Career Leaderboards</a> (All Types)
        </b><center>
        <hr>";
    }
}