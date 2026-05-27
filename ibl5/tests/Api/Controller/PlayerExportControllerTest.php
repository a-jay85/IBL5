<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\PlayerExportController;
use Api\Response\JsonResponder;
use Tests\WideUnit\WideUnitTestCase;

class PlayerExportControllerTest extends WideUnitTestCase
{
    private function playerRow(int $pid, string $name, string $position): array
    {
        return [
            'player_uuid' => 'uuid-' . $pid,
            'pid' => $pid,
            'name' => $name,
            'nickname' => null,
            'position' => $position,
            'age' => 25,
            'htft' => 6,
            'htin' => 2,
            'dc_can_play_in_game' => 1,
            'retired' => 0,
            'experience' => 5,
            'bird_rights' => 1,
            'teamid' => 14,
            'team_uuid' => 'team-uuid-14',
            'team_city' => 'Miami',
            'team_name' => 'Heat',
            'full_team_name' => 'Miami Heat',
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
            'minutes_played' => 2100,
            'field_goals_made' => 480,
            'field_goals_attempted' => 1000,
            'free_throws_made' => 150,
            'free_throws_attempted' => 180,
            'three_pointers_made' => 80,
            'three_pointers_attempted' => 220,
            'offensive_rebounds' => 50,
            'defensive_rebounds' => 200,
            'assists' => 300,
            'steals' => 90,
            'turnovers' => 110,
            'blocks' => 30,
            'personal_fouls' => 120,
            'points_per_game' => 18.5,
            'fg_percentage' => 0.480,
            'ft_percentage' => 0.833,
            'three_pt_percentage' => 0.364,
            'updated_at' => '2026-03-01 00:00:00',
        ];
    }

    public function testHandleProducesCSVOutput(): void
    {
        $this->mockDb->setMockData([
            $this->playerRow(101, 'Alpha Player', 'PG'),
        ]);

        $controller = new PlayerExportController($this->mockDb);
        $responder = $this->createStub(JsonResponder::class);

        $output = $this->captureOutput(function () use ($controller, $responder): void {
            $controller->handle([], [], $responder);
        });

        // Strip UTF-8 BOM if present
        $output = ltrim($output, "\xEF\xBB\xBF");

        $this->assertNotEmpty($output, 'CSV output should not be empty');
        $this->assertStringContainsString(',', $output);
    }

    public function testHandleIncludesHeaderRow(): void
    {
        $this->mockDb->setMockData([
            $this->playerRow(101, 'Alpha Player', 'PG'),
        ]);

        $controller = new PlayerExportController($this->mockDb);
        $responder = $this->createStub(JsonResponder::class);

        $output = $this->captureOutput(function () use ($controller, $responder): void {
            $controller->handle([], [], $responder);
        });

        $output = ltrim($output, "\xEF\xBB\xBF");
        $firstLine = strtok($output, "\n") ?: '';

        // Verify key column headers from PlayerExportTransformer::HEADERS
        $this->assertStringContainsString('PID', $firstLine);
        $this->assertStringContainsString('Name', $firstLine);
        $this->assertStringContainsString('Nickname', $firstLine);
        $this->assertStringContainsString('Position', $firstLine);
        $this->assertStringContainsString('GP', $firstLine);
        $this->assertStringContainsString('PPG', $firstLine);
    }

    public function testHandleTransformsPlayerData(): void
    {
        $this->mockDb->setMockData([
            $this->playerRow(201, 'Bravo Player', 'SG'),
            $this->playerRow(202, 'Charlie Player', 'SF'),
        ]);

        $controller = new PlayerExportController($this->mockDb);
        $responder = $this->createStub(JsonResponder::class);

        $output = $this->captureOutput(function () use ($controller, $responder): void {
            $controller->handle([], [], $responder);
        });

        $output = ltrim($output, "\xEF\xBB\xBF");

        // Both players' names must appear
        $this->assertStringContainsString('Bravo Player', $output);
        $this->assertStringContainsString('Charlie Player', $output);

        // PIDs must appear
        $this->assertStringContainsString('201', $output);
        $this->assertStringContainsString('202', $output);

        // Positions must appear
        $this->assertStringContainsString('SG', $output);
        $this->assertStringContainsString('SF', $output);

        // Output must have at least 3 lines: header + 2 player rows
        $lines = array_filter(explode("\n", trim($output)));
        $this->assertGreaterThanOrEqual(3, count($lines));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
