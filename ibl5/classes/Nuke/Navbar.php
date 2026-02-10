<?php

declare(strict_types=1);

namespace Nuke;

class Navbar
{
    public static function menuimg(string $gfile): string
    {
        $ThemeSel = get_theme();
        if (file_exists("themes/$ThemeSel/images/menu/$gfile")) {
            $menuimg = "themes/$ThemeSel/images/menu/$gfile";
        } else {
            $menuimg = "modules/YourAccount/images/$gfile";
        }
        return $menuimg;
    }

    public static function nav(int $main_up = 0): void
    {
        /** @var int $articlecomm */
        global $articlecomm;
        /** @phpstan-var \MySQL $db */ // @phpstan-ignore varTag.deprecatedClass
        global $db;
        /** @var string $prefix */
        global $prefix;
        get_lang("YourAccount");
        $queryResult = $db->sql_query("SELECT overwrite_theme from " . $prefix . "_config"); // @phpstan-ignore method.deprecatedClass
        $row = $queryResult instanceof \mysqli_result ? $db->sql_fetchrow($queryResult) : null; // @phpstan-ignore method.deprecatedClass
        /** @var array{overwrite_theme: int|string}|false|null $row */
        $overwrite_theme = is_array($row) ? intval($row['overwrite_theme']) : 0;
        $thmcount = 0;
        $handle = opendir('themes');
        if ($handle !== false) {
            while (($file = readdir($handle)) !== false) {
                if ((!str_contains($file, '.'))) {
                    $thmcount++;
                }
            }
            closedir($handle);
        }
        echo "<table border=\"0\" width=\"100%\" align=\"center\"><tr><td width=\"10%\">";

        $menuimg = Navbar::menuimg("info.gif");
        echo "<font class=\"content\">"
            . "<center><a href=\"modules.php?name=YourAccount&amp;op=edituser\"><img src=\"$menuimg\" border=\"0\" alt=\"" . _CHANGEYOURINFO . "\" title=\"" . _CHANGEYOURINFO . "\"></a><br>"
            . "<a href=\"modules.php?name=YourAccount&amp;op=edituser\">" . _CHANGEYOURINFO . "</a>"
            . "</center></font></td>";

        $menuimg = Navbar::menuimg("home.gif");
        echo "<td width=\"10%\"><font class=\"content\">"
            . "<center><a href=\"modules.php?name=YourAccount&amp;op=edithome\"><img src=\"$menuimg\" border=\"0\" alt=\"" . _CHANGEHOME . "\" title=\"" . _CHANGEHOME . "\"></a><br>"
            . "<a href=\"modules.php?name=YourAccount&amp;op=edithome\">" . _CHANGEHOME . "</a>"
            . "</center></form></font></td>";

        if ($articlecomm === 1) {
            $menuimg = Navbar::menuimg("comments.gif");
            echo "<td width=\"10%\"><font class=\"content\">"
                . "<center><a href=\"modules.php?name=YourAccount&amp;op=editcomm\"><img src=\"$menuimg\" border=\"0\" alt=\"" . _CONFIGCOMMENTS . "\" title=\"" . _CONFIGCOMMENTS . "\"></a><br>"
                . "<a href=\"modules.php?name=YourAccount&amp;op=editcomm\">" . _CONFIGCOMMENTS . "</a>"
                . "</center></form></font></td>";
        }


        if ($thmcount > 1 and $overwrite_theme === 1) {
            $menuimg = Navbar::menuimg("themes.gif");
            echo "<td width=\"10%\"><font class=\"content\">"
                . "<center><a href=\"modules.php?name=YourAccount&amp;op=chgtheme\"><img src=\"$menuimg\" border=\"0\" alt=\"" . _SELECTTHETHEME . "\" title=\"" . _SELECTTHETHEME . "\"></a><br>"
                . "<a href=\"modules.php?name=YourAccount&amp;op=chgtheme\">" . _SELECTTHETHEME . "</a>"
                . "</center></form></font></td>";
        }

        $menuimg = Navbar::menuimg("exit.gif");
        echo "<td width=\"10%\"><font class=\"content\">"
            . "<center><a href=\"modules.php?name=YourAccount&amp;op=logout\"><img src=\"$menuimg\" border=\"0\" alt=\"" . _LOGOUTEXIT . "\" title=\"" . _LOGOUTEXIT . "\"></a><br>"
            . "<a href=\"modules.php?name=YourAccount&amp;op=logout\">" . _LOGOUTEXIT . "</a>"
            . "</center></form></font>";

        echo "</td></tr></table>";
        if ($main_up !== 1) {
            echo "<br><center>[ <a href=\"modules.php?name=YourAccount\">" . _RETURNACCOUNT . "</a> ]</center>\n";
        }
    }
}
