<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\TeamDetailController;
use Api\Response\JsonResponder;
use Tests\WideUnit\WideUnitTestCase;

class TeamDetailControllerTest extends WideUnitTestCase
{
    private const TEAM_UUID = 'team-uuid-abc';

    /**
     * @return array<string, mixed>
     */
    private function teamRow(): array
    {
        return [
            'teamid' => 1,
            'uuid' => self::TEAM_UUID,
            'team_city' => 'Boston',
            'team_name' => 'Celtics',
            'owner_name' => 'TestOwner',
            'arena' => 'Test Arena',
            'conference' => 'Eastern',
            'division' => 'Atlantic',
            'discord_id' => 12345,
            'league_record' => '50-32',
            'conference_record' => '30-20',
            'division_record' => '12-4',
            'home_wins' => 30,
            'home_losses' => 11,
            'away_wins' => 20,
            'away_losses' => 21,
            'win_percentage' => 0.61,
            'conference_games_back' => '5.0',
            'division_games_back' => '2.0',
            'games_remaining' => 0,
        ];
    }

    public function testHandleReturnsTeamDataForValidUuid(): void
    {
        $this->mockDb->setMockData([$this->teamRow()]);

        $controller = new TeamDetailController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                $this->callback(function (array $data): bool {
                    return $data['uuid'] === self::TEAM_UUID
                        && $data['city'] === 'Boston'
                        && $data['name'] === 'Celtics'
                        && $data['full_name'] === 'Boston Celtics'
                        && $data['owner'] === 'TestOwner'
                        && $data['record']['league'] === '50-32'
                        && $data['record']['home'] === '30-11'
                        && $data['standings']['win_percentage'] === 0.61;
                }),
                $this->isArray(),
                200,
                $this->callback(function (array $headers): bool {
                    return isset($headers['ETag'])
                        && $headers['Cache-Control'] === 'public, max-age=60';
                })
            );

        $controller->handle(['uuid' => self::TEAM_UUID], [], $responder);
    }

    public function testHandleReturns404ForUnknownUuid(): void
    {
        $this->mockDb->setMockData([]);

        $controller = new TeamDetailController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(404, 'not_found', 'Team not found.');

        $controller->handle(['uuid' => 'nonexistent'], [], $responder);
    }

    public function testHandleReturns304WhenETagMatches(): void
    {
        $this->mockDb->setMockData([$this->teamRow()]);

        $expectedTag = '"' . md5('team-' . self::TEAM_UUID) . '"';
        $_SERVER['HTTP_IF_NONE_MATCH'] = $expectedTag;

        $controller = new TeamDetailController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('notModified');

        $controller->handle(['uuid' => self::TEAM_UUID], [], $responder);
    }

    public function testHandlePassesCorrectETagInHeaders(): void
    {
        $this->mockDb->setMockData([$this->teamRow()]);

        $expectedTag = '"' . md5('team-' . self::TEAM_UUID) . '"';

        $controller = new TeamDetailController($this->mockDb);
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

        $controller->handle(['uuid' => self::TEAM_UUID], [], $responder);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
        parent::tearDown();
    }
}
