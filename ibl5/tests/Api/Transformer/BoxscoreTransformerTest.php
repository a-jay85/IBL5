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
            'visitorQ1points' => 28,
            'visitorQ2points' => 30,
            'visitorQ3points' => 25,
            'visitorQ4points' => 22,
            'visitorOTpoints' => 0,
            'homeQ1points' => 32,
            'homeQ2points' => 24,
            'homeQ3points' => 31,
            'homeQ4points' => 27,
            'homeOTpoints' => 0,
            'gameMIN' => 240,
            'game2GM' => 44,
            'game2GA' => 89,
            'gameFTM' => 8,
            'gameFTA' => 10,
            'game3GM' => 6,
            'game3GA' => 15,
            'gameORB' => 16,
            'gameDRB' => 36,
            'gameAST' => 16,
            'gameSTL' => 10,
            'gameTOV' => 17,
            'gameBLK' => 3,
            'gamePF' => 8,
            'attendance' => 7008,
            'capacity' => 19332,
            'visitorWins' => 16,
            'visitorLosses' => 14,
            'homeWins' => 12,
            'homeLosses' => 18,
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
            'gameMIN' => 38,
            'game2GM' => 13,
            'game2GA' => 23,
            'gameFTM' => 0,
            'gameFTA' => 0,
            'game3GM' => 0,
            'game3GA' => 1,
            'gameORB' => 6,
            'gameDRB' => 10,
            'gameAST' => 11,
            'gameSTL' => 4,
            'gameTOV' => 2,
            'gameBLK' => 2,
            'gamePF' => 1,
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
        $row['visitorTeamID'] = 11;
        $row['homeTeamID'] = 26;
        $result = $this->transformer->transformTeamStats($row);

        $this->assertArrayNotHasKey('id', $result);
        $this->assertArrayNotHasKey('visitorTeamID', $result);
        $this->assertArrayNotHasKey('homeTeamID', $result);
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
        $row['visitorTID'] = 8;
        $row['homeTID'] = 16;
        $result = $this->transformer->transformPlayerLine($row);

        $this->assertArrayNotHasKey('id', $result);
        $this->assertArrayNotHasKey('pid', $result);
        $this->assertArrayNotHasKey('visitorTID', $result);
        $this->assertArrayNotHasKey('homeTID', $result);
    }

    public function testTransformPlayerLineHandlesNullUuid(): void
    {
        $row = $this->makePlayerRow();
        $row['player_uuid'] = null;
        $result = $this->transformer->transformPlayerLine($row);

        $this->assertNull($result['uuid']);
    }
}
