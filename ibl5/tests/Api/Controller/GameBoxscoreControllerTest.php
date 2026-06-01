<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\GameBoxscoreController;
use Api\Response\JsonResponder;
use Tests\WideUnit\WideUnitTestCase;

class GameBoxscoreControllerTest extends WideUnitTestCase
{
    private const GAME_UUID = 'game-boxscore-uuid-abc';
    private const UPDATED_AT = '2026-03-20 08:00:00';
    private const VISITOR_TEAM_ID = 1;
    private const HOME_TEAM_ID = 14;
    private const GAME_DATE = '2026-03-20';

    /**
     * @return array<string, mixed>
     */
    private function gameRow(string $status = 'completed'): array
    {
        return [
            'game_uuid' => self::GAME_UUID,
            'season_year' => 2026,
            'game_date' => self::GAME_DATE,
            'game_status' => $status,
            'box_score_id' => 555,
            'game_of_that_day' => 1,
            'visitor_uuid' => 'visitor-team-uuid',
            'visitor_city' => 'Boston',
            'visitor_name' => 'Celtics',
            'visitor_full_name' => 'Boston Celtics',
            'visitor_score' => 112,
            'visitor_team_id' => self::VISITOR_TEAM_ID,
            'home_uuid' => 'home-team-uuid',
            'home_city' => 'Miami',
            'home_name' => 'Heat',
            'home_full_name' => 'Miami Heat',
            'home_score' => 108,
            'home_team_id' => self::HOME_TEAM_ID,
            'updated_at' => self::UPDATED_AT,
            'created_at' => '2026-01-01 00:00:00',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function teamBoxscoreRow(string $name): array
    {
        return [
            'name' => $name,
            'visitor_q1_points' => 28,
            'visitor_q2_points' => 30,
            'visitor_q3_points' => 27,
            'visitor_q4_points' => 27,
            'visitor_ot_points' => 0,
            'home_q1_points' => 25,
            'home_q2_points' => 28,
            'home_q3_points' => 29,
            'home_q4_points' => 26,
            'home_ot_points' => 0,
            'game_min' => 240,
            'game_2gm' => 35,
            'game_2ga' => 72,
            'game_ftm' => 18,
            'game_fta' => 22,
            'game_3gm' => 14,
            'game_3ga' => 35,
            'game_orb' => 10,
            'game_drb' => 30,
            'game_ast' => 24,
            'game_stl' => 8,
            'game_tov' => 12,
            'game_blk' => 5,
            'game_pf' => 20,
            'attendance' => 19600,
            'capacity' => 20000,
            'visitor_wins' => 38,
            'visitor_losses' => 20,
            'home_wins' => 36,
            'home_losses' => 22,
            'calc_points' => 112,
            'calc_rebounds' => 40,
            'calc_fg_made' => 49,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function playerBoxscoreRow(
        string $uuid,
        string $name,
        string $pos,
        int $teamId,
        int $points = 20
    ): array {
        return [
            'player_uuid' => $uuid,
            'name' => $name,
            'pos' => $pos,
            'game_min' => 36,
            'game_2gm' => 7,
            'game_2ga' => 14,
            'game_ftm' => 4,
            'game_fta' => 5,
            'game_3gm' => 2,
            'game_3ga' => 6,
            'game_orb' => 2,
            'game_drb' => 5,
            'game_ast' => 4,
            'game_stl' => 2,
            'game_tov' => 3,
            'game_blk' => 1,
            'game_pf' => 3,
            'calc_points' => $points,
            'calc_rebounds' => 7,
            'calc_fg_made' => 9,
            'player_tid' => $teamId,
        ];
    }

    private function seedSuccessMocks(): void
    {
        // getGameByUuid uses vw_schedule_upcoming
        $this->mockDb->onQuery('vw_schedule_upcoming', [$this->gameRow()]);

        // getBoxscoreTeams uses ibl_box_scores_teams
        $this->mockDb->onQuery('ibl_box_scores_teams', [
            $this->teamBoxscoreRow('Celtics'),
            $this->teamBoxscoreRow('Heat'),
        ]);

        // getBoxscorePlayers uses ibl_box_scores (without _teams)
        // The query is: FROM ibl_box_scores b LEFT JOIN ibl_plr ...
        $this->mockDb->onQuery('ibl_box_scores b', [
            $this->playerBoxscoreRow('visitor-p1-uuid', 'Visitor Player One', 'PG', self::VISITOR_TEAM_ID, 28),
            $this->playerBoxscoreRow('visitor-p2-uuid', 'Visitor Player Two', 'SG', self::VISITOR_TEAM_ID, 18),
            $this->playerBoxscoreRow('home-p1-uuid', 'Home Player One', 'C', self::HOME_TEAM_ID, 22),
            $this->playerBoxscoreRow('home-p2-uuid', 'Home Player Two', 'PF', self::HOME_TEAM_ID, 15),
        ]);
    }

    public function testHandleReturnsBoxscoreForCompletedGame(): void
    {
        $this->seedSuccessMocks();

        $controller = new GameBoxscoreController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                $this->callback(function (array $data): bool {
                    return isset($data['game'], $data['visitor'], $data['home'])
                        && $data['game']['uuid'] === self::GAME_UUID
                        && $data['game']['status'] === 'completed'
                        && isset($data['visitor']['team_stats'], $data['visitor']['players'])
                        && isset($data['home']['team_stats'], $data['home']['players'])
                        && count($data['visitor']['players']) === 2
                        && count($data['home']['players']) === 2;
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

    public function testHandleReturns404ForUnknownGame(): void
    {
        $this->mockDb->onQuery('vw_schedule_upcoming', []);

        $controller = new GameBoxscoreController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(404, 'not_found', 'Game not found.');

        $responder->expects($this->never())
            ->method('success');

        $controller->handle(['uuid' => 'nonexistent-uuid'], [], $responder);
    }

    public function testHandleReturns404ForScheduledGame(): void
    {
        $this->mockDb->onQuery('vw_schedule_upcoming', [$this->gameRow('scheduled')]);

        $controller = new GameBoxscoreController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('error')
            ->with(404, 'no_boxscore', 'Box score is not available for scheduled games.');

        $responder->expects($this->never())
            ->method('success');

        $controller->handle(['uuid' => self::GAME_UUID], [], $responder);
    }

    public function testHandleReturns304WhenETagMatches(): void
    {
        $this->seedSuccessMocks();

        $expectedTag = '"' . md5(self::UPDATED_AT) . '"';
        $_SERVER['HTTP_IF_NONE_MATCH'] = $expectedTag;

        $controller = new GameBoxscoreController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('notModified');

        $responder->expects($this->never())
            ->method('success');

        $controller->handle(['uuid' => self::GAME_UUID], [], $responder);
    }

    public function testHandleCorrectlyPartitionsPlayersByTeam(): void
    {
        $this->seedSuccessMocks();

        $controller = new GameBoxscoreController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                $this->callback(function (array $data): bool {
                    $visitorPlayers = $data['visitor']['players'] ?? [];
                    $homePlayers = $data['home']['players'] ?? [];

                    if (count($visitorPlayers) !== 2 || count($homePlayers) !== 2) {
                        return false;
                    }

                    // Verify visitor players are correctly identified by name
                    $visitorNames = array_column($visitorPlayers, 'name');
                    $homeNames = array_column($homePlayers, 'name');

                    return in_array('Visitor Player One', $visitorNames, true)
                        && in_array('Visitor Player Two', $visitorNames, true)
                        && in_array('Home Player One', $homeNames, true)
                        && in_array('Home Player Two', $homeNames, true);
                }),
                $this->isArray(),
                200,
                $this->isArray()
            );

        $controller->handle(['uuid' => self::GAME_UUID], [], $responder);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
        parent::tearDown();
    }
}
