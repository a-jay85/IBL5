<?php

class NukeNavbar
{
    public static function menuimg($gfile)
    {
        $ThemeSel = get_theme();
        if (file_exists("themes/$ThemeSel/images/menu/$gfile")) {
            $menuimg = "themes/$ThemeSel/images/menu/$gfile";
        } else {
            $menuimg = "modules/Your_Account/images/$gfile";
        }
        return ($menuimg);
    }

    public static function nav($main_up = 0)
    {
        global $module_name, $articlecomm, $db, $prefix;
        get_lang("Your_Account");
        $row = $db->sql_fetchrow($db->sql_query("SELECT overwrite_theme from " . $prefix . "_config"));
        $overwrite_theme = intval($row['overwrite_theme']);
        $thmcount = 0;
        $handle = opendir('themes');
        while ($file = readdir($handle)) {
            if ((!mb_ereg("[.]", $file))) {
                $thmcount++;
            }
        }
        closedir($handle);
        echo "<table border=\"0\" width=\"100%\" align=\"center\"><tr><td width=\"10%\">";
    
        $menuimg = NukeNavbar::menuimg("info.gif");
        echo "<font class=\"content\">"
            . "<center><a href=\"modules.php?name=Your_Account&amp;op=edituser\"><img src=\"$menuimg\" border=\"0\" alt=\"" . _CHANGEYOURINFO . "\" title=\"" . _CHANGEYOURINFO . "\"></a><br>"
            . "<a href=\"modules.php?name=Your_Account&amp;op=edituser\">" . _CHANGEYOURINFO . "</a>"
            . "</center></font></td>";
    
        $menuimg = NukeNavbar::menuimg("home.gif");
        echo "<td width=\"10%\"><font class=\"content\">"
            . "<center><a href=\"modules.php?name=Your_Account&amp;op=edithome\"><img src=\"$menuimg\" border=\"0\" alt=\"" . _CHANGEHOME . "\" title=\"" . _CHANGEHOME . "\"></a><br>"
            . "<a href=\"modules.php?name=Your_Account&amp;op=edithome\">" . _CHANGEHOME . "</a>"
            . "</center></form></font></td>";
    
        if ($articlecomm == 1) {
            $menuimg = NukeNavbar::menuimg("comments.gif");
            echo "<td width=\"10%\"><font class=\"content\">"
                . "<center><a href=\"modules.php?name=Your_Account&amp;op=editcomm\"><img src=\"$menuimg\" border=\"0\" alt=\"" . _CONFIGCOMMENTS . "\" title=\"" . _CONFIGCOMMENTS . "\"></a><br>"
                . "<a href=\"modules.php?name=Your_Account&amp;op=editcomm\">" . _CONFIGCOMMENTS . "</a>"
                . "</center></form></font></td>";
        }
    
        if (is_active("Private_Messages")) {
            $menuimg = NukeNavbar::menuimg("messages.gif");
            echo "<td width=\"10%\"><font class=\"content\">"
                . "<center><a href=\"modules.php?name=Private_Messages\"><img src=\"$menuimg\" border=\"0\" alt=\"" . _PRIVATEMESSAGES . "\" title=\"" . _PRIVATEMESSAGES . "\"></a><br>"
                . "<a href=\"modules.php?name=Private_Messages\">" . _MESSAGES . "</a>"
                . "</center></form></font></td>";
        }
    
        if (is_active("Journal")) {
            $menuimg = NukeNavbar::menuimg("journal.gif");
            echo "<td width=\"10%\"><font class=\"content\">"
                . "<center><a href=\"modules.php?name=Journal&amp;file=edit\"><img src=\"$menuimg\" border=\"0\" alt=\"" . _JOURNAL . "\" title=\"" . _JOURNAL . "\"></a><br>"
                . "<a href=\"modules.php?name=Journal&amp;file=edit\">" . _JOURNAL . "</a>"
                . "</center></form></font></td>";
        }
    
        if ($thmcount > 1 and $overwrite_theme == 1) {
            $menuimg = NukeNavbar::menuimg("themes.gif");
            echo "<td width=\"10%\"><font class=\"content\">"
                . "<center><a href=\"modules.php?name=Your_Account&amp;op=chgtheme\"><img src=\"$menuimg\" border=\"0\" alt=\"" . _SELECTTHETHEME . "\" title=\"" . _SELECTTHETHEME . "\"></a><br>"
                . "<a href=\"modules.php?name=Your_Account&amp;op=chgtheme\">" . _SELECTTHETHEME . "</a>"
                . "</center></form></font></td>";
        }
    
        $menuimg = NukeNavbar::menuimg("exit.gif");
        echo "<td width=\"10%\"><font class=\"content\">"
            . "<center><a href=\"modules.php?name=Your_Account&amp;op=logout\"><img src=\"$menuimg\" border=\"0\" alt=\"" . _LOGOUTEXIT . "\" title=\"" . _LOGOUTEXIT . "\"></a><br>"
            . "<a href=\"modules.php?name=Your_Account&amp;op=logout\">" . _LOGOUTEXIT . "</a>"
            . "</center></form></font>";
    
        echo "</td></tr></table>";
        if ($main_up != 1) {
            echo "<br><center>[ <a href=\"modules.php?name=Your_Account\">" . _RETURNACCOUNT . "</a> ]</center>\n";
        }
    }
}
