<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use Tests\Integration\IntegrationTestCase;

/**
 * Base class for module entry point integration tests.
 *
 * Provides the scaffolding to include module index.php files in PHPUnit:
 * - Security gate bypass (MODULE_FILE constant, $_SERVER['PHP_SELF'])
 * - Global state setup ($authService, $leagueContext, theme globals)
 * - Superglobal save/restore ($_GET, $_POST, $_REQUEST, $_SERVER, $_SESSION)
 * - Double output buffering to handle PageLayout::footer()'s ob_end_flush()
 * - Theme function stubs (themeheader, themefooter, etc.)
 */
abstract class ModuleEntryPointTestCase extends IntegrationTestCase
{
    /** @var array<string, mixed> */
    private array $savedGet;
    /** @var array<string, mixed> */
    private array $savedPost;
    /** @var array<string, mixed> */
    private array $savedRequest;
    /** @var array<string, mixed> */
    private array $savedServer;
    /** @var array<string, mixed> */
    private array $savedSession;

    /** @var list<string> Global keys set during test that need cleanup */
    private array $injectedGlobalKeys = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Save superglobal state
        $this->savedGet = $_GET;
        $this->savedPost = $_POST;
        $this->savedRequest = $_REQUEST;
        $this->savedServer = $_SERVER;
        $this->savedSession = $_SESSION ?? [];

        // Security gate bypass
        $_SERVER['PHP_SELF'] = '/modules.php';
        if (!defined('MODULE_FILE')) {
            define('MODULE_FILE', true);
        }

        // Use HTMX boosted mode so PageLayout::header() skips SiteStatistics::recordHit()
        // and PageLayout::footer() skips themeheader/footer. This keeps tests focused on
        // the module's own parameter handling, not the page chrome.
        $_SERVER['HTTP_HX_BOOSTED'] = 'true';

        // Ensure LegacyFunctions are loaded (get_lang, cookiedecode, is_user, etc.)
        $legacyPath = dirname(__DIR__, 3) . '/classes/Bootstrap/LegacyFunctions.php';
        require_once $legacyPath;

        // Define theme function stubs in global namespace
        require_once __DIR__ . '/theme-stubs.php';

        // Set globals that modules expect
        $this->setDefaultGlobals();
    }

    protected function tearDown(): void
    {
        // Restore superglobals
        $_GET = $this->savedGet;
        $_POST = $this->savedPost;
        $_REQUEST = $this->savedRequest;
        $_SERVER = $this->savedServer;
        $_SESSION = $this->savedSession;

        // Clean up injected globals
        foreach ($this->injectedGlobalKeys as $key) {
            unset($GLOBALS[$key]);
        }
        $this->injectedGlobalKeys = [];

        parent::tearDown();
    }

    /**
     * Include a module's index.php, capturing all output.
     *
     * Uses double output buffering: an inner buffer for PageLayout::footer()'s
     * ob_end_flush() to consume, and an outer buffer to capture everything.
     *
     * @param string $moduleName Directory name under modules/ (e.g., 'Schedule')
     * @param array<string, mixed> $get Simulated $_GET parameters
     * @param array<string, mixed> $post Simulated $_POST parameters
     * @param array<string, mixed> $extraGlobals Additional $GLOBALS to set (for modules that read from $GLOBALS)
     * @return string Captured output
     */
    protected function runModule(
        string $moduleName,
        array $get = [],
        array $post = [],
        array $extraGlobals = [],
    ): string {
        // Set superglobals
        $_GET = $get;
        $_POST = $post;
        $_REQUEST = array_merge($get, $post);

        // Set extra globals (for modules like Search that read from $GLOBALS)
        foreach ($extraGlobals as $key => $value) {
            $GLOBALS[$key] = $value;
            $this->injectedGlobalKeys[] = $key;
        }

        // Reset pagetitle for each module run
        $GLOBALS['pagetitle'] = '';

        $modulePath = dirname(__DIR__, 3) . "/modules/{$moduleName}/index.php";

        // Module files expect certain variables in local scope (mainfile.php extracts
        // $_REQUEST into $GLOBALS at top level where global = local scope). Since we
        // include from inside a method, we must extract them into local scope.
        // phpcs:ignore -- extract is intentional here for PHP-Nuke compatibility
        extract($extraGlobals);

        // Double buffering: L2 (outer/capture) and L1 (inner/sacrificial for footer's ob_end_flush)
        $baseLevel = ob_get_level();
        ob_start(); // L2 — capture buffer
        ob_start(); // L1 — sacrificial buffer

        try {
            include $modulePath;
        } catch (\Throwable $e) {
            // Clean up any buffers above our capture level
            while (ob_get_level() > $baseLevel + 1) {
                ob_end_clean();
            }
            ob_get_clean(); // L2
            throw $e;
        }

        // Footer's ob_end_flush() consumed L1 and flushed into L2.
        // If L1 is still active (module didn't call footer), flush it.
        while (ob_get_level() > $baseLevel + 1) {
            ob_end_flush();
        }

        return (string) ob_get_clean(); // L2
    }

    private function setDefaultGlobals(): void
    {
        // Auth stub — unauthenticated by default
        $authStub = $this->createStub(\Auth\Contracts\AuthServiceInterface::class);
        $authStub->method('isAuthenticated')->willReturn(false);
        $authStub->method('isAdmin')->willReturn(false);
        $authStub->method('getCookieArray')->willReturn(null);
        $GLOBALS['authService'] = $authStub;

        // League context stub
        $lcStub = $this->createStub(\League\LeagueContext::class);
        $GLOBALS['leagueContext'] = $lcStub;

        // PHP-Nuke globals
        $GLOBALS['prefix'] = 'nuke';
        $GLOBALS['user_prefix'] = 'nuke';
        $GLOBALS['sitename'] = 'IBL Test';
        $GLOBALS['pagetitle'] = '';
        $GLOBALS['slogan'] = 'Test Slogan';
        $GLOBALS['name'] = '';
        $GLOBALS['user'] = '';
        $GLOBALS['cookie'] = ['', '', ''];
        $GLOBALS['currentlang'] = 'english';
        $GLOBALS['language'] = 'english';
        $GLOBALS['op'] = '';
        $GLOBALS['pa'] = '';
        $GLOBALS['articlecomm'] = 0;

        // Initialize session if needed
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }

        $this->injectedGlobalKeys = array_merge($this->injectedGlobalKeys, [
            'authService', 'leagueContext', 'prefix', 'user_prefix',
            'sitename', 'pagetitle', 'slogan', 'name', 'user', 'cookie',
            'currentlang', 'language', 'op', 'pa', 'articlecomm',
        ]);
    }

    /**
     * Set the auth stub to simulate an authenticated user.
     */
    protected function authenticateAs(string $username): void
    {
        $authStub = $this->createStub(\Auth\Contracts\AuthServiceInterface::class);
        $authStub->method('isAuthenticated')->willReturn(true);
        $authStub->method('isAdmin')->willReturn(false);
        $authStub->method('getCookieArray')->willReturn([$username, $username, '']);
        $GLOBALS['authService'] = $authStub;
        $GLOBALS['user'] = base64_encode("{$username}:{$username}:");
        $GLOBALS['cookie'] = [$username, $username, ''];
    }
}
