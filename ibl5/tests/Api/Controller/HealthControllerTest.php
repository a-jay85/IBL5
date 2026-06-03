<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\HealthController;
use Api\Repository\HealthRepository;
use Api\Response\JsonResponder;
use PHPUnit\Framework\TestCase;

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
}
