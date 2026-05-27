<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\PlayerHistoryController;
use Api\Response\JsonResponder;
use Tests\WideUnit\WideUnitTestCase;

class PlayerHistoryControllerTest extends WideUnitTestCase
{
    private const PLAYER_UUID = 'player-uuid-hist789';
    private const UPDATED_AT_1 = '2026-05-01 12:00:00';
    private const UPDATED_AT_2 = '2025-05-01 12:00:00';

    private function historyRow(int $year, string $updatedAt): array
    {
        return [
            'player_uuid' => self::PLAYER_UUID,
            'pid' => 77,
            'name' => 'Jane Doe',
            'year' => $year,
            'teamid' => 5,
            'team' => 'Cavaliers',
            'team_uuid' => 'team-uuid-cavs',
            'team_city' => 'Cleveland',
            'team_name' => 'Cavaliers',
            'games' => 70,
            'minutes' => 2100,
            'fgm' => 450,
            'fga' => 900,
            'ftm' => 160,
            'fta' => 190,
            'tgm' => 100,
            'tga' => 250,
            'orb' => 40,
            'reb' => 200,
            'ast' => 120,
            'stl' => 80,
            'blk' => 30,
            'tvr' => 90,
            'pf' => 170,
            'pts' => 1260,
            'salary' => 4500000,
            'updated_at' => $updatedAt,
        ];
    }

    private function twoHistoryRows(): array
    {
        return [
            $this->historyRow(2026, self::UPDATED_AT_1),
            $this->historyRow(2025, self::UPDATED_AT_2),
        ];
    }

    public function testHandleReturnsSeasonHistoryForValidUuid(): void
    {
        $this->mockDb->setMockData($this->twoHistoryRows());

        $controller = new PlayerHistoryController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                $this->callback(function (array $data): bool {
                    if (count($data) !== 2) {
                        return false;
                    }
                    $first = $data[0];
                    return $first['year'] === 2026
                        && $first['pid'] === 77
                        && $first['player_name'] === 'Jane Doe'
                        && $first['team']['uuid'] === 'team-uuid-cavs'
                        && $first['team']['city'] === 'Cleveland'
                        && $first['team']['name'] === 'Cavaliers'
                        && $first['team']['team_id'] === 5
                        && $first['games'] === 70
                        && $first['minutes'] === 2100
                        && isset($first['stats'])
                        && $first['stats']['fg_made'] === 450
                        && $first['stats']['fg_attempted'] === 900
                        && $first['stats']['ft_made'] === 160
                        && $first['stats']['ft_attempted'] === 190
                        && $first['stats']['three_pt_made'] === 100
                        && $first['stats']['three_pt_attempted'] === 250
                        && $first['stats']['offensive_rebounds'] === 40
                        && $first['stats']['rebounds'] === 200
                        && $first['stats']['assists'] === 120
                        && $first['stats']['steals'] === 80
                        && $first['stats']['blocks'] === 30
                        && $first['stats']['turnovers'] === 90
                        && $first['stats']['personal_fouls'] === 170
                        && $first['salary'] === 4500000;
                }),
                $this->callback(function (array $meta): bool {
                    return $meta['total'] === 2;
                }),
                200,
                $this->callback(function (array $headers): bool {
                    return isset($headers['ETag'])
                        && $headers['Cache-Control'] === 'public, max-age=60';
                })
            );

        $controller->handle(['uuid' => self::PLAYER_UUID], [], $responder);
    }

    public function testHandleReturns404WhenNoHistory(): void
    {
        $this->mockDb->setMockData([]);

        $controller = new PlayerHistoryController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(404, 'not_found', 'Player not found or has no history.');

        $controller->handle(['uuid' => 'nonexistent-uuid'], [], $responder);
    }

    public function testHandleReturns304WhenETagMatches(): void
    {
        $rows = $this->twoHistoryRows();
        $this->mockDb->setMockData($rows);

        $concatenated = self::UPDATED_AT_1 . self::UPDATED_AT_2;
        $expectedTag = '"' . md5($concatenated) . '"';
        $_SERVER['HTTP_IF_NONE_MATCH'] = $expectedTag;

        $controller = new PlayerHistoryController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('notModified');

        $controller->handle(['uuid' => self::PLAYER_UUID], [], $responder);
    }

    public function testHandlePassesCorrectTotalInMeta(): void
    {
        $this->mockDb->setMockData($this->twoHistoryRows());

        $controller = new PlayerHistoryController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                $this->isArray(),
                $this->callback(function (array $meta): bool {
                    return $meta['total'] === 2;
                }),
                200,
                $this->isArray()
            );

        $controller->handle(['uuid' => self::PLAYER_UUID], [], $responder);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
        parent::tearDown();
    }
}
