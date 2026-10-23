<?php

declare(strict_types=1);

namespace PageLayout;

use Clock\ClockInterface;
use Clock\SystemClock;
use Utilities\HtmxHelper;

class PageLayout
{
    private static ?ClockInterface $clock = null;

    /**
     * Override the clock used by renderPageGenerationTime(). Pass null to restore SystemClock.
     */
    public static function setTestClock(?ClockInterface $clock): void
    {
        self::$clock = $clock;
    }

    private static function clock(): ClockInterface
    {
        return self::$clock ?? new SystemClock();
    }

    public static function header(): void
    {
        if (HtmxHelper::isBoostedRequest()) {
            self::renderBoostedHeader();
            return;
        }

        self::renderHead();
        if (
            isset($_SESSION['flash_success'])
            && is_string($_SESSION['flash_success'])
            && $_SESSION['flash_success'] !== ''
        ) {
            $flashMessage = \Security\HtmlSanitizer::safeHtmlOutput($_SESSION['flash_success']);
            unset($_SESSION['flash_success']);
            echo '<div role="alert" class="d-alert d-alert-success mb-4">' . $flashMessage . '</div>';
        }
        if (defined('ADMIN_PHASE_GATE_NOTICE')) {
            echo '<div role="alert" class="d-alert d-alert-warning mb-4">Admin mode: You can view this module, but it is currently closed to non-admin GMs.</div>';
        }
    }

