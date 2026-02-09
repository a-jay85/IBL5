<?php

declare(strict_types=1);

namespace Tests\Api\Transformer;

use Api\Transformer\PlayerTransformer;
use PHPUnit\Framework\TestCase;

class PlayerTransformerTest extends TestCase
{
    private PlayerTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new PlayerTransformer();
    }

    /**
     * @return array<string, mixed>
     */
    private function makePlayerRow(): array
    {
        return [
            'player_uuid' => 'abc-123-def',
            'pid' => 123,
            'name' => 'LeBron James',
            'position' => 'SF',
            'age' => 39,
            'htft' => 6,
            'htin' => 9,
            'experience' => 20,
            'bird_rights' => 3,
            'teamid' => 5,
            'team_uuid' => 'team-uuid-456',
            'team_city' => 'Los Angeles',
            'team_name' => 'Lakers',
            'full_team_name' => 'Los Angeles Lakers',
            'current_salary' => 4700,
            'year1_salary' => 5000,
            'year2_salary' => 0,
            'games_played' => 50,
            'minutes_played' => 1750,
            'field_goals_made' => 400,
            'field_goals_attempted' => 800,
            'free_throws_made' => 200,
            'free_throws_attempted' => 250,
            'three_pointers_made' => 80,
            'three_pointers_attempted' => 220,
            'offensive_rebounds' => 30,
            'defensive_rebounds' => 300,
            'assists' => 350,
            'steals' => 60,
            'turnovers' => 150,
            'blocks' => 40,
            'personal_fouls' => 80,
            'fg_percentage' => 0.500,
            'ft_percentage' => 0.800,
            'three_pt_percentage' => 0.364,
            'points_per_game' => 21.6,
            'updated_at' => '2026-01-15 12:00:00',
        ];
    }

    public function testTransformExposesUuidNotInternalId(): void
    {
        $row = $this->makePlayerRow();
        $result = $this->transformer->transform($row);

        $this->assertSame('abc-123-def', $result['uuid']);
        $this->assertSame(123, $result['pid']);
        $this->assertArrayNotHasKey('tid', $result);
        $this->assertArrayNotHasKey('teamid', $result);
    }

    public function testTransformIncludesBasicFields(): void
    {
        $row = $this->makePlayerRow();
        $result = $this->transformer->transform($row);

        $this->assertSame('LeBron James', $result['name']);
        $this->assertSame('SF', $result['position']);
        $this->assertSame(39, $result['age']);
        $this->assertSame('6-9', $result['height']);
        $this->assertSame(20, $result['experience']);
    }

    public function testTransformIncludesTeamAsNestedObject(): void
    {
        $row = $this->makePlayerRow();
        $result = $this->transformer->transform($row);

        $this->assertIsArray($result['team']);
        $this->assertSame('team-uuid-456', $result['team']['uuid']);
        $this->assertSame('Los Angeles', $result['team']['city']);
        $this->assertSame('Lakers', $result['team']['name']);
        $this->assertSame('Los Angeles Lakers', $result['team']['full_name']);
        $this->assertSame(5, $result['team']['team_id']);
    }

    public function testTransformTeamIsNullWhenNoTeamUuid(): void
    {
        $row = $this->makePlayerRow();
        $row['team_uuid'] = null;
        $result = $this->transformer->transform($row);

        $this->assertNull($result['team']);
    }

    public function testTransformIncludesContract(): void
    {
        $row = $this->makePlayerRow();
        $result = $this->transformer->transform($row);

        $this->assertSame(4700, $result['contract']['current_salary']);
        $this->assertSame(5000, $result['contract']['year1']);
        $this->assertSame(0, $result['contract']['year2']);
    }

    public function testTransformIncludesSummaryStats(): void
    {
        $row = $this->makePlayerRow();
        $result = $this->transformer->transform($row);

        $this->assertSame(50, $result['stats']['games_played']);
        $this->assertSame(21.6, $result['stats']['points_per_game']);
        $this->assertSame(0.500, $result['stats']['fg_percentage']);
    }

    public function testTransformHandlesNullStats(): void
    {
        $row = $this->makePlayerRow();
        $row['points_per_game'] = null;
        $row['fg_percentage'] = null;
        $result = $this->transformer->transform($row);

        $this->assertNull($result['stats']['points_per_game']);
        $this->assertNull($result['stats']['fg_percentage']);
    }

    public function testTransformDetailIncludesFullStats(): void
    {
        $row = $this->makePlayerRow();
        $result = $this->transformer->transformDetail($row);

        $this->assertSame(400, $result['stats']['field_goals_made']);
        $this->assertSame(800, $result['stats']['field_goals_attempted']);
        $this->assertSame(200, $result['stats']['free_throws_made']);
        $this->assertSame(350, $result['stats']['assists']);
        $this->assertSame(60, $result['stats']['steals']);
        $this->assertSame(40, $result['stats']['blocks']);
    }

    public function testTransformDetailIncludesBirdRights(): void
    {
        $row = $this->makePlayerRow();
        $result = $this->transformer->transformDetail($row);

        $this->assertSame(3, $result['bird_rights']);
    }

    public function testFormatHeightProducesCorrectFormat(): void
    {
        $row = $this->makePlayerRow();
        $row['htft'] = 7;
        $row['htin'] = 1;
        $result = $this->transformer->transform($row);

        $this->assertSame('7-1', $result['height']);
    }

    public function testFormatHeightReturnsEmptyStringWhenZero(): void
    {
        $row = $this->makePlayerRow();
        $row['htft'] = 0;
        $row['htin'] = 0;
        $result = $this->transformer->transform($row);

        $this->assertSame('', $result['height']);
    }
}
