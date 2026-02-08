<?php

declare(strict_types=1);

namespace Tests\Api\Transformer;

use Api\Transformer\StandingsTransformer;
use PHPUnit\Framework\TestCase;

class StandingsTransformerTest extends TestCase
{
    private StandingsTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new StandingsTransformer();
    }

    /**
     * @return array<string, mixed>
     */
    private function makeStandingsRow(): array
    {
        return [
            'team_uuid' => 'team-uuid-456',
            'team_city' => 'Miami',
            'team_name' => 'Heat',
            'full_team_name' => 'Miami Heat',
            'conference' => 'East',
            'division' => 'Southeast',
            'league_record' => '35-27',
            'conference_record' => '22-18',
            'division_record' => '8-6',
            'home_record' => '20-11',
            'away_record' => '15-16',
            'win_percentage' => 0.565,
            'conference_games_back' => '5.0',
            'division_games_back' => '2.0',
            'games_remaining' => 20,
            'clinched_conference' => 0,
            'clinched_division' => 0,
            'clinched_playoffs' => 1,
        ];
    }

    public function testTransformIncludesTeamObject(): void
    {
        $row = $this->makeStandingsRow();
        $result = $this->transformer->transform($row);

        $this->assertSame('team-uuid-456', $result['team']['uuid']);
        $this->assertSame('Miami', $result['team']['city']);
        $this->assertSame('Heat', $result['team']['name']);
        $this->assertSame('Miami Heat', $result['team']['full_name']);
    }

    public function testTransformExcludesInternalIds(): void
    {
        $row = $this->makeStandingsRow();
        $row['teamid'] = 5;
        $result = $this->transformer->transform($row);

        $this->assertArrayNotHasKey('teamid', $result);
        $this->assertArrayNotHasKey('team_uuid', $result);
    }

    public function testTransformIncludesConferenceAndDivision(): void
    {
        $row = $this->makeStandingsRow();
        $result = $this->transformer->transform($row);

        $this->assertSame('East', $result['conference']);
        $this->assertSame('Southeast', $result['division']);
    }

    public function testTransformIncludesRecords(): void
    {
        $row = $this->makeStandingsRow();
        $result = $this->transformer->transform($row);

        $this->assertSame('35-27', $result['record']['league']);
        $this->assertSame('22-18', $result['record']['conference']);
        $this->assertSame('8-6', $result['record']['division']);
        $this->assertSame('20-11', $result['record']['home']);
        $this->assertSame('15-16', $result['record']['away']);
    }

    public function testTransformIncludesGamesBack(): void
    {
        $row = $this->makeStandingsRow();
        $result = $this->transformer->transform($row);

        $this->assertSame('5.0', $result['games_back']['conference']);
        $this->assertSame('2.0', $result['games_back']['division']);
    }

    public function testTransformIncludesClinchStatus(): void
    {
        $row = $this->makeStandingsRow();
        $result = $this->transformer->transform($row);

        $this->assertFalse($result['clinched']['conference']);
        $this->assertFalse($result['clinched']['division']);
        $this->assertTrue($result['clinched']['playoffs']);
    }

    public function testTransformWinPercentage(): void
    {
        $row = $this->makeStandingsRow();
        $result = $this->transformer->transform($row);

        $this->assertSame(0.565, $result['win_percentage']);
    }

    public function testTransformNullWinPercentage(): void
    {
        $row = $this->makeStandingsRow();
        $row['win_percentage'] = null;
        $result = $this->transformer->transform($row);

        $this->assertNull($result['win_percentage']);
    }

    public function testTransformGamesRemaining(): void
    {
        $row = $this->makeStandingsRow();
        $result = $this->transformer->transform($row);

        $this->assertSame(20, $result['games_remaining']);
    }
}
