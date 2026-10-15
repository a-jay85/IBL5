<?php

declare(strict_types=1);

namespace Tests\Unit\PageLayout;

use PageLayout\PageLayout;
use PHPUnit\Framework\TestCase;
use Tests\Clock\FixedClock;

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
        PageLayout::setTestClock(null);

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

    /**
     * Everything PageLayout::header() emits before themeheader() runs, with cache-busters normalised.
     * Slicing at the theme marker keeps the pin independent of ADMIN_PHASE_GATE_NOTICE, which another
     * test in this class defines process-wide and which appends a div AFTER themeheader().
     */
    private function renderNormalisedHead(): string
    {
        unset($_SERVER['HTTP_HX_BOOSTED']);
        ob_start();
        PageLayout::header();
        $output = (string) ob_get_clean();

        $pos = strpos($output, '<!-- THEMEHEADER_CALLED -->');
        self::assertNotFalse($pos, 'themeheader() marker missing; head slice would be unbounded');
        $head = substr($output, 0, $pos);

        $normalised = preg_replace('/\?v=\d*/', '?v=V', $head);
        self::assertIsString($normalised);

        return $normalised;
    }

    /** @return array<string, array{string, string}> */
    public static function scriptPathProvider(): array
    {
        $root = \Bootstrap\AppPaths::root();

        return [
            'root index, no base tag' => [$root . '/index.php', ''],
            'one level deep' => [$root . '/admin/index.php', "<base href=\"../\">\n"],
            'two levels deep' => [$root . '/modules/Foo/index.php', "<base href=\"../../\">\n"],
            'outside root, no base tag' => ['/tmp/not-ibl5/modules/Foo/index.php', ''],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('scriptPathProvider')]
    public function testHeadOutputIsByteIdenticalToGolden(string $scriptFilename, string $baseLine): void
    {
        $GLOBALS['sitename'] = 'IBL"><script>alert(1)</script>';
        $GLOBALS['pagetitle'] = 'Page & <b>Title</b>';
        $GLOBALS['slogan'] = 'Slo"gan <i>&</i>';
        unset($_SERVER['HTTP_HOST']);
        $_SERVER['SCRIPT_FILENAME'] = $scriptFilename;

        $golden = file_get_contents(__DIR__ . '/fixtures/head-golden.html');
        self::assertIsString($golden);
        self::assertSame(1, substr_count($golden, "<head>\n"), 'golden must hold exactly one <head> line to splice the base tag after');
        $expected = str_replace("<head>\n", "<head>\n" . $baseLine, $golden);

        self::assertSame($expected, $this->renderNormalisedHead());
    }

    public function testHeadEscapesHostileSiteGlobals(): void
    {
        $GLOBALS['sitename'] = 'IBL"><script>alert(1)</script>';
        $GLOBALS['pagetitle'] = 'Page & <b>Title</b>';
        $GLOBALS['slogan'] = 'Slo"gan <i>&</i>';
        $_SERVER['SCRIPT_FILENAME'] = \Bootstrap\AppPaths::root() . '/index.php';

        $head = $this->renderNormalisedHead();

        self::assertStringNotContainsString('<script>alert(1)</script>', $head);
        self::assertStringContainsString('<title>IBL&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt; Page &amp; &lt;b&gt;Title&lt;/b&gt;</title>', $head);
        self::assertStringContainsString('<META NAME="AUTHOR" CONTENT="IBL&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;">', $head);
        self::assertStringContainsString('<META NAME="COPYRIGHT" CONTENT="Copyright (c) by IBL&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;">', $head);
        self::assertStringContainsString('<META NAME="DESCRIPTION" CONTENT="Slo&quot;gan &lt;i&gt;&amp;&lt;/i&gt;">', $head);
    }

    public function testHeadOpenGraphBlockForPreprodHostIsByteIdentical(): void
    {
        $_SERVER['HTTP_HOST'] = 'pre.iblhoops.net';
        $_SERVER['SCRIPT_FILENAME'] = \Bootstrap\AppPaths::root() . '/index.php';

        $head = $this->renderNormalisedHead();

        $expectedOg = "<meta property=\"og:title\" content=\"IBL \xe2\x80\x94 Internet Basketball League\">\n"
            . "<meta property=\"og:description\" content=\"The Internet Basketball League (IBL) is an online fantasy basketball league powered by the Jump Shot Basketball simulation engine.\">\n"
            . "<meta property=\"og:type\" content=\"website\">\n"
            . "<meta property=\"og:url\" content=\"https://pre.iblhoops.net/ibl5/index.php\">\n"
            . "<meta property=\"og:image\" content=\"https://pre.iblhoops.net/ibl5/images/ibl/logocorner.jpg\">\n"
            . "<meta property=\"og:image:width\" content=\"150\">\n"
            . "<meta property=\"og:image:height\" content=\"150\">\n"
            . "<meta property=\"og:site_name\" content=\"IBL \xe2\x80\x94 Internet Basketball League\">\n";
        self::assertStringContainsString($expectedOg, $head);
        self::assertStringNotContainsString('www.iblhoops.net', $head);
    }

    /** @return array<string, array{string, list<string>}> */
    public static function headHelperProvider(): array
    {
        return [
            'calculateRelativeBasePath' => ['calculateRelativeBasePath', ['currentFile', 'iblRoot']],
            'renderScriptTags' => ['renderScriptTags', ['iblRootPath']],
            'renderMetaTags' => ['renderMetaTags', ['sitename', 'slogan']],
            'renderOpenGraphMetaTags' => ['renderOpenGraphMetaTags', ['requestHost']],
            'renderStylesheetLink' => ['renderStylesheetLink', ['themeSel']],
        ];
    }

    /**
     * Security-surface discharge: each extracted head helper is a pure string builder.
     * Structural invariant: private static, returns string, every parameter is a plain non-nullable
     * string. A helper that grows a \mysqli, session, cookie, or actor-identity parameter, or that
     * changes a parameter to mixed/array, fails here before any review reads the diff.
     *
     * @param list<string> $expectedParams
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('headHelperProvider')]
    public function testExtractedHeadHelpersArePureStringBuilders(string $method, array $expectedParams): void
    {
        $reflection = new \ReflectionMethod(PageLayout::class, $method);

        self::assertTrue($reflection->isPrivate(), "$method must stay private");
        self::assertTrue($reflection->isStatic(), "$method must stay static");

        $returnType = $reflection->getReturnType();
        self::assertInstanceOf(\ReflectionNamedType::class, $returnType);
        self::assertSame('string', $returnType->getName(), "$method must return string");

        $actualParams = [];
        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            self::assertInstanceOf(\ReflectionNamedType::class, $type, "$method parameter \${$parameter->getName()} must be typed");
            self::assertSame('string', $type->getName(), "$method parameter \${$parameter->getName()} must be string");
            self::assertFalse($type->allowsNull(), "$method parameter \${$parameter->getName()} must be non-nullable");
            $actualParams[] = $parameter->getName();
        }
        self::assertSame($expectedParams, $actualParams, "$method parameter list drifted");
    }

    public function testRenderHeadSignatureIsUnchanged(): void
    {
        $reflection = new \ReflectionMethod(PageLayout::class, 'renderHead');

        self::assertTrue($reflection->isPrivate());
        self::assertTrue($reflection->isStatic());
        self::assertSame(0, $reflection->getNumberOfParameters());

        $returnType = $reflection->getReturnType();
        self::assertInstanceOf(\ReflectionNamedType::class, $returnType);
        self::assertSame('void', $returnType->getName());
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

    public function testRenderPageGenerationTimeReportsElapsedSecondsSinceStartTime(): void
    {
        $GLOBALS['start_time'] = microtime(true) - 1.234;
        ob_start();
        PageLayout::renderPageGenerationTime();
        $output = (string) ob_get_clean();

        self::assertMatchesRegularExpression('/^<!-- .+ 1\.\d\d .+ -->\n$/', $output);
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

    public function testRenderPageGenerationTimeUsesInjectedClock(): void
    {
        $label = defined('_PAGEGENERATION') ? \_PAGEGENERATION : 'Page Generation:';
        $unit = defined('_SECONDS') ? \_SECONDS : 'seconds';
        $GLOBALS['start_time'] = 1791549296.0;
        PageLayout::setTestClock(new FixedClock(1791549296, 1791549296.25));

        ob_start();
        PageLayout::renderPageGenerationTime();
        $output = (string) ob_get_clean();

        self::assertSame("<!-- {$label} 0.25 {$unit} -->\n", $output);
    }

    public function testRenderPageGenerationTimeZeroElapsed(): void
    {
        $label = defined('_PAGEGENERATION') ? \_PAGEGENERATION : 'Page Generation:';
        $unit = defined('_SECONDS') ? \_SECONDS : 'seconds';
        $GLOBALS['start_time'] = 1791549296.0;
        PageLayout::setTestClock(new FixedClock(1791549296));

        ob_start();
        PageLayout::renderPageGenerationTime();
        $output = (string) ob_get_clean();

        self::assertSame("<!-- {$label} 0 {$unit} -->\n", $output);
    }

    public function testSetTestClockNullRestoresSystemClock(): void
    {
        PageLayout::setTestClock(new FixedClock(1791549296, 1791549296.25));
        PageLayout::setTestClock(null);
        $GLOBALS['start_time'] = microtime(true) - 1.234;

        ob_start();
        PageLayout::renderPageGenerationTime();
        $output = (string) ob_get_clean();

        self::assertMatchesRegularExpression('/^<!-- .+ 1\.\d\d .+ -->\n$/', $output);
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
