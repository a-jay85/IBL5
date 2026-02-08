<?php

declare(strict_types=1);

namespace Nuke;

class Footer
{
    public static function renderPageGenerationTime(): void
    {
        global $start_time;
        $mtime = microtime();
        $mtime = explode(" ", $mtime);
        $mtime = $mtime[1] + $mtime[0];
        $end_time = $mtime;
        $total_time = ($end_time - $start_time);
        $total_time = _PAGEGENERATION . " " . substr((string) $total_time, 0, 4) . " " . _SECONDS;

        echo "<!-- " . $total_time . " -->\n";
    }

    public static function foot()
    {
        global $name, $admin;
        if (defined('HOME_FILE')) {
            blocks("Down");
        }
        if (basename($_SERVER['PHP_SELF']) !== "index.php" and defined('MODULE_FILE') and (file_exists("modules/$name/admin/panel.php") && is_admin($admin))) {
            echo "<br>";
            include "modules/$name/admin/panel.php";
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