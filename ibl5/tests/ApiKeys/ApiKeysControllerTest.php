<?php

declare(strict_types=1);

namespace Tests\ApiKeys;

use ApiKeys\ApiKeysController;
use ApiKeys\Contracts\ApiKeysServiceInterface;
use ApiKeys\Contracts\ApiKeysViewInterface;
use Auth\Contracts\AuthServiceInterface;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Security\CsrfGuard;
use Tests\WideUnit\WideUnitTestCase;
use Utilities\NukeCompat;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ApiKeysControllerTest extends WideUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/classes/Bootstrap/LegacyFunctions.php';

        $stubAuthService = self::createStub(AuthServiceInterface::class);
        $stubAuthService->method('getCookieArray')->willReturn(null);
        $stubAuthService->method('getUsername')->willReturn(null);
        $GLOBALS['authService'] = $stubAuthService;
        $GLOBALS['user'] = '';
        $GLOBALS['sitename'] = '';
        $GLOBALS['pagetitle'] = '';
        $_SERVER['HTTP_HX_BOOSTED'] = 'true';

        if (!isset($_SESSION)) {
            $_SESSION = [];
        }

        $_POST = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['authService'], $GLOBALS['user'], $GLOBALS['sitename'], $GLOBALS['pagetitle']);
        unset($_SERVER['HTTP_HX_BOOSTED'], $_SERVER['REQUEST_METHOD']);
        $_POST = [];
        parent::tearDown();
    }

    private function runHandle(ApiKeysController $controller, string $op): string
    {
        $baseLevel = ob_get_level();
        ob_start();
        ob_start();
        $output = '';
        try {
            $controller->handle($op, 'cookie');
        } catch (\Throwable $e) {
            while (ob_get_level() > $baseLevel) {
                ob_end_clean();
            }
            throw $e;
        }
        while (ob_get_level() > $baseLevel) {
            $output = (string) ob_get_clean() . $output;
        }
        return $output;
    }

    private function buildController(
        ApiKeysServiceInterface $service,
        ?NukeCompat $nukeCompat = null,
        ?AuthServiceInterface $authService = null,
        ?ApiKeysViewInterface $view = null,
    ): ApiKeysController {
        if ($nukeCompat === null) {
            $nukeCompat = self::createStub(NukeCompat::class);
            $nukeCompat->method('isUser')->willReturn(true);
        }
        if ($authService === null) {
            $authService = self::createStub(AuthServiceInterface::class);
            $authService->method('getUserId')->willReturn(5);
            $authService->method('getUsername')->willReturn('testgm');
            $authService->method('getCookieArray')->willReturn(null);
        }
        if ($view === null) {
            $view = self::createStub(ApiKeysViewInterface::class);
            $view->method('renderExportGuide')->willReturn('EXPORT-GUIDE');
            $view->method('renderNewKeyState')->willReturnCallback(
                static fn(string $k): string => 'NEW-KEY:' . $k
            );
        }
        return new ApiKeysController($service, $view, $nukeCompat, $authService);
    }

    public function testUnauthenticatedRequestReturnsAuthFailure(): void
    {
        $nukeCompat = self::createMock(NukeCompat::class);
        $nukeCompat->method('isUser')->willReturn(false);
        $nukeCompat->expects(self::once())->method('loginBox');

        $authService = self::createMock(AuthServiceInterface::class);
        $authService->expects(self::never())->method('getUserId');

        $service = self::createMock(ApiKeysServiceInterface::class);
        $service->expects(self::never())->method('generateKeyForUser');
        $service->expects(self::never())->method('revokeKeyForUser');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        CsrfGuard::clearTokens('api_keys_revoke');
        $_POST['_csrf_token'] = CsrfGuard::generateRawToken('api_keys_revoke');

        $output = $this->runHandle($this->buildController($service, $nukeCompat, $authService), 'revoke');
        self::assertSame('', $output);
    }

    public function testNonPostMethodReturnsEarly(): void
    {
        $service = self::createMock(ApiKeysServiceInterface::class);
        $service->expects(self::never())->method('generateKeyForUser');
        $service->expects(self::never())->method('revokeKeyForUser');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        CsrfGuard::clearTokens('api_keys_generate');
        $_POST['_csrf_token'] = CsrfGuard::generateRawToken('api_keys_generate');

        $output = $this->runHandle($this->buildController($service), 'generate');
        self::assertSame('', $output);
    }

    public function testNonPostRevokeReturnsEarly(): void
    {
        $service = self::createMock(ApiKeysServiceInterface::class);
        $service->expects(self::never())->method('generateKeyForUser');
        $service->expects(self::never())->method('revokeKeyForUser');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        CsrfGuard::clearTokens('api_keys_revoke');
        $_POST['_csrf_token'] = CsrfGuard::generateRawToken('api_keys_revoke');

        $output = $this->runHandle($this->buildController($service), 'revoke');
        self::assertSame('', $output);
    }

    public function testCsrfMismatchReturnsCsrfFailure(): void
    {
        $service = self::createMock(ApiKeysServiceInterface::class);
        $service->expects(self::never())->method('revokeKeyForUser');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = 'deadbeef';

        $output = $this->runHandle($this->buildController($service), 'revoke');
        self::assertStringContainsString('Invalid or expired form submission', $output);
        self::assertStringContainsString('EXPORT-GUIDE', $output);
    }

    public function testGenerateCsrfMismatchDoesNotGenerateKey(): void
    {
        $service = self::createMock(ApiKeysServiceInterface::class);
        $service->expects(self::never())->method('generateKeyForUser');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = 'deadbeef';

        $output = $this->runHandle($this->buildController($service), 'generate');
        self::assertStringContainsString('Invalid or expired form submission', $output);
        self::assertStringNotContainsString('NEW-KEY:', $output);
    }

    public function testMissingUserIdReturnsIdentityFailure(): void
    {
        $authService = self::createStub(AuthServiceInterface::class);
        $authService->method('getUserId')->willReturn(null);
        $authService->method('getUsername')->willReturn(null);
        $authService->method('getCookieArray')->willReturn(null);

        $service = self::createMock(ApiKeysServiceInterface::class);
        $service->expects(self::never())->method('generateKeyForUser');
        $service->expects(self::never())->method('revokeKeyForUser');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        CsrfGuard::clearTokens('api_keys_revoke');
        $_POST['_csrf_token'] = CsrfGuard::generateRawToken('api_keys_revoke');

        $output = $this->runHandle($this->buildController($service, null, $authService), 'revoke');
        self::assertStringContainsString('Unable to determine user identity.', $output);
        self::assertStringNotContainsString('EXPORT-GUIDE', $output);
    }

    public function testValidPostGenerateExecutesAction(): void
    {
        $service = self::createMock(ApiKeysServiceInterface::class);
        $service->expects(self::once())
            ->method('generateKeyForUser')
            ->with(5, 'testgm')
            ->willReturn(['raw_key' => 'ibl_testkey', 'prefix' => 'ibl_test']);
        $service->expects(self::never())->method('revokeKeyForUser');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        CsrfGuard::clearTokens('api_keys_generate');
        $_POST['_csrf_token'] = CsrfGuard::generateRawToken('api_keys_generate');

        $output = $this->runHandle($this->buildController($service), 'generate');
        self::assertStringContainsString('NEW-KEY:ibl_testkey', $output);
        self::assertStringContainsString('EXPORT-GUIDE', $output);
    }

    public function testValidPostRevokeExecutesAction(): void
    {
        $service = self::createMock(ApiKeysServiceInterface::class);
        $service->expects(self::once())->method('revokeKeyForUser')->with(5);
        $service->expects(self::never())->method('generateKeyForUser');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        CsrfGuard::clearTokens('api_keys_revoke');
        $_POST['_csrf_token'] = CsrfGuard::generateRawToken('api_keys_revoke');

        $output = $this->runHandle($this->buildController($service), 'revoke');
        self::assertSame('', $output);
    }
}
