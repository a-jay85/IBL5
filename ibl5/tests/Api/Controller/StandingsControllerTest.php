<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\StandingsController;
use Api\Response\JsonResponder;
use Tests\Integration\IntegrationTestCase;

class StandingsControllerTest extends IntegrationTestCase
{
    public function testHandleCallsResponderWithStandingsData(): void
    {
        // Set up mock standings data
        $this->mockDb->setMockData([
            [
                'team_uuid' => 'uuid-1',
                'teamid' => 1,
                'team_city' => 'Chicago',
                'team_name' => 'Bulls',
                'full_team_name' => 'Chicago Bulls',
                'owner_name' => 'TestOwner',
                'league_record' => '40-22',
                'win_percentage' => 0.645,
                'conference' => 'East',
                'conference_record' => '28-12',
                'conference_games_back' => '0.0',
                'division' => 'Central',
                'division_record' => '10-4',
                'division_games_back' => '0.0',
                'home_wins' => 24,
                'home_losses' => 7,
                'away_wins' => 16,
                'away_losses' => 15,
                'home_record' => '24-7',
                'away_record' => '16-15',
                'games_remaining' => 20,
                'conference_wins' => 28,
                'conference_losses' => 12,
                'division_wins' => 10,
                'division_losses' => 4,
                'clinched_conference' => 0,
                'clinched_division' => 1,
                'clinched_playoffs' => 1,
                'conference_magic_number' => 5,
                'division_magic_number' => 0,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-15 12:00:00',
            ],
        ]);

        $controller = new StandingsController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                $this->callback(function (array $data): bool {
                    if (count($data) !== 1) {
                        return false;
                    }
                    $first = $data[0];
                    return $first['team']['uuid'] === 'uuid-1'
                        && $first['team']['name'] === 'Bulls'
                        && $first['conference'] === 'East'
                        && $first['record']['league'] === '40-22'
                        && $first['clinched']['division'] === true;
                }),
                $this->isArray(),
                200,
                $this->isArray()
            );

        $controller->handle([], [], $responder);
    }

    public function testHandlePassesConferenceFilter(): void
    {
        $this->mockDb->setMockData([]);

        $controller = new StandingsController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                [],
                $this->callback(function (array $meta): bool {
                    return ($meta['conference'] ?? null) === 'Eastern';
                }),
                200,
                $this->isArray()
            );

        $controller->handle(['conference' => 'East'], [], $responder);
    }
}
