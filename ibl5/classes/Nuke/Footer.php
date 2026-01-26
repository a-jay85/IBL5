<?php

namespace Nuke;

class Footer
{
    public static function footmsg()
    {
        global $foot1, $foot2, $foot3, $start_time;
        $mtime = microtime();
        $mtime = explode(" ", $mtime);
        $mtime = $mtime[1] + $mtime[0];
        $end_time = $mtime;
        $total_time = ($end_time - $start_time);
        $total_time = _PAGEGENERATION . " " . substr($total_time, 0, 4) . " " . _SECONDS;

        $footmsg = "<div class=\"site-footer\">\n";
        if (!empty($foot1)) {
            $footmsg .= "<p>" . $foot1 . "</p>\n";
        }
        if (!empty($foot2)) {
            $footmsg .= "<p>" . $foot2 . "</p>\n";
        }
        if (!empty($foot3)) {
            $footmsg .= "<p>" . $foot3 . "</p>\n";
        }
        $footmsg .= "<p class=\"page-time\">" . $total_time . "</p>\n";
        $footmsg .= "</div>\n";
        echo $footmsg;
    }

    public static function foot()
    {
        global $name, $admin;
        if (defined('HOME_FILE')) {
            blocks("Down");
        }
        if (basename($_SERVER['PHP_SELF']) != "index.php" and defined('MODULE_FILE') and (file_exists("modules/$name/admin/panel.php") && is_admin($admin))) {
            echo "<br>";
            OpenTable();
            include "modules/$name/admin/panel.php";
            CloseTable();
        }
        themefooter();
        if (file_exists("includes/custom_files/custom_footer.php")) {
            include_secure("includes/custom_files/custom_footer.php");
        }
        echo "</body>\n</html>";
        ob_end_flush();
        die();
    }

    public static function footer()
    {
        define('NUKE_FOOTER', true);
        Footer::foot();
    }
}