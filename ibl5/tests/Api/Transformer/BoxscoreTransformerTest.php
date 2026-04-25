<?php

declare(strict_types=1);

namespace Tests\Api\Transformer;

use Api\Transformer\BoxscoreTransformer;
use PHPUnit\Framework\TestCase;

class BoxscoreTransformerTest extends TestCase
{
    private BoxscoreTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new BoxscoreTransformer();
    }

    /**
     * @return array<string, mixed>
     */
    private function makeTeamRow(): array
    {
        return [
            'name' => 'Celtics',
            'visitor_q1_points' => 28,
            'visitor_q2_points' => 30,
            'visitor_q3_points' => 25,
            'visitor_q4_points' => 22,
            'visitor_ot_points' => 0,
            'home_q1_points' => 32,
            'home_q2_points' => 24,
            'home_q3_points' => 31,
            'home_q4_points' => 27,
            'home_ot_points' => 0,
            'game_min' => 240,
            'game_2gm' => 44,
            'game_2ga' => 89,
            'game_ftm' => 8,
            'game_fta' => 10,
            'game_3gm' => 6,
            'game_3ga' => 15,
            'game_orb' => 16,
            'game_drb' => 36,
            'game_ast' => 16,
            'game_stl' => 10,
            'game_tov' => 17,
            'game_blk' => 3,
            'game_pf' => 8,
            'attendance' => 7008,
            'capacity' => 19332,
            'visitor_wins' => 16,
            'visitor_losses' => 14,
            'home_wins' => 12,
            'home_losses' => 18,
            'calc_points' => 114,
            'calc_rebounds' => 52,
            'calc_fg_made' => 50,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makePlayerRow(): array
    {
        return [
            'player_uuid' => 'player-uuid-123',
            'name' => 'Test Player',
            'pos' => 'PG',
            'game_min' => 38,
            'game_2gm' => 13,
            'game_2ga' => 23,
            'game_ftm' => 0,
            'game_fta' => 0,
            'game_3gm' => 0,
            'game_3ga' => 1,
            'game_orb' => 6,
            'game_drb' => 10,
            'game_ast' => 11,
            'game_stl' => 4,
            'game_tov' => 2,
            'game_blk' => 2,
            'game_pf' => 1,
            'calc_points' => 26,
            'calc_rebounds' => 16,
            'calc_fg_made' => 13,
        ];
    }

    public function testTransformTeamStatsIncludesQuarterScoring(): void
    {
        $row = $this->makeTeamRow();
        $result = $this->transformer->transformTeamStats($row);

        $this->assertSame(28, $result['quarter_scoring']['q1']['visitor']);
        $this->assertSame(32, $result['quarter_scoring']['q1']['home']);
        $this->assertSame(0, $result['quarter_scoring']['ot']['visitor']);
    }

    public function testTransformTeamStatsIncludesTotals(): void
    {
        $row = $this->makeTeamRow();
        $result = $this->transformer->transformTeamStats($row);

        $this->assertSame(50, $result['totals']['fg_made']);
        $this->assertSame(104, $result['totals']['fg_attempted']); // 89 + 15
        $this->assertSame(44, $result['totals']['two_pt_made']);
        $this->assertSame(89, $result['totals']['two_pt_attempted']);
        $this->assertSame(8, $result['totals']['ft_made']);
        $this->assertSame(10, $result['totals']['ft_attempted']);
        $this->assertSame(6, $result['totals']['three_pt_made']);
        $this->assertSame(15, $result['totals']['three_pt_attempted']);
        $this->assertSame(16, $result['totals']['offensive_rebounds']);
        $this->assertSame(36, $result['totals']['defensive_rebounds']);
        $this->assertSame(52, $result['totals']['rebounds']);
        $this->assertSame(16, $result['totals']['assists']);
        $this->assertSame(10, $result['totals']['steals']);
        $this->assertSame(17, $result['totals']['turnovers']);
        $this->assertSame(3, $result['totals']['blocks']);
        $this->assertSame(8, $result['totals']['personal_fouls']);
        $this->assertSame(114, $result['totals']['points']);
    }

    public function testTransformTeamStatsIncludesAttendance(): void
    {
        $row = $this->makeTeamRow();
        $result = $this->transformer->transformTeamStats($row);

        $this->assertSame(7008, $result['attendance']);
        $this->assertSame(19332, $result['capacity']);
    }

    public function testTransformTeamStatsIncludesRecords(): void
    {
        $row = $this->makeTeamRow();
        $result = $this->transformer->transformTeamStats($row);

        $this->assertSame('16-14', $result['records']['visitor']);
        $this->assertSame('12-18', $result['records']['home']);
    }

    public function testTransformTeamStatsExcludesInternalIds(): void
    {
        $row = $this->makeTeamRow();
        $row['id'] = 1;
        $row['visitor_teamid'] = 11;
        $row['home_teamid'] = 26;
        $result = $this->transformer->transformTeamStats($row);

        $this->assertArrayNotHasKey('id', $result);
        $this->assertArrayNotHasKey('visitor_teamid', $result);
        $this->assertArrayNotHasKey('home_teamid', $result);
    }

    public function testTransformPlayerLineIncludesPlayerInfo(): void
    {
        $row = $this->makePlayerRow();
        $result = $this->transformer->transformPlayerLine($row);

        $this->assertSame('player-uuid-123', $result['uuid']);
        $this->assertSame('Test Player', $result['name']);
        $this->assertSame('PG', $result['position']);
        $this->assertSame(38, $result['minutes']);
    }

    public function testTransformPlayerLineIncludesStats(): void
    {
        $row = $this->makePlayerRow();
        $result = $this->transformer->transformPlayerLine($row);

        $this->assertSame(13, $result['two_pt_made']);
        $this->assertSame(23, $result['two_pt_attempted']);
        $this->assertSame(0, $result['ft_made']);
        $this->assertSame(0, $result['ft_attempted']);
        $this->assertSame(0, $result['three_pt_made']);
        $this->assertSame(1, $result['three_pt_attempted']);
        $this->assertSame(13, $result['fg_made']);
        $this->assertSame(24, $result['fg_attempted']); // 23 + 1
        $this->assertSame(6, $result['offensive_rebounds']);
        $this->assertSame(10, $result['defensive_rebounds']);
        $this->assertSame(16, $result['rebounds']);
        $this->assertSame(11, $result['assists']);
        $this->assertSame(4, $result['steals']);
        $this->assertSame(2, $result['turnovers']);
        $this->assertSame(2, $result['blocks']);
        $this->assertSame(1, $result['personal_fouls']);
        $this->assertSame(26, $result['points']);
    }

    public function testTransformPlayerLineExcludesInternalIds(): void
    {
        $row = $this->makePlayerRow();
        $row['id'] = 1;
        $row['pid'] = 4825;
        $row['visitor_teamid'] = 8;
        $row['home_teamid'] = 16;
        $result = $this->transformer->transformPlayerLine($row);

        $this->assertArrayNotHasKey('id', $result);
        $this->assertArrayNotHasKey('pid', $result);
        $this->assertArrayNotHasKey('visitor_teamid', $result);
        $this->assertArrayNotHasKey('home_teamid', $result);
    }

    public function testTransformPlayerLineHandlesNullUuid(): void
    {
        $row = $this->makePlayerRow();
        $row['player_uuid'] = null;
        $result = $this->transformer->transformPlayerLine($row);

        $this->assertNull($result['uuid']);
    }
}
