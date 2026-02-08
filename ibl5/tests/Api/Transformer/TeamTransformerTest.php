<?php

declare(strict_types=1);

namespace Tests\Api\Transformer;

use Api\Transformer\TeamTransformer;
use PHPUnit\Framework\TestCase;

class TeamTransformerTest extends TestCase
{
    private TeamTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new TeamTransformer();
    }

    /**
     * @return array<string, mixed>
     */
    private function makeTeamRow(): array
    {
        return [
            'uuid' => 'team-uuid-123',
            'team_city' => 'Chicago',
            'team_name' => 'Bulls',
            'owner_name' => 'TestOwner',
            'arena' => 'United Center',
            'conference' => 'East',
            'division' => 'Central',
        ];
    }

    public function testTransformIncludesBasicFields(): void
    {
        $row = $this->makeTeamRow();
        $result = $this->transformer->transform($row);

        $this->assertSame('team-uuid-123', $result['uuid']);
        $this->assertSame('Chicago', $result['city']);
        $this->assertSame('Bulls', $result['name']);
        $this->assertSame('Chicago Bulls', $result['full_name']);
        $this->assertSame('TestOwner', $result['owner']);
        $this->assertSame('United Center', $result['arena']);
        $this->assertSame('East', $result['conference']);
        $this->assertSame('Central', $result['division']);
    }

    public function testTransformExcludesInternalIds(): void
    {
        $row = $this->makeTeamRow();
        $result = $this->transformer->transform($row);

        $this->assertArrayNotHasKey('teamid', $result);
    }

    public function testTransformDetailIncludesRecords(): void
    {
        $row = $this->makeTeamRow();
        $row['league_record'] = '42-20';
        $row['conference_record'] = '28-12';
        $row['division_record'] = '10-4';
        $row['home_wins'] = 25;
        $row['home_losses'] = 6;
        $row['away_wins'] = 17;
        $row['away_losses'] = 14;
        $row['win_percentage'] = 0.677;
        $row['conference_games_back'] = '2.5';
        $row['division_games_back'] = '0.0';
        $row['games_remaining'] = 20;

        $result = $this->transformer->transformDetail($row);

        $this->assertSame('42-20', $result['record']['league']);
        $this->assertSame('28-12', $result['record']['conference']);
        $this->assertSame('25-6', $result['record']['home']);
        $this->assertSame('17-14', $result['record']['away']);
        $this->assertSame(0.677, $result['standings']['win_percentage']);
        $this->assertSame(20, $result['standings']['games_remaining']);
    }

    public function testTransformDetailHandlesNullStandings(): void
    {
        $row = $this->makeTeamRow();
        $row['league_record'] = null;
        $row['conference_record'] = null;
        $row['division_record'] = null;
        $row['home_wins'] = null;
        $row['home_losses'] = null;
        $row['away_wins'] = null;
        $row['away_losses'] = null;
        $row['win_percentage'] = null;
        $row['conference_games_back'] = null;
        $row['division_games_back'] = null;
        $row['games_remaining'] = null;

        $result = $this->transformer->transformDetail($row);

        $this->assertNull($result['record']['league']);
        $this->assertNull($result['standings']['win_percentage']);
    }
}
