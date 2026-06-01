<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\TeamRosterController;
use Api\Response\JsonResponder;
use Tests\WideUnit\WideUnitTestCase;

class TeamRosterControllerTest extends WideUnitTestCase
{
    private const TEAM_UUID = 'team-uuid-abc';
    private const PLAYER_UUID = 'player-uuid-001';
    private const UPDATED_AT = '2026-01-15 12:00:00';

    /**
     * @return array<string, mixed>
     */
    private function playerRow(): array
    {
        return [
            'player_uuid' => self::PLAYER_UUID,
            'pid' => 2001,
            'name' => 'Stephen Curry',
            'nickname' => null,
            'position' => 'PG',
            'age' => 35,
            'htft' => 6,
            'htin' => 2,
            'dc_can_play_in_game' => 1,
            'retired' => 0,
            'experience' => 14,
            'bird_rights' => 1,
            'teamid' => 10,
            'team_uuid' => self::TEAM_UUID,
            'team_city' => 'Golden State',
            'team_name' => 'Warriors',
            'full_team_name' => 'Golden State Warriors',
            'owner_name' => 'TestOwner2',
            'contract_year' => 1,
            'current_salary' => 50000000,
            'year1_salary' => 50000000,
            'year2_salary' => 52000000,
            'year3_salary' => 0,
            'year4_salary' => 0,
            'year5_salary' => 0,
            'year6_salary' => 0,
            'games_played' => 60,
            'minutes_played' => 2000,
            'field_goals_made' => 430,
            'field_goals_attempted' => 900,
            'free_throws_made' => 180,
            'free_throws_attempted' => 200,
            'three_pointers_made' => 200,
            'three_pointers_attempted' => 500,
            'offensive_rebounds' => 30,
            'defensive_rebounds' => 250,
            'assists' => 450,
            'steals' => 95,
            'turnovers' => 200,
            'blocks' => 10,
            'personal_fouls' => 90,
            'points_per_game' => 29.4,
            'fg_percentage' => 0.478,
            'ft_percentage' => 0.9,
            'three_pt_percentage' => 0.4,
            'updated_at' => self::UPDATED_AT,
        ];
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
        parent::tearDown();
    }

    public function testHandleReturnsRosterForValidTeam(): void
    {
        $this->mockDb->onQuery('SELECT COUNT', [['total' => 1]]);
        $this->mockDb->setMockData([$this->playerRow()]);

        $controller = new TeamRosterController($this->mockDb);
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
                        && $first['pid'] === 2001
                        && $first['name'] === 'Stephen Curry'
                        && $first['position'] === 'PG'
                        && $first['height'] === '6-2'
                        && $first['team']['uuid'] === self::TEAM_UUID
                        && $first['team']['name'] === 'Warriors'
                        && $first['contract']['current_salary'] === 50000000
                        && $first['stats']['games_played'] === 60
                        && $first['stats']['points_per_game'] === 29.4;
                }),
                self::isArray(),
                200,
                self::isArray()
            );

        $controller->handle(['uuid' => self::TEAM_UUID], [], $responder);
    }

    public function testHandleReturns404WhenTeamHasNoPlayers(): void
    {
        $this->mockDb->onQuery('SELECT COUNT', [['total' => 0]]);

        $controller = new TeamRosterController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(404, 'not_found', 'Team not found or has no players.');

        $controller->handle(['uuid' => self::TEAM_UUID], [], $responder);
    }

    public function testHandleReturns304WhenETagMatches(): void
    {
        $row = $this->playerRow();
        $this->mockDb->onQuery('SELECT COUNT', [['total' => 1]]);
        $this->mockDb->setMockData([$row]);

        $expectedTag = '"' . md5($row['updated_at']) . '"';
        $_SERVER['HTTP_IF_NONE_MATCH'] = $expectedTag;

        $controller = new TeamRosterController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('notModified');

        $controller->handle(['uuid' => self::TEAM_UUID], [], $responder);
    }
}
