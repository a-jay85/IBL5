<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\HealthController;
use Api\Repository\HealthRepository;
use Api\Response\JsonResponder;
use PHPUnit\Framework\TestCase;
use Tests\Clock\FixedClock;

class HealthControllerTest extends TestCase
{
    public function testReturnsOkAndHttp200WhenDatabaseReachable(): void
    {
        $healthRepo = self::createStub(HealthRepository::class);
        $healthRepo->method('isReachable')->willReturn(true);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('raw')
            ->with(
                self::callback(static function (array $body): bool {
                    return $body['status'] === 'ok'
                        && $body['db'] === true
                        && is_string($body['checkedAt'])
                        && $body['checkedAt'] !== '';
                }),
                200
            );

        (new HealthController($healthRepo))->handle([], [], $responder);
    }

    public function testReturnsDegradedAndHttp503WhenRepositoryNotReachable(): void
    {
        $healthRepo = self::createStub(HealthRepository::class);
        $healthRepo->method('isReachable')->willReturn(false);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('raw')
            ->with(
                self::callback(static function (array $body): bool {
                    return $body['status'] === 'degraded'
                        && $body['db'] === false
                        && is_string($body['checkedAt']);
                }),
                503
            );

        (new HealthController($healthRepo))->handle([], [], $responder);
    }

    public function testCheckedAtIsIso8601UtcWithinCallWindow(): void
    {
        $healthRepo = self::createStub(HealthRepository::class);
        $healthRepo->method('isReachable')->willReturn(true);

        $captured = null;
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('raw')
            ->with(
                self::callback(static function (array $body) use (&$captured): bool {
                    $captured = $body['checkedAt'];
                    return true;
                }),
                200
            );

        $before = time();
        (new HealthController($healthRepo))->handle([], [], $responder);
        $after = time();

        self::assertIsString($captured);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $captured);
        $ts = strtotime($captured);
        self::assertGreaterThanOrEqual($before, $ts);
        self::assertLessThanOrEqual($after, $ts);
    }

    public function testCheckedAtComesFromInjectedClockWhenHealthy(): void
    {
        $healthRepo = self::createStub(HealthRepository::class);
        $healthRepo->method('isReachable')->willReturn(true);

        $captured = null;
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('raw')
            ->with(
                self::callback(static function (array $body) use (&$captured): bool {
                    $captured = $body;
                    return true;
                }),
                200
            );

        (new HealthController($healthRepo, new FixedClock(1791549296)))->handle([], [], $responder);

        self::assertIsArray($captured);
        self::assertSame('2026-10-09T12:34:56Z', $captured['checkedAt']);
        self::assertSame('ok', $captured['status']);
    }

    public function testCheckedAtComesFromInjectedClockWhenDegraded(): void
    {
        $healthRepo = self::createStub(HealthRepository::class);
        $healthRepo->method('isReachable')->willReturn(false);

        $captured = null;
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('raw')
            ->with(
                self::callback(static function (array $body) use (&$captured): bool {
                    $captured = $body;
                    return true;
                }),
                503
            );

        (new HealthController($healthRepo, new FixedClock(1791549296)))->handle([], [], $responder);

        self::assertIsArray($captured);
        self::assertSame('2026-10-09T12:34:56Z', $captured['checkedAt']);
        self::assertSame('degraded', $captured['status']);
    }
}
