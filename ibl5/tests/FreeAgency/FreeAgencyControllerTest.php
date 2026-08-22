<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\Contracts\FreeAgencyProcessorInterface;
use FreeAgency\Contracts\FreeAgencyServiceInterface;
use FreeAgency\FreeAgencyController;
use FreeAgency\FreeAgencyView;
use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Tests for FreeAgencyController
 *
 * Tests authentication gating and controller instantiation.
 * The POST handlers' redirect dispatch remains E2E/CLI-covered because it
 * relies on static methods (CsrfGuard, HtmxHelper::redirect) that cannot be
 * mocked, but the authz verdict — the security-critical "non-party refused +
 * no mutation" property — is now unit-tested in FreeAgencyProcessorTest.
 */
class FreeAgencyControllerTest extends TestCase
{
    public function testUnauthenticatedUserRendersLoginBox(): void
    {
        $mockDb = new MockDatabase();

        $loginBoxCalled = false;
        $nukeCompat = self::createStub(\Utilities\NukeCompat::class);
        $nukeCompat->method('isUser')->willReturn(false);
        $nukeCompat->method('loginBox')->willReturnCallback(function () use (&$loginBoxCalled): void {
            $loginBoxCalled = true;
        });

        $commonRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $authService = self::createStub(\Auth\AuthService::class);
        $service = self::createStub(FreeAgencyServiceInterface::class);
        $view = self::createStub(FreeAgencyView::class);
        $processor = self::createStub(FreeAgencyProcessorInterface::class);
        $controller = new FreeAgencyController($mockDb, $commonRepo, $authService, $service, $view, $processor, $nukeCompat);
        $controller->handleRequest(null, '', 0);

        $this->assertTrue($loginBoxCalled);
    }

}
