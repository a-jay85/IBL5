<?php

declare(strict_types=1);

namespace Tests\Api\Transformer;

use Api\Transformer\PlayerStatsTransformer;
use PHPUnit\Framework\TestCase;

class PlayerStatsTransformerTest extends TestCase
{
    private PlayerStatsTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new PlayerStatsTransformer();
    }

    /**
     * @return array<string, mixed>
     */
    private function makeCareerRow(): array
    {
        return [
            'player_uuid' => 'player-uuid-123',
            'pid' => 201,
            'name' => 'LeBron James',
            'career_games' => 1400,
            'career_minutes' => 52000,
            'career_points' => 40000,
            'career_rebounds' => 10500,
            'career_assists' => 10800,
            'career_steals' => 2100,
            'career_blocks' => 1100,
            'ppg_career' => 28.6,
            'rpg_career' => 7.5,
            'apg_career' => 7.7,
            'fg_pct_career' => 0.505,
            'ft_pct_career' => 0.735,
            'three_pt_pct_career' => 0.346,
            'playoff_minutes' => 12000,
            'draft_year' => 2003,
            'draft_round' => 1,
            'draft_pick' => 1,
            'drafted_by_team' => 'Cavaliers',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2026-01-15 12:00:00',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeSeasonRow(): array
    {
        return [
            'player_uuid' => 'player-uuid-123',
            'year' => 2007,
            'team' => 'Celtics',
            'team_uuid' => 'team-uuid-456',
            'team_city' => 'Boston',
            'team_name' => 'Celtics',
            'teamid' => 1,
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
            'salary' => 500,
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2026-01-15 12:00:00',
        ];
    }

    public function testTransformCareerExposesUuid(): void
    {
        $row = $this->makeCareerRow();
        $result = $this->transformer->transformCareer($row);

        $this->assertSame('player-uuid-123', $result['uuid']);
        $this->assertArrayNotHasKey('pid', $result);
    }

    public function testTransformCareerIncludesTotals(): void
    {
        $row = $this->makeCareerRow();
        $result = $this->transformer->transformCareer($row);

        $this->assertSame(1400, $result['career_totals']['games']);
        $this->assertSame(52000, $result['career_totals']['minutes']);
        $this->assertSame(40000, $result['career_totals']['points']);
        $this->assertSame(10500, $result['career_totals']['rebounds']);
        $this->assertSame(10800, $result['career_totals']['assists']);
        $this->assertSame(2100, $result['career_totals']['steals']);
        $this->assertSame(1100, $result['career_totals']['blocks']);
    }

    public function testTransformCareerIncludesAverages(): void
    {
        $row = $this->makeCareerRow();
        $result = $this->transformer->transformCareer($row);

        $this->assertSame(28.6, $result['career_averages']['points_per_game']);
        $this->assertSame(7.5, $result['career_averages']['rebounds_per_game']);
        $this->assertSame(7.7, $result['career_averages']['assists_per_game']);
    }

    public function testTransformCareerIncludesPercentages(): void
    {
        $row = $this->makeCareerRow();
        $result = $this->transformer->transformCareer($row);

        $this->assertSame(0.505, $result['career_percentages']['fg_percentage']);
        $this->assertSame(0.735, $result['career_percentages']['ft_percentage']);
        $this->assertSame(0.346, $result['career_percentages']['three_pt_percentage']);
    }

    public function testTransformCareerIncludesDraft(): void
    {
        $row = $this->makeCareerRow();
        $result = $this->transformer->transformCareer($row);

        $this->assertSame(2003, $result['draft']['year']);
        $this->assertSame(1, $result['draft']['round']);
        $this->assertSame(1, $result['draft']['pick']);
        $this->assertSame('Cavaliers', $result['draft']['team']);
    }

    public function testTransformCareerHandlesNullDraft(): void
    {
        $row = $this->makeCareerRow();
        $row['draft_year'] = null;
        $row['draft_round'] = null;
        $row['draft_pick'] = null;
        $row['drafted_by_team'] = null;
        $result = $this->transformer->transformCareer($row);

        $this->assertNull($result['draft']['year']);
        $this->assertNull($result['draft']['team']);
    }

    public function testTransformSeasonIncludesBasicFields(): void
    {
        $row = $this->makeSeasonRow();
        $result = $this->transformer->transformSeason($row);

        $this->assertSame(2007, $result['year']);
        $this->assertSame(82, $result['games']);
        $this->assertSame(2238, $result['minutes']);
        $this->assertSame(500, $result['salary']);
    }

    public function testTransformSeasonIncludesTeam(): void
    {
        $row = $this->makeSeasonRow();
        $result = $this->transformer->transformSeason($row);

        $this->assertSame('team-uuid-456', $result['team']['uuid']);
        $this->assertSame('Boston', $result['team']['city']);
        $this->assertSame('Celtics', $result['team']['name']);
    }

    public function testTransformSeasonCalculatesPoints(): void
    {
        $row = $this->makeSeasonRow();
        $result = $this->transformer->transformSeason($row);

        // Points = 2*FGM + FTM + TGM = 2*299 + 185 + 70 = 853
        $this->assertSame(853, $result['stats']['points']);
    }

    public function testTransformSeasonIncludesPerGameAverages(): void
    {
        $row = $this->makeSeasonRow();
        $result = $this->transformer->transformSeason($row);

        // PPG = 853/82 = 10.4
        $this->assertSame('10.4', $result['per_game']['points']);
        // RPG = 280/82 = 3.4
        $this->assertSame('3.4', $result['per_game']['rebounds']);
        // APG = 498/82 = 6.1
        $this->assertSame('6.1', $result['per_game']['assists']);
    }

    public function testTransformSeasonIncludesPercentages(): void
    {
        $row = $this->makeSeasonRow();
        $result = $this->transformer->transformSeason($row);

        // FG% = 299/705 = 0.424
        $this->assertSame('0.424', $result['percentages']['fg']);
        // FT% = 185/212 = 0.873
        $this->assertSame('0.873', $result['percentages']['ft']);
        // 3P% = 70/215 = 0.326
        $this->assertSame('0.326', $result['percentages']['three_pt']);
    }

    public function testTransformSeasonHandlesZeroGames(): void
    {
        $row = $this->makeSeasonRow();
        $row['games'] = 0;
        $result = $this->transformer->transformSeason($row);

        $this->assertSame('0.0', $result['per_game']['points']);
        $this->assertSame('0.0', $result['per_game']['rebounds']);
    }

    public function testTransformSeasonExcludesInternalIds(): void
    {
        $row = $this->makeSeasonRow();
        $result = $this->transformer->transformSeason($row);

        $this->assertArrayNotHasKey('pid', $result);
        $this->assertArrayNotHasKey('teamid', $result);
    }
}
