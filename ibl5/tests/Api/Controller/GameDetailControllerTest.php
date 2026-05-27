<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\GameDetailController;
use Api\Response\JsonResponder;
use Tests\WideUnit\WideUnitTestCase;

class GameDetailControllerTest extends WideUnitTestCase
{
    private const GAME_UUID = 'game-uuid-abc123';
    private const UPDATED_AT = '2026-03-15 10:00:00';

    private function gameRow(): array
    {
        return [
            'game_uuid' => self::GAME_UUID,
            'season_year' => 2026,
            'game_date' => '2026-03-15',
            'game_status' => 'completed',
            'box_score_id' => 789,
            'game_of_that_day' => 2,
            'visitor_uuid' => 'visitor-team-uuid',
            'visitor_city' => 'Boston',
            'visitor_name' => 'Celtics',
            'visitor_full_name' => 'Boston Celtics',
            'visitor_score' => 108,
            'visitor_team_id' => 1,
            'home_uuid' => 'home-team-uuid',
            'home_city' => 'Miami',
            'home_name' => 'Heat',
            'home_full_name' => 'Miami Heat',
            'home_score' => 115,
            'home_team_id' => 14,
            'updated_at' => self::UPDATED_AT,
            'created_at' => '2026-01-01 00:00:00',
        ];
    }

    public function testHandleReturnsGameDataForValidUuid(): void
    {
        $this->mockDb->setMockData([$this->gameRow()]);

        $controller = new GameDetailController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                $this->callback(function (array $data): bool {
                    return $data['uuid'] === self::GAME_UUID
                        && $data['season'] === 2026
                        && $data['date'] === '2026-03-15'
                        && $data['status'] === 'completed'
                        && $data['box_score_id'] === 789
                        && $data['game_of_that_day'] === 2
                        && $data['visitor']['uuid'] === 'visitor-team-uuid'
                        && $data['visitor']['city'] === 'Boston'
                        && $data['visitor']['name'] === 'Celtics'
                        && $data['visitor']['full_name'] === 'Boston Celtics'
                        && $data['visitor']['score'] === 108
                        && $data['visitor']['team_id'] === 1
                        && $data['home']['uuid'] === 'home-team-uuid'
                        && $data['home']['city'] === 'Miami'
                        && $data['home']['name'] === 'Heat'
                        && $data['home']['full_name'] === 'Miami Heat'
                        && $data['home']['score'] === 115
                        && $data['home']['team_id'] === 14;
                }),
                $this->isArray(),
                200,
                $this->callback(function (array $headers): bool {
                    return isset($headers['ETag'])
                        && $headers['Cache-Control'] === 'public, max-age=60';
                })
            );

        $controller->handle(['uuid' => self::GAME_UUID], [], $responder);
    }

    public function testHandleReturns404ForUnknownUuid(): void
    {
        $this->mockDb->setMockData([]);

        $controller = new GameDetailController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(404, 'not_found', 'Game not found.');

        $controller->handle(['uuid' => 'nonexistent-uuid'], [], $responder);
    }

    public function testHandleReturns304WhenETagMatches(): void
    {
        $this->mockDb->setMockData([$this->gameRow()]);

        $expectedTag = '"' . md5(self::UPDATED_AT) . '"';
        $_SERVER['HTTP_IF_NONE_MATCH'] = $expectedTag;

        $controller = new GameDetailController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('notModified');

        $controller->handle(['uuid' => self::GAME_UUID], [], $responder);
    }

    public function testHandlePassesCorrectETagInHeaders(): void
    {
        $this->mockDb->setMockData([$this->gameRow()]);

        $expectedTag = '"' . md5(self::UPDATED_AT) . '"';

        $controller = new GameDetailController($this->mockDb);
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

        $controller->handle(['uuid' => self::GAME_UUID], [], $responder);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
        parent::tearDown();
    }
}
