<?php

declare(strict_types=1);

namespace Tests\Unit\PageLayout;

use PageLayout\PageLayout;
use PHPUnit\Framework\TestCase;

class PageLayoutTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $savedServer;
    /** @var array<string, mixed> */
    private array $savedSession;
    /** @var array<string, mixed> */
    private array $savedGlobals;

    protected function setUp(): void
    {
        $this->savedServer = $_SERVER;
        $this->savedSession = $_SESSION ?? [];
        $this->savedGlobals = [];

        foreach (['sitename', 'pagetitle', 'slogan', 'name', 'user', 'start_time', 'authService'] as $key) {
            $this->savedGlobals[$key] = $GLOBALS[$key] ?? '__UNSET__';
        }

        require_once dirname(__DIR__, 2) . '/Module/EntryPoints/theme-stubs.php';

        $legacyPath = dirname(__DIR__, 3) . '/classes/Bootstrap/LegacyFunctions.php';
        require_once $legacyPath;

        $authStub = static::createStub(\Auth\Contracts\AuthServiceInterface::class);
        $authStub->method('getCookieArray')->willReturn(null);
        $GLOBALS['authService'] = $authStub;

        $GLOBALS['sitename'] = 'Test Site';
        $GLOBALS['pagetitle'] = 'Test Page';
        $GLOBALS['slogan'] = 'Test Slogan';
        $GLOBALS['name'] = '';
        $GLOBALS['user'] = '';



        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->savedServer;
        $_SESSION = $this->savedSession;

        foreach ($this->savedGlobals as $key => $value) {
            if ($value === '__UNSET__') {
                unset($GLOBALS[$key]);
            } else {
                $GLOBALS[$key] = $value;
            }
        }

        unset($GLOBALS['mysqli_db']);
    }

    public function testHeaderBoostedRendsTitleOnly(): void
    {
        $_SERVER['HTTP_HX_BOOSTED'] = 'true';
        ob_start();
        PageLayout::header();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('<title>', $output);
        self::assertStringContainsString('Test Site', $output);
        self::assertStringNotContainsString('THEMEHEADER_CALLED', $output);
        self::assertStringNotContainsString('<!DOCTYPE html>', $output);
    }

    public function testHeaderBoostedShowsFlashMessage(): void
    {
        $_SERVER['HTTP_HX_BOOSTED'] = 'true';
        $_SESSION['flash_success'] = 'Operation completed';
        ob_start();
        PageLayout::header();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('Operation completed', $output);
        self::assertStringContainsString('ibl-alert--success', $output);
        self::assertArrayNotHasKey('flash_success', $_SESSION);
    }

    public function testHeaderNonBoostedCallsThemeHeader(): void
    {
        unset($_SERVER['HTTP_HX_BOOSTED']);
        ob_start();
        PageLayout::header();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('<!DOCTYPE html>', $output);
        self::assertStringContainsString('THEMEHEADER_CALLED', $output);
        self::assertStringContainsString('<title>', $output);
    }

    public function testHeaderNonBoostedEmitsExpectedHeadResources(): void
    {
        // Characterization: lock the non-boosted <head> baseline before editing it.
        unset($_SERVER['HTTP_HX_BOOSTED']);
        ob_start();
        PageLayout::header();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('<title>', $output);
        self::assertStringContainsString('StyleSheet', $output);
        self::assertStringContainsString('fonts.googleapis.com/css2', $output);
    }

    public function testHeaderNonBoostedEmitsFaviconLink(): void
    {
        unset($_SERVER['HTTP_HX_BOOSTED']);
        ob_start();
        PageLayout::header();
        $output = (string) ob_get_clean();

        // Favicon emitted unconditionally with a root-absolute, depth-independent href.
        self::assertStringContainsString('rel="icon"', $output);
        self::assertStringContainsString('href="/ibl5/favicon.ico"', $output);
    }

    public function testHeaderNonBoostedFaviconHrefIsRootAbsolute(): void
    {
        unset($_SERVER['HTTP_HX_BOOSTED']);
        ob_start();
        PageLayout::header();
        $output = (string) ob_get_clean();

        // The old theme-relative favicon path must be gone (it resolved against <base href>/module depth).
        self::assertStringNotContainsString('images/favicon.ico', $output);
        self::assertStringNotContainsString('shortcut icon', $output);
    }

    public function testHeaderNonBoostedSkipsRecordHitWhenNoDb(): void
    {
        unset($_SERVER['HTTP_HX_BOOSTED']);
        unset($GLOBALS['mysqli_db']);
        ob_start();
        PageLayout::header();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('<!DOCTYPE html>', $output);
    }

    public function testFooterBoostedFlushesBuffer(): void
    {
        $_SERVER['HTTP_HX_BOOSTED'] = 'true';
        ob_start();
        ob_start();
        echo 'TEST_BODY';
        PageLayout::footer();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('TEST_BODY', $output);
        self::assertStringNotContainsString('THEMEFOOTER_CALLED', $output);
    }

    public function testFooterNonBoostedCallsThemeFooter(): void
    {
        unset($_SERVER['HTTP_HX_BOOSTED']);
        ob_start();
        ob_start();
        echo 'BODY_CONTENT';
        PageLayout::footer();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('THEMEFOOTER_CALLED', $output);
        self::assertStringContainsString('</body>', $output);
        self::assertStringContainsString('</html>', $output);
    }

    public function testRenderFontPreconnectLinksReturnsBarlowTriple(): void
    {
        $result = PageLayout::renderFontPreconnectLinks();

        self::assertStringContainsString('<link rel="preconnect" href="https://fonts.googleapis.com">', $result);
        self::assertStringContainsString('<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>', $result);
        self::assertStringContainsString('family=Barlow+Condensed:wght@500;600;700;800', $result);
        self::assertStringContainsString('family=Barlow:wght@400;500;600;700', $result);
        self::assertStringContainsString('display=block', $result);
        self::assertStringContainsString('rel="stylesheet"', $result);
        // Exactly three link tags — no more, no less
        self::assertSame(3, substr_count($result, '<link'));
    }

    public function testRenderPageGenerationTimeOutputsComment(): void
    {
        $GLOBALS['start_time'] = microtime(true) - 1.0;
        ob_start();
        PageLayout::renderPageGenerationTime();
        $output = (string) ob_get_clean();

        self::assertMatchesRegularExpression('/<!--.*Page Generation.*-->/', $output);
    }

    public function testRenderPageGenerationTimeUsesDefinedLabels(): void
    {
        if (!defined('_PAGEGENERATION')) {
            define('_PAGEGENERATION', 'Generated in:');
        }
        if (!defined('_SECONDS')) {
            define('_SECONDS', 'secs');
        }
        $GLOBALS['start_time'] = microtime(true) - 0.5;
        ob_start();
        PageLayout::renderPageGenerationTime();
        $output = (string) ob_get_clean();

        self::assertStringContainsString(\_PAGEGENERATION, $output);
        self::assertStringContainsString(\_SECONDS, $output);
    }

    public function testHeaderBoostedShowsAdminPhaseGateNotice(): void
    {
        $_SERVER['HTTP_HX_BOOSTED'] = 'true';
        if (!defined('ADMIN_PHASE_GATE_NOTICE')) {
            define('ADMIN_PHASE_GATE_NOTICE', true);
        }
        ob_start();
        PageLayout::header();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('ibl-alert--warning', $output);
        self::assertStringContainsString('Admin mode', $output);
    }

    public function testOgMetaTagsNeverReflectMaliciousHost(): void
    {
        unset($_SERVER['HTTP_HX_BOOSTED']);
        $_SERVER['HTTP_HOST'] = 'evil"><script>alert(1)</script>';
        ob_start();
        PageLayout::header();
        $output = (string) ob_get_clean();

        self::assertStringNotContainsString('<script>alert', $output);
        self::assertStringNotContainsString('evil', $output);
        self::assertStringContainsString('https://www.iblhoops.net/ibl5', $output);
    }

    public function testOgMetaTagsUseCanonicalHostWhenHostHeaderIsNormal(): void
    {
        unset($_SERVER['HTTP_HX_BOOSTED']);
        $_SERVER['HTTP_HOST'] = 'www.iblhoops.net';
        ob_start();
        PageLayout::header();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('content="https://www.iblhoops.net/ibl5', $output);
    }

    public function testOgMetaTagsUseCanonicalHostWhenNoHostHeader(): void
    {
        unset($_SERVER['HTTP_HX_BOOSTED']);
        unset($_SERVER['HTTP_HOST']);
        ob_start();
        PageLayout::header();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('content="https://www.iblhoops.net/ibl5', $output);
    }

    public function testOgMetaTagsUsePreprodHostForPreprodEnvironment(): void
    {
        unset($_SERVER['HTTP_HX_BOOSTED']);
        $_SERVER['HTTP_HOST'] = 'pre.iblhoops.net';
        ob_start();
        PageLayout::header();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('https://pre.iblhoops.net/ibl5/images/ibl/logocorner.jpg', $output);
        self::assertStringContainsString('content="150"', $output);
    }
}
