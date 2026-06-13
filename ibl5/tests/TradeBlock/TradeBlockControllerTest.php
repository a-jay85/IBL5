<?php

declare(strict_types=1);

namespace Tests\TradeBlock;

use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use TradeBlock\Contracts\TradeBlockProcessorInterface;
use TradeBlock\Contracts\TradeBlockServiceInterface;
use TradeBlock\Contracts\TradeBlockValidatorInterface;
use TradeBlock\Contracts\TradeBlockViewInterface;
use TradeBlock\TradeBlockController;

/**
 * Structural coverage only: the POST/CSRF/PRG paths call HtmxHelper::redirect(),
 * which is typed `: never` and hard-`exit`s, so they cannot be driven from a unit
 * test without terminating the runner. The CSRF-reject and IDOR behaviors are
 * covered at the API level in tests/e2e/flows/trade-block-submission.spec.ts, and
 * the reconcile/IDOR logic is unit-tested in TradeBlockProcessorTest.
 */
class TradeBlockControllerTest extends TestCase
{
    private TradeBlockController $controller;

    protected function setUp(): void
    {
        $this->controller = new TradeBlockController(
            self::createStub(TradeBlockServiceInterface::class),
            self::createStub(TradeBlockProcessorInterface::class),
            self::createStub(TradeBlockViewInterface::class),
            self::createStub(TradeBlockValidatorInterface::class),
            self::createStub(TeamIdentityRepositoryInterface::class),
            self::createStub(\Utilities\NukeCompat::class),
        );
    }

    public function testControllerConstructsWithAllDependencies(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        self::assertTrue($reflection->hasMethod('handleRequest'));
    }

    public function testUnauthenticatedUserGetsLoginBox(): void
    {
        $nukeCompat = $this->createMock(\Utilities\NukeCompat::class);
        $nukeCompat->method('isUser')->willReturn(false);
        $nukeCompat->expects(self::once())->method('loginBox');

        $serviceMock = $this->createMock(TradeBlockServiceInterface::class);
        $serviceMock->expects(self::never())->method('getBrowseData');

        $controller = new TradeBlockController(
            $serviceMock,
            self::createStub(TradeBlockProcessorInterface::class),
            self::createStub(TradeBlockViewInterface::class),
            self::createStub(TradeBlockValidatorInterface::class),
            self::createStub(TeamIdentityRepositoryInterface::class),
            $nukeCompat,
        );

        $controller->handleRequest('not-a-user', 'browse');
    }
}
