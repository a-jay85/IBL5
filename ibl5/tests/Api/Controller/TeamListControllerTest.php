<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\TeamListController;
use Api\Response\JsonResponder;
use Tests\WideUnit\WideUnitTestCase;

class TeamListControllerTest extends WideUnitTestCase
{
    private const UPDATED_AT = '2026-01-15 12:00:00';
    private const TEAM_UUID = 'team-uuid-001';

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
            'arena' => 'TD Garden',
            'conference' => 'Eastern',
            'division' => 'Atlantic',
            'discord_id' => 12345,
            'updated_at' => self::UPDATED_AT,
        ];
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
        parent::tearDown();
    }

    public function testHandleReturnsTeamList(): void
    {
        $this->mockDb->onQuery('SELECT COUNT', [['total' => 1]]);
        $this->mockDb->setMockData([$this->teamRow()]);

        $controller = new TeamListController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                $this->callback(function (array $data): bool {
                    if (count($data) !== 1) {
                        return false;
                    }
                    $first = $data[0];
                    return $first['uuid'] === self::TEAM_UUID
                        && $first['city'] === 'Boston'
                        && $first['name'] === 'Celtics'
                        && $first['full_name'] === 'Boston Celtics'
                        && $first['owner'] === 'TestOwner'
                        && $first['arena'] === 'TD Garden'
                        && $first['conference'] === 'Eastern'
                        && $first['division'] === 'Atlantic';
                }),
                $this->isArray(),
                200,
                $this->isArray()
            );

        $controller->handle([], [], $responder);
    }

    public function testHandleReturns304WhenETagMatches(): void
    {
        $row = $this->teamRow();
        $this->mockDb->onQuery('SELECT COUNT', [['total' => 1]]);
        $this->mockDb->setMockData([$row]);

        $expectedTag = '"' . md5($row['updated_at']) . '"';
        $_SERVER['HTTP_IF_NONE_MATCH'] = $expectedTag;

        $controller = new TeamListController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('notModified');

        $controller->handle([], [], $responder);
    }

    public function testHandleDefaultSortIsTeamName(): void
    {
        $this->mockDb->onQuery('SELECT COUNT', [['total' => 0]]);
        $this->mockDb->setMockData([]);

        $controller = new TeamListController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                $this->isArray(),
                $this->callback(function (array $meta): bool {
                    return ($meta['sort'] ?? '') === 'team_name'
                        && ($meta['order'] ?? '') === 'asc';
                }),
                200,
                $this->isArray()
            );

        $controller->handle([], [], $responder);
    }
}
