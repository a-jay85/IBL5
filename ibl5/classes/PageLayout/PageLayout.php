<?php

declare(strict_types=1);

namespace PageLayout;

class PageLayout
{
    public static function header(): void
    {
        online();
        self::renderHead();
        include "includes/counter.php";
        if (
            isset($_SESSION['flash_success'])
            && is_string($_SESSION['flash_success'])
            && $_SESSION['flash_success'] !== ''
        ) {
            /** @var string $flashMessage */
            $flashMessage = \Utilities\HtmlSanitizer::safeHtmlOutput($_SESSION['flash_success']);
            unset($_SESSION['flash_success']);
            echo '<div class="ibl-alert ibl-alert--success">' . $flashMessage . '</div>';
        }
        if (defined('HOME_FILE')) {
            message_box();
            blocks("Center");
        }
    }

    private static function renderHead(): void
    {
        /** @var string $sitename */
        global $sitename;
        /** @var string $pagetitle */
        global $pagetitle;
        /** @var string $slogan */
        global $slogan;
        /** @var string $name */
        global $name;
        $ThemeSel = get_theme();

        // Calculate the correct base path for resources
        $currentFileRaw = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $currentFile = is_string($currentFileRaw) ? $currentFileRaw : '';
        $docRootRaw = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $documentRoot = is_string($docRootRaw) ? $docRootRaw : '';

        // Calculate how many directory levels deep we are from the application root
        $iblRoot = $documentRoot . '/ibl5';
        $relativePath = '';

        // If we're in a subdirectory of ibl5, calculate the relative path back to root
        if (strpos($currentFile, $iblRoot) === 0) {
            $relativeFromIblRoot = substr(dirname($currentFile), strlen($iblRoot));
            if ($relativeFromIblRoot !== '') {
                // Count directory levels and build relative path
                $levels = substr_count(trim($relativeFromIblRoot, '/'), '/') + 1;
                $relativePath = str_repeat('../', $levels);
            }
        }

        echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
        echo '<html xmlns="http://www.w3.org/1999/xhtml">';
        echo "<head>\n";
        echo "<title>$sitename $pagetitle</title>\n";
        echo '<meta name="google-site-verification" content="3y3xJYDHSYUitn7cbfFfI6C2BiK_q66dtRfykpzHW5w" />';
        echo "<script src=\"{$relativePath}jslib/sorttable.js\"></script>";
        echo "<script src=\"{$relativePath}jslib/responsive-tables.js\"></script>";
        echo "<script src=\"{$relativePath}jslib/name-abbreviation.js\"></script>";
        echo "<script src=\"{$relativePath}jslib/user-team-highlighter.js\"></script>";

        // Meta tags (inlined from includes/meta.php)
        echo "<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=" . _CHARSET . "\">\n";
        echo "<meta id=\"viewport-meta\" name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        echo "<script>(function(){try{if(localStorage.getItem('ibl_desktop_view')==='1'){document.getElementById('viewport-meta').setAttribute('content','width=1440');document.documentElement.classList.add('desktop-view-active');}}catch(e){}})()</script>\n";
        echo "<META HTTP-EQUIV=\"EXPIRES\" CONTENT=\"0\">\n";
        echo "<META NAME=\"RESOURCE-TYPE\" CONTENT=\"DOCUMENT\">\n";
        echo "<META NAME=\"DISTRIBUTION\" CONTENT=\"GLOBAL\">\n";
        echo "<META NAME=\"AUTHOR\" CONTENT=\"$sitename\">\n";
        echo "<META NAME=\"COPYRIGHT\" CONTENT=\"Copyright (c) by $sitename\">\n";
        echo "<META NAME=\"KEYWORDS\" CONTENT=\"basketball, fantasy basketball, basketball league, IBL, Internet Basketball League, basketball simulation, basketball stats, NBA, basketball draft, free agency, basketball trading, basketball standings, basketball schedule\">\n";
        echo "<META NAME=\"DESCRIPTION\" CONTENT=\"$slogan\">\n";
        echo "<META NAME=\"ROBOTS\" CONTENT=\"INDEX, FOLLOW\">\n";
        echo "<META NAME=\"REVISIT-AFTER\" CONTENT=\"1 DAYS\">\n";
        echo "<META NAME=\"RATING\" CONTENT=\"GENERAL\">\n";

        // JavaScript functions (inlined from includes/javascript.php)
        $nameStr = is_string($name) ? $name : '';
        if (defined('MODULE_FILE') && file_exists("modules/" . $nameStr . "/copyright.php")) {
            echo "<script type=\"text/javascript\">\n";
            echo "<!--\n";
            echo "function openwindow(){\n";
            echo "\twindow.open (\"modules/" . $nameStr . "/copyright.php\",\"Copyright\",\"toolbar=no,location=no,directories=no,status=no,scrollbars=yes,resizable=no,copyhistory=no,width=400,height=200\");\n";
            echo "}\n";
            echo "//-->\n";
            echo "</SCRIPT>\n\n";
        }

        if (file_exists("themes/$ThemeSel/images/favicon.ico")) {
            echo "<link REL=\"shortcut icon\" HREF=\"{$relativePath}themes/$ThemeSel/images/favicon.ico\" TYPE=\"image/x-icon\">\n";
        }
        echo "<link rel=\"alternate\" type=\"application/rss+xml\" title=\"RSS\" href=\"{$relativePath}backend.php\">\n";

        // Google Fonts (inlined from includes/custom_files/custom_head.php)
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        echo '<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700;800&family=Barlow:wght@400;500;600;700&display=block" rel="stylesheet">';

        // Font loading styles
        echo '<style id="font-loading-styles">
.fonts-loading {
    visibility: hidden;
}
.fonts-loaded {
    visibility: visible;
}
</style>';

        // Font loading detection script
        echo '<script>
// Add fonts-loading class immediately
document.documentElement.classList.add("fonts-loading");

// Check if fonts are already cached
if (document.fonts && document.fonts.check("1em Barlow")) {
    document.documentElement.classList.remove("fonts-loading");
    document.documentElement.classList.add("fonts-loaded");
} else if (document.fonts) {
    // Wait for fonts to load
    Promise.all([
        document.fonts.load("400 1em Barlow"),
        document.fonts.load("600 1em Barlow Condensed")
    ]).then(function() {
        document.documentElement.classList.remove("fonts-loading");
        document.documentElement.classList.add("fonts-loaded");
    }).catch(function() {
        // Fallback: show content anyway if fonts fail
        document.documentElement.classList.remove("fonts-loading");
        document.documentElement.classList.add("fonts-loaded");
    });

    // Safety timeout - show content after 1 second max
    setTimeout(function() {
        document.documentElement.classList.remove("fonts-loading");
        document.documentElement.classList.add("fonts-loaded");
    }, 1000);
} else {
    // Fallback for browsers without Font Loading API
    document.documentElement.classList.remove("fonts-loading");
    document.documentElement.classList.add("fonts-loaded");
}
</script>';

        // Navigation JavaScript for mobile menu toggle
        echo '<script src="' . $relativePath . 'jslib/navigation.js" defer></script>';

        // FOUT prevention inline styles
        echo '<style>
/* FOUT Prevention - Hide body until fonts are loaded */
.fonts-loading body {
    visibility: hidden;
}
.fonts-loaded body {
    visibility: visible;
}
</style>';

        // CSS stylesheet — loaded once (fixes duplicate CSS loading bug)
        $cssPath = "themes/$ThemeSel/style/style.css";
        $cssVersion = file_exists($cssPath) ? filemtime($cssPath) : '';
        echo "<LINK REL=\"StyleSheet\" HREF=\"{$relativePath}{$cssPath}?v={$cssVersion}\" TYPE=\"text/css\">\n";

        echo "\n\n\n</head>\n\n";
        themeheader();
    }

    public static function footer(): never
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
        $nameStr = is_string($name) ? $name : '';
        if (basename($phpSelf) !== "index.php" and defined('MODULE_FILE') and (file_exists("modules/$nameStr/admin/panel.php") && is_admin($admin))) {
            echo "<br>";
            include "modules/$nameStr/admin/panel.php";
        }
        themefooter();
        echo "</body>\n</html>";
        ob_end_flush();
        die();
    }

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
}
