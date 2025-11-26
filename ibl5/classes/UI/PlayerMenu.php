<?php

namespace UI;

/**
 * PlayerMenu - Displays the player navigation menu
 */
class PlayerMenu
{
    /**
     * Display the player menu with navigation links
     *
     * @return void
     */
    public static function display(): void
    {
        ob_start();
        ?>
<div style="text-align: center;">
    <b>
        <a href="modules.php?name=Player_Search">Player Search</a> |
        <a href="modules.php?name=Player_Awards">Awards Search</a> |
        <a href="modules.php?name=One-on-One">One-on-One Game</a> |
        <a href="modules.php?name=Leaderboards">Career Leaderboards</a> (All Types)
    </b>
</div>
<hr>
        <?php
        echo ob_get_clean();
    }
}
