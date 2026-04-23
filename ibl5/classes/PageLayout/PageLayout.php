<?php

declare(strict_types=1);

namespace PageLayout;

use Utilities\HtmxHelper;

class PageLayout
{
    public static function header(): void
    {
        // Populate $cookie/$user globals for all request types.
        /** @var string $user */
        global $user;
        cookiedecode($user);

        if (HtmxHelper::isBoostedRequest()) {
            self::renderBoostedHeader();
            return;
        }

        self::renderHead();
        if (isset($GLOBALS['mysqli_db']) && $GLOBALS['mysqli_db'] instanceof \mysqli) {
            (new \SiteStatistics\StatisticsRepository($GLOBALS['mysqli_db']))->recordHit();
        }
        if (
            isset($_SESSION['flash_success'])
            && is_string($_SESSION['flash_success'])
            && $_SESSION['flash_success'] !== ''
        ) {
            $flashMessage = \Utilities\HtmlSanitizer::safeHtmlOutput($_SESSION['flash_success']);
            unset($_SESSION['flash_success']);
            echo '<div class="ibl-alert ibl-alert--success">' . $flashMessage . '</div>';
        }
        if (defined('ADMIN_PHASE_GATE_NOTICE')) {
            echo '<div class="ibl-alert ibl-alert--warning">Admin mode: You can view this module, but it is currently closed to non-admin GMs.</div>';
        }
        if (defined('HOME_FILE')) {
            blocks("Center");
        }
    }

