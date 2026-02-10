<?php

declare(strict_types=1);

namespace Tests\Api\Transformer;

use Api\Transformer\LeaderTransformer;
use PHPUnit\Framework\TestCase;

class LeaderTransformerTest extends TestCase
{
    private LeaderTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new LeaderTransformer();
    }

    /**
     * @return array<string, mixed>
     */
    private function makeLeaderRow(): array
    {
        return [
            'player_uuid' => 'player-uuid-123',
            'pid' => 201,
            'name' => 'Sleepy Floyd',
            'teamid' => 1,
            'team_uuid' => 'team-uuid-456',
            'team_city' => 'Boston',
            'team_name' => 'Celtics',
            'year' => 2007,
            'games' => 82,
            'minutes' => 2238,
            'fgm' => 299,
            'fga' => 705,
            'ftm' => 185,
            'fta' => 212,
            'tgm' => 70,
            'tga' => 215,
            'orb' => 45,
            'reb' => 280,
            'ast' => 498,
            'stl' => 98,
            'blk' => 6,
            'tvr' => 204,
            'pf' => 139,
            'pts' => 853,
        ];
    }

    public function testTransformIncludesPlayerInfo(): void
    {
        $row = $this->makeLeaderRow();
        $result = $this->transformer->transform($row);

        $this->assertSame('player-uuid-123', $result['player']['uuid']);
        $this->assertSame('Sleepy Floyd', $result['player']['name']);
    }

    public function testTransformIncludesTeamInfo(): void
    {
        $row = $this->makeLeaderRow();
        $result = $this->transformer->transform($row);

        $this->assertSame('team-uuid-456', $result['team']['uuid']);
        $this->assertSame('Boston', $result['team']['city']);
        $this->assertSame('Celtics', $result['team']['name']);
    }

    public function testTransformIncludesSeason(): void
    {
        $row = $this->makeLeaderRow();
        $result = $this->transformer->transform($row);

        $this->assertSame(2007, $result['season']);
    }

    public function testTransformIncludesPerGameStats(): void
    {
        $row = $this->makeLeaderRow();
        $result = $this->transformer->transform($row);

        // PPG = (2*299 + 185 + 70) / 82 = 853/82 = 10.4
        $this->assertSame('10.4', $result['stats']['points_per_game']);
        // RPG = 280/82 = 3.4
        $this->assertSame('3.4', $result['stats']['rebounds_per_game']);
        // APG = 498/82 = 6.1
        $this->assertSame('6.1', $result['stats']['assists_per_game']);
        // SPG = 98/82 = 1.2
        $this->assertSame('1.2', $result['stats']['steals_per_game']);
    }

    public function testTransformIncludesPercentages(): void
    {
        $row = $this->makeLeaderRow();
        $result = $this->transformer->transform($row);

        // FG% = 299/705 = 0.424
        $this->assertSame('0.424', $result['stats']['fg_percentage']);
        // FT% = 185/212 = 0.873
        $this->assertSame('0.873', $result['stats']['ft_percentage']);
        // 3P% = 70/215 = 0.326
        $this->assertSame('0.326', $result['stats']['three_pt_percentage']);
    }

    public function testTransformIncludesInternalIdsInNestedObjects(): void
    {
        $row = $this->makeLeaderRow();
        $result = $this->transformer->transform($row);

        $this->assertSame(201, $result['player']['pid']);
        $this->assertSame(1, $result['team']['team_id']);
    }

    public function testTransformHandlesNullTeam(): void
    {
        $row = $this->makeLeaderRow();
        $row['team_uuid'] = null;
        $row['team_city'] = null;
        $row['team_name'] = null;
        $result = $this->transformer->transform($row);

        $this->assertNull($result['team']['uuid']);
        $this->assertSame('', $result['team']['city']);
        $this->assertSame('', $result['team']['name']);
    }
}
