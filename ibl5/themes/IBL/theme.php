<?php

$lnkcolor = "#336699";
if ($_SERVER['SERVER_NAME'] != "localhost") {
    $bgcolor1 = "#EEEEEE";
} else {
    $bgcolor1 = "#BBBBBB";
}
$bgcolor2 = "#CCCCCC";
$bgcolor3 = "#AAAAAA";
$textcolor1 = "#000000";
$textcolor2 = "#000000";
$theme_home = "Web_Links";
$hr = 1; # 1 to have horizonal rule in comments instead of table bgcolor

function OpenTable()
{
    global $bgcolor1, $bgcolor2;
    echo "<table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"0\" bgcolor=\"$bgcolor2\"><tr><td>\n";
    echo "<table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"8\" bgcolor=\"$bgcolor1\"><tr><td>\n";
}

function OpenTable2()
{
    global $bgcolor1, $bgcolor2;
    echo "<table border=\"0\" cellspacing=\"1\" cellpadding=\"0\" bgcolor=\"$bgcolor2\" align=\"center\"><tr><td>\n";
    echo "<table border=\"0\" cellspacing=\"1\" cellpadding=\"8\" bgcolor=\"$bgcolor1\"><tr><td>\n";
}

function CloseTable()
{
    echo "</td></tr></table></td></tr></table>\n";
}

function CloseTable2()
{
    echo "</td></tr></table></td></tr></table>\n";
}

function FormatStory($thetext, $notes, $aid, $informant)
{
    global $anonymous;
    if (!empty($notes)) {
        $notes = "<b>" . _NOTE . "</b> <i>$notes</i>\n";
    } else {
        $notes = "";
    }
    if ("$aid" == "$informant") {
        echo "<font size=\"2\">$thetext<br>$notes</font>\n";
    } else {
        if (!empty($informant)) {
            $boxstuff = "<a href=\"modules.php?name=Your_Account&amp;op=userinfo&amp;username=$informant\">$informant</a> ";
        } else {
            $boxstuff = "$anonymous ";
        }
        $boxstuff .= "" . _WRITES . " <i>\"$thetext\"</i> $notes\n";
        echo "<font size=\"2\">$boxstuff</font>\n";
    }
}

function themeheader()
{
    global $user, $cookie, $bgcolor1, $bgcolor2, $user, $leagueContext, $mysqli_db;

    // Determine login state
    $isLoggedIn = is_user($user);
    $username = null;
    $teamId = null;

    if ($isLoggedIn) {
        cookiedecode($user);
        $username = $cookie[1];

        // Fetch user's team name and then lookup team ID
        if ($mysqli_db && $username) {
            // First get the team name from nuke_users
            $stmt = $mysqli_db->prepare("SELECT user_ibl_team FROM nuke_users WHERE username = ?");
            if ($stmt) {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $teamName = trim($row['user_ibl_team']);

                    // If team name exists, lookup the team ID from ibl_team_info
                    if ($teamName !== '' && $teamName !== '0') {
                        $stmt2 = $mysqli_db->prepare("SELECT teamid FROM ibl_team_info WHERE team_name = ?");
                        if ($stmt2) {
                            $stmt2->bind_param('s', $teamName);
                            $stmt2->execute();
                            $result2 = $stmt2->get_result();
                            if ($row2 = $result2->fetch_assoc()) {
                                $teamId = (int)$row2['teamid'];
                            }
                            $stmt2->close();
                        }
                    }
                }
                $stmt->close();
            }
        }
    }

    // Get current league for switcher
    $currentLeague = $leagueContext->getCurrentLeague();

    // Render the floating navigation bar
    $navView = new \Navigation\NavigationView($isLoggedIn, $username, $currentLeague, $teamId);
    echo $navView->render();

    // Body tag and main content wrapper
    echo "<body bgcolor=\"$bgcolor1\">";
    echo "<table border=\"0\" cellpadding=\"4\" cellspacing=\"0\" width=\"100%\" align=\"center\">\n"
        . "<tr><td valign=\"top\" width=\"100%\" colspan=\"3\">\n";
}

function themefooter()
{
    global $bgcolor1;
    // // if (defined('INDEX_FILE')) {
    // echo "</td><td>&nbsp;&nbsp;</td><td valign=\"top\" bgcolor=$bgcolor1>";
    // blocks("right");
    // echo "</td>";
    // // }
    echo "</td></tr></table></td></tr></table>";
    echo "<center>";
    Nuke\Footer::footmsg();
    echo "</center>";
}

