<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\Contracts\FreeAgencyProcessorInterface;
use FreeAgency\Contracts\FreeAgencyServiceInterface;
use FreeAgency\FreeAgencyController;
use FreeAgency\FreeAgencyView;
use Http\HttpRequest;
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
        $controller = new FreeAgencyController($mockDb, $commonRepo, $authService, $service, $view, $processor, new HttpRequest(), $nukeCompat);
        $controller->handleRequest(null, '', 0);

        $this->assertTrue($loginBoxCalled);
    }

    public function testExtractSubmittedOfferFromQueryReturnsNullWhenFirstOfferAbsent(): void
    {
        $method = new \ReflectionMethod(FreeAgencyController::class, 'extractSubmittedOfferFromQuery');

        $this->assertNull($method->invoke($this->buildStubController(new HttpRequest(get: []))));
    }

    public function testExtractSubmittedOfferFromQueryCoercesNumericOfferValues(): void
    {
        $method = new \ReflectionMethod(FreeAgencyController::class, 'extractSubmittedOfferFromQuery');
        $result = $method->invoke($this->buildStubController(new HttpRequest(get: ['offer1' => '5', 'offer2' => 'abc'])));

        $this->assertIsArray($result);
        $this->assertSame(['offer1' => 5, 'offer2' => 0, 'offer3' => 0, 'offer4' => 0, 'offer5' => 0, 'offer6' => 0], $result);
    }

    public function testExtractSubmittedOfferFromQueryZeroesNonNumericOfferValues(): void
    {
        $method = new \ReflectionMethod(FreeAgencyController::class, 'extractSubmittedOfferFromQuery');
        $result = $method->invoke($this->buildStubController(new HttpRequest(get: ['offer1' => '5', 'offer2' => 'abc'])));

        $this->assertIsArray($result);
        $this->assertSame(0, $result['offer2']);
    }

    public function testExtractSubmittedOfferFromQueryReturnsZeroedOfferForArrayValuedFirstOffer(): void
    {
        $method = new \ReflectionMethod(FreeAgencyController::class, 'extractSubmittedOfferFromQuery');
        $result = $method->invoke($this->buildStubController(new HttpRequest(get: ['offer1' => ['5']])));

        $this->assertIsArray($result);
        $this->assertSame(0, $result['offer1']);
    }

    private function buildStubController(HttpRequest $request): FreeAgencyController
    {
        return new FreeAgencyController(
            new MockDatabase(),
            self::createStub(TeamIdentityRepositoryInterface::class),
            self::createStub(\Auth\AuthService::class),
            self::createStub(FreeAgencyServiceInterface::class),
            self::createStub(FreeAgencyView::class),
            self::createStub(FreeAgencyProcessorInterface::class),
            $request,
            self::createStub(\Utilities\NukeCompat::class),
        );
    }

}
