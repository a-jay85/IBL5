<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\LeadersController;
use Api\Response\JsonResponder;
use Tests\Integration\IntegrationTestCase;

class LeadersControllerTest extends IntegrationTestCase
{
    public function testHandleCallsResponderWithLeaderData(): void
    {
        $this->mockDb->setMockData([
            [
                'player_uuid' => 'player-uuid-1',
                'pid' => 201,
                'name' => 'Sleepy Floyd',
                'team_uuid' => 'team-uuid-1',
                'team_city' => 'Boston',
                'team_name' => 'Celtics',
                'teamid' => 1,
                'year' => 2007,
                'games' => 82,
                'minutes' => 2238,
                'fgm' => 299,
                'fga' => 705,
                'ftm' => 185,
                'fta' => 212,
                'tgm' => 70,
                'tga' => 215,
                'orb' => 45,
                'reb' => 280,
                'ast' => 498,
                'stl' => 98,
                'blk' => 6,
                'tvr' => 204,
                'pf' => 139,
                'pts' => 853,
                'salary' => 500,
                'team' => 'Celtics',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2026-01-15 12:00:00',
                'total' => 1, // Mock COUNT(*) result reuses same data
            ],
        ]);

        $controller = new LeadersController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                $this->callback(function (array $data): bool {
                    if (count($data) !== 1) {
                        return false;
                    }
                    $first = $data[0];
                    return $first['player']['uuid'] === 'player-uuid-1'
                        && $first['player']['name'] === 'Sleepy Floyd'
                        && $first['team']['name'] === 'Celtics'
                        && $first['season'] === 2007;
                }),
                $this->isArray(),
                200,
                $this->isArray()
            );

        $controller->handle([], [], $responder);
    }

    public function testHandleIncludesCategoryInMeta(): void
    {
        $this->mockDb->setMockData([]);

        $controller = new LeadersController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                $this->anything(),
                $this->callback(function (array $meta): bool {
                    return ($meta['category'] ?? '') === 'rpg';
                }),
                200,
                $this->isArray()
            );

        $controller->handle([], ['category' => 'rpg'], $responder);
    }

    public function testHandleDefaultsCategoryToPpg(): void
    {
        $this->mockDb->setMockData([]);

        $controller = new LeadersController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                $this->anything(),
                $this->callback(function (array $meta): bool {
                    return ($meta['category'] ?? '') === 'ppg';
                }),
                200,
                $this->isArray()
            );

        $controller->handle([], [], $responder);
    }

    public function testHandleNormalizesInvalidCategory(): void
    {
        $this->mockDb->setMockData([]);

        $controller = new LeadersController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                $this->anything(),
                $this->callback(function (array $meta): bool {
                    return ($meta['category'] ?? '') === 'ppg';
                }),
                200,
                $this->isArray()
            );

        $controller->handle([], ['category' => 'invalid'], $responder);
    }
}
