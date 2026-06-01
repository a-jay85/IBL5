<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\PlayerListController;
use Api\Response\JsonResponder;
use Tests\WideUnit\WideUnitTestCase;

class PlayerListControllerTest extends WideUnitTestCase
{
    private const PLAYER_UUID = 'player-uuid-001';
    private const TEAM_UUID = 'team-uuid-001';
    private const UPDATED_AT = '2026-01-15 12:00:00';

    /**
     * @return array<string, mixed>
     */
    private function playerRow(): array
    {
        return [
            'player_uuid' => self::PLAYER_UUID,
            'pid' => 1001,
            'name' => 'LeBron James',
            'nickname' => null,
            'position' => 'SF',
            'age' => 38,
            'htft' => 6,
            'htin' => 9,
            'dc_can_play_in_game' => 1,
            'retired' => 0,
            'experience' => 20,
            'bird_rights' => 1,
            'teamid' => 5,
            'team_uuid' => self::TEAM_UUID,
            'team_city' => 'Los Angeles',
            'team_name' => 'Lakers',
            'full_team_name' => 'Los Angeles Lakers',
            'owner_name' => 'TestOwner',
            'contract_year' => 1,
            'current_salary' => 45000000,
            'year1_salary' => 45000000,
            'year2_salary' => 47000000,
            'year3_salary' => 0,
            'year4_salary' => 0,
            'year5_salary' => 0,
            'year6_salary' => 0,
            'games_played' => 55,
            'minutes_played' => 1800,
            'field_goals_made' => 400,
            'field_goals_attempted' => 800,
            'free_throws_made' => 200,
            'free_throws_attempted' => 250,
            'three_pointers_made' => 80,
            'three_pointers_attempted' => 200,
            'offensive_rebounds' => 60,
            'defensive_rebounds' => 400,
            'assists' => 600,
            'steals' => 90,
            'turnovers' => 150,
            'blocks' => 30,
            'personal_fouls' => 100,
            'points_per_game' => 27.5,
            'fg_percentage' => 0.5,
            'ft_percentage' => 0.8,
            'three_pt_percentage' => 0.4,
            'updated_at' => self::UPDATED_AT,
        ];
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
        parent::tearDown();
    }

    public function testHandleReturnsPlayerList(): void
    {
        $this->mockDb->onQuery('SELECT COUNT', [['total' => 1]]);
        $this->mockDb->setMockData([$this->playerRow()]);

        $controller = new PlayerListController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                self::callback(function (array $data): bool {
                    if (count($data) !== 1) {
                        return false;
                    }
                    $first = $data[0];
                    return $first['uuid'] === self::PLAYER_UUID
                        && $first['pid'] === 1001
                        && $first['name'] === 'LeBron James'
                        && $first['position'] === 'SF'
                        && $first['age'] === 38
                        && $first['height'] === '6-9'
                        && $first['experience'] === 20
                        && $first['team']['uuid'] === self::TEAM_UUID
                        && $first['team']['city'] === 'Los Angeles'
                        && $first['team']['name'] === 'Lakers'
                        && $first['contract']['current_salary'] === 45000000
                        && $first['stats']['games_played'] === 55
                        && $first['stats']['points_per_game'] === 27.5;
                }),
                self::isArray(),
                200,
                self::isArray()
            );

        $controller->handle([], [], $responder);
    }

    public function testHandleAppliesPositionFilter(): void
    {
        $this->mockDb->onQuery('SELECT COUNT', [['total' => 0]]);
        $this->mockDb->setMockData([]);

        $controller = new PlayerListController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success');

        $controller->handle([], ['position' => 'SF'], $responder);

        $this->assertQueryExecuted('position =');
    }

    public function testHandleAppliesTeamFilter(): void
    {
        $this->mockDb->onQuery('SELECT COUNT', [['total' => 0]]);
        $this->mockDb->setMockData([]);

        $controller = new PlayerListController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success');

        $controller->handle([], ['team' => self::TEAM_UUID], $responder);

        $this->assertQueryExecuted('team_uuid =');
    }

    public function testHandleAppliesSearchFilter(): void
    {
        $this->mockDb->onQuery('SELECT COUNT', [['total' => 0]]);
        $this->mockDb->setMockData([]);

        $controller = new PlayerListController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success');

        $controller->handle([], ['search' => 'LeBron'], $responder);

        $this->assertQueryExecuted('LIKE');
    }

    public function testHandleReturns304WhenETagMatches(): void
    {
        $row = $this->playerRow();
        $this->mockDb->onQuery('SELECT COUNT', [['total' => 1]]);
        $this->mockDb->setMockData([$row]);

        $expectedTag = '"' . md5($row['updated_at']) . '"';
        $_SERVER['HTTP_IF_NONE_MATCH'] = $expectedTag;

        $controller = new PlayerListController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('notModified');

        $controller->handle([], [], $responder);
    }
}
