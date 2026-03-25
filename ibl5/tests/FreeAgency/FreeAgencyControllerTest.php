<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\FreeAgencyController;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FreeAgencyController
 *
 * Tests authentication gating and controller instantiation.
 * POST handler tests are covered by E2E tests since they rely on
 * static methods (CsrfGuard, HtmxHelper::redirect) that cannot be mocked.
 */
class FreeAgencyControllerTest extends TestCase
{
    public function testUnauthenticatedUserRendersLoginBox(): void
    {
        $mockDb = new \MockDatabase();

        $loginBoxCalled = false;
        $nukeCompat = $this->createStub(\Utilities\NukeCompat::class);
        $nukeCompat->method('isUser')->willReturn(false);
        $nukeCompat->method('loginBox')->willReturnCallback(function () use (&$loginBoxCalled): void {
            $loginBoxCalled = true;
        });

        $controller = new FreeAgencyController($mockDb, $nukeCompat);
        $controller->handleRequest(null, '', 0);

        $this->assertTrue($loginBoxCalled);
    }

    public function testControllerCanBeInstantiated(): void
    {
        $mockDb = new \MockDatabase();
        $controller = new FreeAgencyController($mockDb);

        $this->assertInstanceOf(FreeAgencyController::class, $controller);
    }
}
