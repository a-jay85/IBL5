<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use Season\Season;
use Waivers\Contracts\WaiversProcessorInterface;
use Waivers\Contracts\WaiversServiceInterface;
use Waivers\Contracts\WaiversViewInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Repositories\Contracts\SalaryCapRepositoryInterface;
use Auth\Contracts\AuthServiceInterface;
use Http\HttpRequest;
use Waivers\WaiversController;

class WaiversControllerTest extends TestCase
{
    /**
     * Builds a controller whose collaborators all let handleWaiverRequest() reach the
     * AuthService lookup: NukeCompat reports a logged-in visitor and the injected Season
     * reports waivers open. Override only what a given test asserts on.
     */
    private function buildController(
        ?TeamIdentityRepositoryInterface $teamIdentityRepo = null,
        ?AuthServiceInterface $authService = null,
        ?\Utilities\NukeCompat $nukeCompat = null,
        ?HttpRequest $request = null,
    ): WaiversController {
        if ($nukeCompat === null) {
            $nukeCompatStub = self::createStub(\Utilities\NukeCompat::class);
            $nukeCompatStub->method('isUser')->willReturn(true);
            $nukeCompat = $nukeCompatStub;
        }

        $seasonStub = self::createStub(Season::class);
        $seasonStub->method('areWaiversAllowed')->willReturn(true);

        return new WaiversController(
            self::createStub(WaiversServiceInterface::class),
            self::createStub(WaiversProcessorInterface::class),
            self::createStub(WaiversViewInterface::class),
            $teamIdentityRepo ?? self::createStub(TeamIdentityRepositoryInterface::class),
            self::createStub(SalaryCapRepositoryInterface::class),
            $nukeCompat,
            self::createStub(\mysqli::class),
            $authService ?? self::createStub(AuthServiceInterface::class),
            $request ?? new HttpRequest(),
            null,
            $seasonStub,
        );
    }

    public function testWaiverPoolMovesCategoryIdIsPositive(): void
    {
        $this->assertGreaterThan(0, WaiversController::WAIVER_POOL_MOVES_CATEGORY_ID);
    }

    public function testHandleWaiverRequestForwardsAuthServiceUsername(): void
    {
        $teamIdentityRepoMock = $this->createMock(TeamIdentityRepositoryInterface::class);
        $teamIdentityRepoMock->expects($this->once())
            ->method('getUserByUsername')
            ->with('gm')
            ->willReturn(null);

        $authServiceStub = self::createStub(AuthServiceInterface::class);
        $authServiceStub->method('getUsername')->willReturn('gm');

        $controller = $this->buildController(
            teamIdentityRepo: $teamIdentityRepoMock,
            authService: $authServiceStub,
        );

        $controller->handleWaiverRequest('gm', 'view');
    }

    public function testHandleWaiverRequestForwardsEmptyStringWhenAuthServiceHasNoUsername(): void
    {
        $teamIdentityRepoMock = $this->createMock(TeamIdentityRepositoryInterface::class);
        $teamIdentityRepoMock->expects($this->once())
            ->method('getUserByUsername')
            ->with('')
            ->willReturn(null);

        $authServiceStub = self::createStub(AuthServiceInterface::class);
        $authServiceStub->method('getUsername')->willReturn(null);

        $controller = $this->buildController(
            teamIdentityRepo: $teamIdentityRepoMock,
            authService: $authServiceStub,
        );

        $controller->handleWaiverRequest('gm', 'view');
    }

    public function testUnknownUsernameFallsBackToLoginBox(): void
    {
        $nukeCompatMock = $this->createMock(\Utilities\NukeCompat::class);
        $nukeCompatMock->method('isUser')->willReturn(true);
        $nukeCompatMock->expects($this->once())->method('loginBox');

        $teamIdentityRepoStub = self::createStub(TeamIdentityRepositoryInterface::class);
        $teamIdentityRepoStub->method('getUserByUsername')->willReturn(null);

        $controller = $this->buildController(
            teamIdentityRepo: $teamIdentityRepoStub,
            nukeCompat: $nukeCompatMock,
        );

        $controller->handleWaiverRequest('gm', 'view');
    }
}
