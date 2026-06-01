<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\PlayerDetailController;
use Api\Response\JsonResponder;
use Tests\WideUnit\WideUnitTestCase;

class PlayerDetailControllerTest extends WideUnitTestCase
{
    private const PLAYER_UUID = 'player-uuid-def456';
    private const UPDATED_AT = '2026-04-10 08:30:00';

    /**
     * @return array<string, mixed>
     */
    private function playerRow(): array
    {
        return [
            'player_uuid' => self::PLAYER_UUID,
            'pid' => 42,
            'name' => 'John Smith',
            'nickname' => null,
            'position' => 'PG',
            'age' => 28,
            'htft' => 6,
            'htin' => 6,
            'dc_can_play_in_game' => 1,
            'retired' => 0,
            'experience' => 5,
            'bird_rights' => 1,
            'teamid' => 3,
            'team_uuid' => 'team-uuid-xyz',
            'team_city' => 'Chicago',
            'team_name' => 'Bulls',
            'full_team_name' => 'Chicago Bulls',
            'owner_name' => 'TestOwner',
            'contract_year' => 2,
            'current_salary' => 5000000,
            'year1_salary' => 5000000,
            'year2_salary' => 5500000,
            'year3_salary' => 0,
            'year4_salary' => 0,
            'year5_salary' => 0,
            'year6_salary' => 0,
            'games_played' => 60,
            'minutes_played' => 1800,
            'field_goals_made' => 400,
            'field_goals_attempted' => 850,
            'free_throws_made' => 150,
            'free_throws_attempted' => 180,
            'three_pointers_made' => 80,
            'three_pointers_attempted' => 200,
            'offensive_rebounds' => 30,
            'defensive_rebounds' => 120,
            'assists' => 350,
            'steals' => 90,
            'turnovers' => 110,
            'blocks' => 20,
            'personal_fouls' => 140,
            'points_per_game' => 18.5,
            'fg_percentage' => 0.471,
            'ft_percentage' => 0.833,
            'three_pt_percentage' => 0.400,
            'updated_at' => self::UPDATED_AT,
        ];
    }

    public function testHandleReturnsPlayerDataForValidUuid(): void
    {
        $this->mockDb->setMockData([$this->playerRow()]);

        $controller = new PlayerDetailController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                $this->callback(function (array $data): bool {
                    return $data['uuid'] === self::PLAYER_UUID
                        && $data['pid'] === 42
                        && $data['name'] === 'John Smith'
                        && $data['position'] === 'PG'
                        && $data['age'] === 28
                        && $data['height'] === '6-6'
                        && $data['experience'] === 5
                        && $data['bird_rights'] === 1
                        && $data['team']['uuid'] === 'team-uuid-xyz'
                        && $data['team']['city'] === 'Chicago'
                        && $data['team']['name'] === 'Bulls'
                        && $data['team']['full_name'] === 'Chicago Bulls'
                        && $data['team']['team_id'] === 3
                        && $data['contract']['current_salary'] === 5000000
                        && $data['contract']['year1'] === 5000000
                        && $data['contract']['year2'] === 5500000
                        && $data['stats']['games_played'] === 60
                        && $data['stats']['minutes_played'] === 1800
                        && $data['stats']['field_goals_made'] === 400
                        && $data['stats']['field_goals_attempted'] === 850
                        && $data['stats']['free_throws_made'] === 150
                        && $data['stats']['free_throws_attempted'] === 180
                        && $data['stats']['three_pointers_made'] === 80
                        && $data['stats']['three_pointers_attempted'] === 200
                        && $data['stats']['offensive_rebounds'] === 30
                        && $data['stats']['defensive_rebounds'] === 120
                        && $data['stats']['assists'] === 350
                        && $data['stats']['steals'] === 90
                        && $data['stats']['turnovers'] === 110
                        && $data['stats']['blocks'] === 20
                        && $data['stats']['personal_fouls'] === 140
                        && $data['stats']['points_per_game'] === 18.5
                        && $data['stats']['fg_percentage'] === 0.471
                        && $data['stats']['ft_percentage'] === 0.833
                        && $data['stats']['three_pt_percentage'] === 0.400;
                }),
                $this->isArray(),
                200,
                $this->callback(function (array $headers): bool {
                    return isset($headers['ETag'])
                        && $headers['Cache-Control'] === 'public, max-age=60';
                })
            );

        $controller->handle(['uuid' => self::PLAYER_UUID], [], $responder);
    }

    public function testHandleReturns404ForUnknownUuid(): void
    {
        $this->mockDb->setMockData([]);

        $controller = new PlayerDetailController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(404, 'not_found', 'Player not found.');

        $controller->handle(['uuid' => 'nonexistent-uuid'], [], $responder);
    }

    public function testHandleReturns304WhenETagMatches(): void
    {
        $this->mockDb->setMockData([$this->playerRow()]);

        $expectedTag = '"' . md5(self::UPDATED_AT) . '"';
        $_SERVER['HTTP_IF_NONE_MATCH'] = $expectedTag;

        $controller = new PlayerDetailController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('notModified');

        $controller->handle(['uuid' => self::PLAYER_UUID], [], $responder);
    }

    public function testHandlePassesCorrectETagInHeaders(): void
    {
        $this->mockDb->setMockData([$this->playerRow()]);

        $expectedTag = '"' . md5(self::UPDATED_AT) . '"';

        $controller = new PlayerDetailController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                $this->isArray(),
                $this->isArray(),
                200,
                $this->callback(function (array $headers) use ($expectedTag): bool {
                    return $headers['ETag'] === $expectedTag;
                })
            );

        $controller->handle(['uuid' => self::PLAYER_UUID], [], $responder);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
        parent::tearDown();
    }
}
