<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\GameListController;
use Api\Response\JsonResponder;
use Tests\Integration\IntegrationTestCase;

class GameListControllerTest extends IntegrationTestCase
{
    public function testHandleCallsResponderWithGameData(): void
    {
        $this->mockDb->setMockData([
            [
                'game_uuid' => 'game-uuid-1',
                'schedule_id' => 15,
                'season_year' => 2007,
                'game_date' => '2007-01-15',
                'box_score_id' => 545,
                'game_status' => 'completed',
                'game_of_that_day' => 3,
                'visitor_uuid' => 'visitor-uuid-1',
                'visitor_team_id' => 1,
                'visitor_city' => 'Boston',
                'visitor_name' => 'Celtics',
                'visitor_full_name' => 'Boston Celtics',
                'visitor_score' => 111,
                'home_uuid' => 'home-uuid-1',
                'home_team_id' => 13,
                'home_city' => 'Utah',
                'home_name' => 'Jazz',
                'home_full_name' => 'Utah Jazz',
                'home_score' => 142,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-15 12:00:00',
                'total' => 1, // Mock COUNT(*) result reuses same data
            ],
        ]);

        $controller = new GameListController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                $this->callback(function (array $data): bool {
                    if (count($data) !== 1) {
                        return false;
                    }
                    $first = $data[0];
                    return $first['uuid'] === 'game-uuid-1'
                        && $first['season'] === 2007
                        && $first['visitor']['name'] === 'Celtics'
                        && $first['home']['name'] === 'Jazz';
                }),
                $this->isArray(),
                200,
                $this->isArray()
            );

        $controller->handle([], [], $responder);
    }

    public function testHandlePassesFiltersToRepository(): void
    {
        $this->mockDb->setMockData([]);

        $controller = new GameListController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success');

        $controller->handle([], ['season' => '2007', 'status' => 'completed'], $responder);

        $this->assertQueryExecuted('season_year');
        $this->assertQueryExecuted('game_status');
    }

    public function testHandleDefaultSortIsGameDateDesc(): void
    {
        $this->mockDb->setMockData([]);

        $controller = new GameListController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                $this->anything(),
                $this->callback(function (array $meta): bool {
                    return ($meta['sort'] ?? '') === 'game_date'
                        && ($meta['order'] ?? '') === 'desc';
                }),
                200,
                $this->isArray()
            );

        $controller->handle([], [], $responder);
    }
}