    private static function renderBoostedHeader(): void
    {
        /** @var string $sitename */
        global $sitename;
        /** @var string $pagetitle */
        global $pagetitle;

        echo '<title>' . \Security\HtmlSanitizer::e($sitename . ' ' . $pagetitle) . '</title>';

        if (
            isset($_SESSION['flash_success'])
            && is_string($_SESSION['flash_success'])
            && $_SESSION['flash_success'] !== ''
        ) {
            $flashMessage = \Security\HtmlSanitizer::safeHtmlOutput($_SESSION['flash_success']);
            unset($_SESSION['flash_success']);
            echo '<div role="alert" class="d-alert d-alert-success mb-4">' . $flashMessage . '</div>';
        }
        if (defined('ADMIN_PHASE_GATE_NOTICE')) {
            echo '<div role="alert" class="d-alert d-alert-warning mb-4">Admin mode: You can view this module, but it is currently closed to non-admin GMs.</div>';
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
        $relativePath = self::calculateRelativeBasePath($currentFile, $iblRoot);

        echo '<!DOCTYPE html>';
        echo '<html lang="en">';
        echo "<head>\n";
        if ($relativePath !== '') {
            echo "<base href=\"{$relativePath}\">\n";
        }
        echo "<title>" . \Security\HtmlSanitizer::e($sitename . ' ' . $pagetitle) . "</title>\n";
        echo '<meta name="google-site-verification" content="3y3xJYDHSYUitn7cbfFfI6C2BiK_q66dtRfykpzHW5w" />';
        echo self::renderScriptTags($iblRoot);

        // Meta tags (inlined from includes/meta.php)
        echo self::renderMetaTags($sitename, $slogan);

        // Open Graph meta tags (for LinkedIn, Facebook, etc.)
        // Host is used ONLY to select the preprod og:image variant -- never interpolated into output.
        // Output host is always a hardcoded literal, so a poisoned Host header (cached + served to all
        // anonymous visitors via PageCache, which does not key on Host) cannot reach the meta tags.
        $requestHost = is_string($_SERVER['HTTP_HOST'] ?? null) ? $_SERVER['HTTP_HOST'] : '';
        echo self::renderOpenGraphMetaTags($requestHost);

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

        // Root-absolute path so it resolves independently of the conditional <base href> and module depth.
        echo "<link rel=\"icon\" href=\"/ibl5/favicon.ico\" type=\"image/x-icon\">\n";
        // Google Fonts (inlined from includes/custom_files/custom_head.php)
        echo self::renderFontPreconnectLinks();

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

        // CSS stylesheet — loaded once (fixes duplicate CSS loading bug)
        echo self::renderStylesheetLink($ThemeSel);

        echo "\n\n\n</head>\n\n";
        themeheader();
    }

    /**
     * Relative path from the executing script's directory back to the ibl5 root.
     * Returns '' when the script sits at the root or outside it (no <base> tag is emitted then).
     * Pure: reads no superglobals; the caller resolves $_SERVER['SCRIPT_FILENAME'] and AppPaths::root().
     */
    private static function calculateRelativeBasePath(string $currentFile, string $iblRoot): string
    {
        if (strpos($currentFile, $iblRoot) !== 0) {
            return '';
        }

        // If we're in a subdirectory of ibl5, calculate the relative path back to root
        $relativeFromIblRoot = substr(dirname($currentFile), strlen($iblRoot));
        if ($relativeFromIblRoot === '') {
            return '';
        }

        // Count directory levels and build relative path
        $levels = substr_count(trim($relativeFromIblRoot, '/'), '/') + 1;

        return str_repeat('../', $levels);
    }

    /**
     * The eight <script src> tags, each cache-busted with the file's mtime (0 when the file is missing).
     * Order is load-bearing: htmx first, htmx-init after the libraries it wires.
     * Every tag carries `defer` so the parser does not block on them before first paint.
     * Deferred scripts still run in document order, after parsing finishes (readyState
     * 'interactive') and before DOMContentLoaded. Each library that self-initialises either
     * listens for DOMContentLoaded or runs immediately when readyState is not 'loading', so
     * both paths see a fully parsed DOM. Code outside these files calls their globals only
     * from event handlers or fetch callbacks, behind a typeof/window guard.
     */
    private static function renderScriptTags(string $iblRootPath): string
    {
        $jsFiles = [
            'jslib/htmx.min.js',
            'jslib/sorttable.js',
            'jslib/responsive-tables.js',
            'jslib/name-abbreviation.js',
            'jslib/user-team-highlighter.js',
            'jslib/sticky-page-header.js',
            'jslib/htmx-init.js',
            'jslib/local-time.js',
        ];
        $html = '';
        foreach ($jsFiles as $jsFile) {
            $fullPath = $iblRootPath . '/' . $jsFile;
            $mtime = is_file($fullPath) ? filemtime($fullPath) : false;
            $version = is_int($mtime) ? $mtime : 0;
            $html .= "<script src=\"{$jsFile}?v={$version}\" defer></script>";
        }

        return $html;
    }

    /**
     * Charset, viewport, desktop-view toggle and the legacy META block (inlined from includes/meta.php).
     * $sitename and $slogan are escaped here; the caller passes the raw globals.
     */
    private static function renderMetaTags(string $sitename, string $slogan): string
    {
        return "<meta charset=\"utf-8\">\n"
            . "<meta id=\"viewport-meta\" name=\"viewport\" content=\"width=device-width, initial-scale=1.0, viewport-fit=cover\">\n"
            . "<script>(function(){try{if(localStorage.getItem('ibl_desktop_view')==='1'){document.getElementById('viewport-meta').setAttribute('content','width=1440');document.documentElement.classList.add('desktop-view-active');}}catch(e){}})()</script>\n"
            . "<META HTTP-EQUIV=\"EXPIRES\" CONTENT=\"0\">\n"
            . "<META NAME=\"RESOURCE-TYPE\" CONTENT=\"DOCUMENT\">\n"
            . "<META NAME=\"DISTRIBUTION\" CONTENT=\"GLOBAL\">\n"
            . "<META NAME=\"AUTHOR\" CONTENT=\"" . \Security\HtmlSanitizer::e($sitename) . "\">\n"
            . "<META NAME=\"COPYRIGHT\" CONTENT=\"Copyright (c) by " . \Security\HtmlSanitizer::e($sitename) . "\">\n"
            . "<META NAME=\"KEYWORDS\" CONTENT=\"basketball, fantasy basketball, basketball league, IBL, Internet Basketball League, basketball simulation, basketball stats, NBA, basketball draft, free agency, basketball trading, basketball standings, basketball schedule\">\n"
            . "<META NAME=\"DESCRIPTION\" CONTENT=\"" . \Security\HtmlSanitizer::e($slogan) . "\">\n"
            . "<META NAME=\"ROBOTS\" CONTENT=\"INDEX, FOLLOW\">\n"
            . "<META NAME=\"REVISIT-AFTER\" CONTENT=\"1 DAYS\">\n"
            . "<META NAME=\"RATING\" CONTENT=\"GENERAL\">\n";
    }

    /**
     * Open Graph block. $requestHost only selects the preprod image variant; the emitted host is always
     * one of two hardcoded literals (XSS fix #1106), so the request header never reaches the output.
     */
    private static function renderOpenGraphMetaTags(string $requestHost): string
    {
        $ogHost = ($requestHost === 'pre.iblhoops.net') ? 'pre.iblhoops.net' : 'www.iblhoops.net';
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

        return "<meta property=\"og:title\" content=\"{$ogTitle}\">\n"
            . "<meta property=\"og:description\" content=\"{$ogDescription}\">\n"
            . "<meta property=\"og:type\" content=\"website\">\n"
            . "<meta property=\"og:url\" content=\"{$ogBaseUrl}/index.php\">\n"
            . "<meta property=\"og:image\" content=\"{$ogImage}\">\n"
            . "<meta property=\"og:image:width\" content=\"{$ogImageWidth}\">\n"
            . "<meta property=\"og:image:height\" content=\"{$ogImageHeight}\">\n"
            . "<meta property=\"og:site_name\" content=\"{$ogTitle}\">\n";
    }

    /**
     * The single theme stylesheet, cache-busted with its cwd-relative mtime ('' when the file is absent).
     */
    private static function renderStylesheetLink(string $themeSel): string
    {
        $cssPath = "themes/$themeSel/style/style.css";
        $cssVersion = file_exists($cssPath) ? filemtime($cssPath) : '';
        // FOUT-prevention invariant: this tag must remain render-blocking.
        // The .fonts-loading { visibility: hidden } rule lives in design/base.css (compiled here).
        // Making it async, lazy, or rel="preload" will silently reintroduce FOUT — no test catches it.
        return "<LINK REL=\"StyleSheet\" HREF=\"{$cssPath}?v={$cssVersion}\" TYPE=\"text/css\">\n";
    }

    public static function renderFontPreconnectLinks(): string
    {
        return '<link rel="preconnect" href="https://fonts.googleapis.com">'
            . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700;800&family=Barlow:wght@400;500;600;700&display=block" rel="stylesheet">';
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
        $end_time = self::clock()->microtime();
        $total_time = $end_time - $start_time;
        $pageGenLabel = defined('_PAGEGENERATION') ? \_PAGEGENERATION : 'Page Generation:';
        $secondsLabel = defined('_SECONDS') ? \_SECONDS : 'seconds';
        $total_time_str = $pageGenLabel . " " . substr((string) $total_time, 0, 4) . " " . $secondsLabel;

        echo "<!-- " . $total_time_str . " -->\n";
    }
}