    private static function renderBoostedHeader(): void
    {
        /** @var string $sitename */
        global $sitename;
        /** @var string $pagetitle */
        global $pagetitle;

        echo '<title>' . \Utilities\HtmlSanitizer::e($sitename . ' ' . $pagetitle) . '</title>';

        if (
            isset($_SESSION['flash_success'])
            && is_string($_SESSION['flash_success'])
            && $_SESSION['flash_success'] !== ''
        ) {
            $flashMessage = \Utilities\HtmlSanitizer::safeHtmlOutput($_SESSION['flash_success']);
            unset($_SESSION['flash_success']);
            echo '<div class="ibl-alert ibl-alert--success">' . $flashMessage . '</div>';
        }
        if (defined('ADMIN_PHASE_GATE_NOTICE')) {
            echo '<div class="ibl-alert ibl-alert--warning">Admin mode: You can view this module, but it is currently closed to non-admin GMs.</div>';
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
        $ThemeSel = 'IBL';

        // Calculate the correct base path for resources
        $currentFileRaw = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $currentFile = is_string($currentFileRaw) ? $currentFileRaw : '';

        // Use AppPaths::root() for reliable path calculation across worktrees
        $iblRoot = \Bootstrap\AppPaths::root();
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

        echo '<!DOCTYPE html>';
        echo '<html lang="en">';
        echo "<head>\n";
        if ($relativePath !== '') {
            echo "<base href=\"{$relativePath}\">\n";
        }
        echo "<title>" . \Utilities\HtmlSanitizer::e($sitename . ' ' . $pagetitle) . "</title>\n";
        echo '<meta name="google-site-verification" content="3y3xJYDHSYUitn7cbfFfI6C2BiK_q66dtRfykpzHW5w" />';
        $jsFiles = [
            'jslib/htmx.min.js',
            'jslib/sorttable.js',
            'jslib/responsive-tables.js',
            'jslib/name-abbreviation.js',
            'jslib/user-team-highlighter.js',
            'jslib/sticky-page-header.js',
            'jslib/contract-hint.js',
            'jslib/offer-salary-hints.js',
            'jslib/htmx-init.js',
            'jslib/local-time.js',
        ];
        $iblRootPath = \Bootstrap\AppPaths::root();
        foreach ($jsFiles as $jsFile) {
            $fullPath = $iblRootPath . '/' . $jsFile;
            $mtime = is_file($fullPath) ? filemtime($fullPath) : false;
            $version = is_int($mtime) ? $mtime : 0;
            echo "<script src=\"{$jsFile}?v={$version}\"></script>";
        }

        // Meta tags (inlined from includes/meta.php)
        echo "<meta charset=\"utf-8\">\n";
        echo "<meta id=\"viewport-meta\" name=\"viewport\" content=\"width=device-width, initial-scale=1.0, viewport-fit=cover\">\n";
        echo "<script>(function(){try{if(localStorage.getItem('ibl_desktop_view')==='1'){document.getElementById('viewport-meta').setAttribute('content','width=1440');document.documentElement.classList.add('desktop-view-active');}}catch(e){}})()</script>\n";
        echo "<META HTTP-EQUIV=\"EXPIRES\" CONTENT=\"0\">\n";
        echo "<META NAME=\"RESOURCE-TYPE\" CONTENT=\"DOCUMENT\">\n";
        echo "<META NAME=\"DISTRIBUTION\" CONTENT=\"GLOBAL\">\n";
        echo "<META NAME=\"AUTHOR\" CONTENT=\"" . \Utilities\HtmlSanitizer::e($sitename) . "\">\n";
        echo "<META NAME=\"COPYRIGHT\" CONTENT=\"Copyright (c) by " . \Utilities\HtmlSanitizer::e($sitename) . "\">\n";
        echo "<META NAME=\"KEYWORDS\" CONTENT=\"basketball, fantasy basketball, basketball league, IBL, Internet Basketball League, basketball simulation, basketball stats, NBA, basketball draft, free agency, basketball trading, basketball standings, basketball schedule\">\n";
        echo "<META NAME=\"DESCRIPTION\" CONTENT=\"" . \Utilities\HtmlSanitizer::e($slogan) . "\">\n";
        echo "<META NAME=\"ROBOTS\" CONTENT=\"INDEX, FOLLOW\">\n";
        echo "<META NAME=\"REVISIT-AFTER\" CONTENT=\"1 DAYS\">\n";
        echo "<META NAME=\"RATING\" CONTENT=\"GENERAL\">\n";

        // Open Graph meta tags (for LinkedIn, Facebook, etc.)
        $ogHost = is_string($_SERVER['HTTP_HOST'] ?? null) ? $_SERVER['HTTP_HOST'] : 'iblhoops.net';
        $ogBaseUrl = "https://{$ogHost}/ibl5";
        $ogTitle = "IBL \xe2\x80\x94 Internet Basketball League";
        $ogDescription = 'The Internet Basketball League (IBL) is an online fantasy basketball league powered by the Jump Shot Basketball simulation engine.';
        if ($ogHost === 'pre.iblhoops.net') {
            $ogImage = "{$ogBaseUrl}/images/ibl/logocorner.jpg";
            $ogImageWidth = '150';
            $ogImageHeight = '150';
        } else {
            $ogImage = "{$ogBaseUrl}/images/og-basketball.png";
            $ogImageWidth = '1200';
            $ogImageHeight = '630';
        }
        echo "<meta property=\"og:title\" content=\"{$ogTitle}\">\n";
        echo "<meta property=\"og:description\" content=\"{$ogDescription}\">\n";
        echo "<meta property=\"og:type\" content=\"website\">\n";
        echo "<meta property=\"og:url\" content=\"{$ogBaseUrl}/index.php\">\n";
        echo "<meta property=\"og:image\" content=\"{$ogImage}\">\n";
        echo "<meta property=\"og:image:width\" content=\"{$ogImageWidth}\">\n";
        echo "<meta property=\"og:image:height\" content=\"{$ogImageHeight}\">\n";
        echo "<meta property=\"og:site_name\" content=\"{$ogTitle}\">\n";

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
            echo "<link REL=\"shortcut icon\" HREF=\"themes/$ThemeSel/images/favicon.ico\" TYPE=\"image/x-icon\">\n";
        }
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
        echo "<LINK REL=\"StyleSheet\" HREF=\"{$cssPath}?v={$cssVersion}\" TYPE=\"text/css\">\n";

        echo "\n\n\n</head>\n\n";
        themeheader();
    }

    /**
     * Render page footer and terminate output.
     *
     * Return type is void (not never) because HTMX boosted requests return
     * early without calling die(). Callers that invoke footer() inside a
     * conditional block (catch, if/else) MUST add an explicit `return` after
     * the block to prevent undefined-variable errors on the HTMX path.
     */
    public static function footer(): void
    {
        if (HtmxHelper::isBoostedRequest()) {
            ob_end_flush();
            return;
        }

        if (defined('HOME_FILE')) {
            blocks("Down");
        }
        themefooter();
        echo "</body>\n</html>";
        ob_end_flush();
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
