<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\PlayerStatsController;
use Api\Response\JsonResponder;
use Tests\Integration\IntegrationTestCase;

class PlayerStatsControllerTest extends IntegrationTestCase
{
    public function testHandleReturns404ForUnknownUuid(): void
    {
        $this->mockDb->setMockData([]);

        $controller = new PlayerStatsController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(404, 'not_found', $this->anything());

        $controller->handle(['uuid' => 'nonexistent-uuid'], [], $responder);
    }

    public function testHandleReturnsCareerStatsForValidUuid(): void
    {
        $this->mockDb->setMockData([
            [
                'player_uuid' => 'player-uuid-123',
                'pid' => 201,
                'name' => 'Test Player',
                'career_games' => 500,
                'career_minutes' => 18000,
                'career_points' => 12000,
                'career_rebounds' => 3000,
                'career_assists' => 2500,
                'career_steals' => 800,
                'career_blocks' => 200,
                'ppg_career' => 24.0,
                'rpg_career' => 6.0,
                'apg_career' => 5.0,
                'fg_pct_career' => 0.480,
                'ft_pct_career' => 0.850,
                'three_pt_pct_career' => 0.370,
                'playoff_minutes' => 2000,
                'draft_year' => 2010,
                'draft_round' => 1,
                'draft_pick' => 5,
                'drafted_by_team' => 'Heat',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2026-01-15 12:00:00',
            ],
        ]);

        $controller = new PlayerStatsController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                $this->callback(function (array $data): bool {
                    return $data['uuid'] === 'player-uuid-123'
                        && $data['career_totals']['games'] === 500
                        && $data['career_averages']['points_per_game'] === 24.0
                        && $data['draft']['year'] === 2010;
                }),
                $this->isArray(),
                200,
                $this->isArray()
            );

        $controller->handle(['uuid' => 'player-uuid-123'], [], $responder);
    }
}
