<?php

declare(strict_types=1);

namespace Nuke;

class Footer
{
    public static function renderPageGenerationTime(): void
    {
        /** @var float $start_time */
        global $start_time;
        $mtime = microtime();
        $mtimeParts = explode(" ", $mtime);
        $end_time = (float) $mtimeParts[1] + (float) $mtimeParts[0];
        $total_time = $end_time - $start_time;
        $pageGenLabel = defined('_PAGEGENERATION') ? \_PAGEGENERATION : 'Page Generation:';
        $secondsLabel = defined('_SECONDS') ? \_SECONDS : 'seconds';
        $total_time_str = $pageGenLabel . " " . substr((string) $total_time, 0, 4) . " " . $secondsLabel;

        echo "<!-- " . $total_time_str . " -->\n";
    }

    public static function foot(): never
    {
        /** @var string $name */
        global $name;
        /** @var string $admin */
        global $admin;
        if (defined('HOME_FILE')) {
            blocks("Down");
        }
        $phpSelfRaw = $_SERVER['PHP_SELF'] ?? '';
        $phpSelf = is_string($phpSelfRaw) ? $phpSelfRaw : '';
        if (basename($phpSelf) !== "index.php" and defined('MODULE_FILE') and (file_exists("modules/$name/admin/panel.php") && is_admin($admin))) {
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

    public static function footer(): never
    {
        define('NUKE_FOOTER', true);
        Footer::foot();
    }
}
