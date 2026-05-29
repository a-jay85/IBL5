<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\HealthController;
use Api\Response\JsonResponder;
use PHPUnit\Framework\TestCase;

class HealthControllerTest extends TestCase
{
    public function testReturnsOkAndHttp200WhenDatabaseReachable(): void
    {
        $db = $this->createStub(\mysqli::class);
        $db->method('query')->willReturn(true);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('raw')
            ->with(
                $this->callback(static function (array $body): bool {
                    return $body['status'] === 'ok'
                        && $body['db'] === true
                        && is_string($body['checkedAt'])
                        && $body['checkedAt'] !== '';
                }),
                200
            );

        (new HealthController($db))->handle([], [], $responder);
    }

    public function testReturnsDegradedAndHttp503WhenSelectThrows(): void
    {
        $db = $this->createStub(\mysqli::class);
        $db->method('query')->willThrowException(new \mysqli_sql_exception('connection lost'));

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('raw')
            ->with(
                $this->callback(static function (array $body): bool {
                    return $body['status'] === 'degraded'
                        && $body['db'] === false
                        && is_string($body['checkedAt']);
                }),
                503
            );

        (new HealthController($db))->handle([], [], $responder);
    }
}