function themeindex($aid, $informant, $time, $title, $counter, $topic, $thetext, $notes, $morelink, $topicname, $topicimage, $topictext)
{
    global $tipath, $bgcolor1, $bgcolor2, $bgcolor3;
    $ThemeSel = get_theme();
    if (file_exists("themes/$ThemeSel/images/topics/$topicimage")) {
        $t_image = "themes/$ThemeSel/images/topics/$topicimage";
    } else {
        $t_image = "$tipath$topicimage";
    }
    echo "<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" align=\"center\" bgcolor=$bgcolor3 width=\"100%\"><tr><td>"
        . "<table border=\"0\" cellpadding=\"3\" cellspacing=\"1\" width=\"100%\"><tr><td bgcolor=$bgcolor2>"
        . "<font class=\"title\">$title</font><br>"
        . "<font size=\"1\">"
        . "$time " . _BY . " "
        . "<b>";
    formatAidHeader($aid);
    echo "</b> ($counter " . _READS . ")</font></td></tr>"
        . "<tr><td bgcolor=$bgcolor1><a href=\"modules.php?name=News&amp;new_topic=$topic\"><img src=\"$t_image\" align=\"right\" border=\"0\" alt=\"$topictext\" title=\"$topictext\"></a>";
    FormatStory($thetext, $notes, $aid, $informant);
    echo "<br>"
        . "</td></tr><tr><td bgcolor=$bgcolor1 align=\"right\">"
        . "<font size=\"2\">$morelink</font>"
        . "</td></tr></table></td></tr></table>"
        . "<br>";
}

function themearticle($aid, $informant, $datetime, $title, $thetext, $topic, $topicname, $topicimage, $topictext)
{
    global $tipath, $bgcolor1, $bgcolor2, $bgcolor3;
    $ThemeSel = get_theme();
    if (file_exists("themes/$ThemeSel/images/topics/$topicimage")) {
        $t_image = "themes/$ThemeSel/images/topics/$topicimage";
    } else {
        $t_image = "$tipath$topicimage";
    }
    if ("$aid" == "$informant") {
        echo "
        	<table border=0 cellpadding=0 cellspacing=0 align=center bgcolor=$bgcolor3 width=100%><tr><td>
        	<table border=0 cellpadding=3 cellspacing=1 width=100%><tr><td bgcolor=$bgcolor2>
        	<font class=\"title\">$title</font><br>" . _POSTEDON . " $datetime
        	<br>" . _TOPIC . ": <a href=modules.php?name=News&amp;new_topic=$topic>$topictext</a>
        	</td></tr><tr><td bgcolor=$bgcolor1>
        	<a href=\"modules.php?name=News&amp;new_topic=$topic\"><img src=\"$t_image\" border=\"0\" alt=\"$topictext\" title=\"$topictext\" align=\"right\"></a>$thetext
        	</td></tr></table></td></tr></table><br>";
    } else {
        if (!empty($informant)) {
            $informant = "<a href=\"modules.php?name=Your_Account&amp;op=userinfo&username=$informant\">$informant</a> ";
        } else {
            $boxstuff = "$anonymous ";
        }
        $boxstuff .= "" . _WRITES . " <i>\"$thetext\"</i> $notes";
        echo "
        	<table border=0 cellpadding=0 cellspacing=0 align=center bgcolor=$bgcolor3 width=100%><tr><td>
        	<table border=0 cellpadding=3 cellspacing=1 width=100%><tr><td bgcolor=$bgcolor2>
        	<font class=\"title\">$title</b></font><p>" . _CONTRIBUTEDBY . " $informant " . _ON . " $datetime</font>
        	</td></tr><tr><td bgcolor=$bgcolor1>
        	<a href=\"modules.php?name=News&amp;new_topic=$topic\"><img src=\"$t_image\" border=\"0\" alt=\"$topictext\" title=\"$topictext\" align=\"right\"></a>$thetext
        	</td></tr></table></td></tr></table><br>";
    }
}

function themesidebox($title, $content)
{
    global $bgcolor1, $bgcolor2, $bgcolor3;
    echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" width=\"150\" bgcolor=\"$bgcolor3\">\n"
        . "<tr><td>\n"
        . "<table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"3\">\n"
        . "<tr><td bgcolor=$bgcolor2>"
        . "<font class=\"boxtitle\">$title</font></td></tr><tr><td bgcolor=\"$bgcolor1\"><font size=\"2\">"
        . "$content"
        . "</font></td></tr></table></td></tr></table><br>";
}
