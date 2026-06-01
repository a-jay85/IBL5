<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\InjuriesController;
use Api\Response\JsonResponder;
use Tests\WideUnit\WideUnitTestCase;

class InjuriesControllerTest extends WideUnitTestCase
{
    public function testHandleCallsResponderWithInjuredPlayers(): void
    {
        $this->mockDb->setMockData([
            [
                'player_uuid' => 'player-uuid-1',
                'pid' => 4825,
                'name' => 'Kevin Martin',
                'pos' => 'SG',
                'injured' => 5,
                'teamid' => 26,
                'team_uuid' => 'team-uuid-1',
                'team_city' => 'Sacramento',
                'team_name' => 'Kings',
            ],
        ]);

        $controller = new InjuriesController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                self::callback(function (array $data): bool {
                    if (count($data) !== 1) {
                        return false;
                    }
                    $first = $data[0];
                    return $first['player']['uuid'] === 'player-uuid-1'
                        && $first['player']['name'] === 'Kevin Martin'
                        && $first['injury']['days_remaining'] === 5;
                }),
                self::callback(function (array $meta): bool {
                    return ($meta['total'] ?? 0) === 1;
                }),
                200,
                self::isArray()
            );

        $controller->handle([], [], $responder);
    }

    public function testHandleReturnsEmptyListWhenNoInjuries(): void
    {
        $this->mockDb->setMockData([]);

        $controller = new InjuriesController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                [],
                self::callback(function (array $meta): bool {
                    return ($meta['total'] ?? -1) === 0;
                }),
                200,
                self::isArray()
            );

        $controller->handle([], [], $responder);
    }
}
