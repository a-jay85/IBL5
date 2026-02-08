<?php

declare(strict_types=1);

namespace Tests\Api\Transformer;

use Api\Transformer\GameTransformer;
use PHPUnit\Framework\TestCase;

class GameTransformerTest extends TestCase
{
    private GameTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new GameTransformer();
    }

    /**
     * @return array<string, mixed>
     */
    private function makeGameRow(): array
    {
        return [
            'game_uuid' => 'game-uuid-123',
            'schedule_id' => 15,
            'season_year' => 2007,
            'game_date' => '2007-01-15',
            'box_score_id' => 545,
            'game_status' => 'completed',
            'visitor_uuid' => 'visitor-uuid-456',
            'visitor_team_id' => 1,
            'visitor_city' => 'Boston',
            'visitor_name' => 'Celtics',
            'visitor_full_name' => 'Boston Celtics',
            'visitor_score' => 111,
            'home_uuid' => 'home-uuid-789',
            'home_team_id' => 13,
            'home_city' => 'Utah',
            'home_name' => 'Jazz',
            'home_full_name' => 'Utah Jazz',
            'home_score' => 142,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-15 12:00:00',
        ];
    }

    public function testTransformExposesUuidNotInternalIds(): void
    {
        $row = $this->makeGameRow();
        $result = $this->transformer->transform($row);

        $this->assertSame('game-uuid-123', $result['uuid']);
        $this->assertArrayNotHasKey('schedule_id', $result);
        $this->assertArrayNotHasKey('box_score_id', $result);
        $this->assertArrayNotHasKey('visitor_team_id', $result);
        $this->assertArrayNotHasKey('home_team_id', $result);
    }

    public function testTransformIncludesBasicFields(): void
    {
        $row = $this->makeGameRow();
        $result = $this->transformer->transform($row);

        $this->assertSame(2007, $result['season']);
        $this->assertSame('2007-01-15', $result['date']);
        $this->assertSame('completed', $result['status']);
    }

    public function testTransformIncludesVisitorTeam(): void
    {
        $row = $this->makeGameRow();
        $result = $this->transformer->transform($row);

        $this->assertIsArray($result['visitor']);
        $this->assertSame('visitor-uuid-456', $result['visitor']['uuid']);
        $this->assertSame('Boston', $result['visitor']['city']);
        $this->assertSame('Celtics', $result['visitor']['name']);
        $this->assertSame('Boston Celtics', $result['visitor']['full_name']);
        $this->assertSame(111, $result['visitor']['score']);
    }

    public function testTransformIncludesHomeTeam(): void
    {
        $row = $this->makeGameRow();
        $result = $this->transformer->transform($row);

        $this->assertIsArray($result['home']);
        $this->assertSame('home-uuid-789', $result['home']['uuid']);
        $this->assertSame('Utah', $result['home']['city']);
        $this->assertSame('Jazz', $result['home']['name']);
        $this->assertSame('Utah Jazz', $result['home']['full_name']);
        $this->assertSame(142, $result['home']['score']);
    }

    public function testTransformHandlesNullScores(): void
    {
        $row = $this->makeGameRow();
        $row['visitor_score'] = null;
        $row['home_score'] = null;
        $result = $this->transformer->transform($row);

        $this->assertNull($result['visitor']['score']);
        $this->assertNull($result['home']['score']);
    }

    public function testTransformScheduledGameStatus(): void
    {
        $row = $this->makeGameRow();
        $row['game_status'] = 'scheduled';
        $row['visitor_score'] = null;
        $row['home_score'] = null;
        $result = $this->transformer->transform($row);

        $this->assertSame('scheduled', $result['status']);
    }
}
