<?php

declare(strict_types=1);

namespace Tests\Api\Transformer;

use Api\Transformer\TeamTransformer;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type TeamListRow from \Api\Repository\ApiTeamRepository
 * @phpstan-import-type TeamDetailRow from \Api\Repository\ApiTeamRepository
 */
class TeamTransformerTest extends TestCase
{
    private TeamTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new TeamTransformer();
    }

    /**
     * @return TeamListRow
     */
    private function makeTeamRow(): array
    {
        return [
            'teamid' => 10,
            'uuid' => 'team-uuid-123',
            'team_city' => 'Chicago',
            'team_name' => 'Bulls',
            'owner_name' => 'TestOwner',
            'discord_id' => 123456789,
            'arena' => 'United Center',
            'conference' => 'East',
            'division' => 'Central',
        ];
    }

    /**
     * @return TeamDetailRow
     */
    private function makeTeamDetailRow(): array
    {
        return [
            'teamid' => 10,
            'uuid' => 'team-uuid-123',
            'team_city' => 'Chicago',
            'team_name' => 'Bulls',
            'owner_name' => 'TestOwner',
            'discord_id' => 123456789,
            'arena' => 'United Center',
            'conference' => 'East',
            'division' => 'Central',
            'league_record' => '42-20',
            'conference_record' => '28-12',
            'division_record' => '10-4',
            'home_wins' => 25,
            'home_losses' => 6,
            'away_wins' => 17,
            'away_losses' => 14,
            'win_percentage' => 0.677,
            'conference_games_back' => '2.5',
            'division_games_back' => '0.0',
            'games_remaining' => 20,
        ];
    }

    /**
     * @return TeamDetailRow
     */
    private function makeTeamDetailRowNullStandings(): array
    {
        return [
            'teamid' => 10,
            'uuid' => 'team-uuid-123',
            'team_city' => 'Chicago',
            'team_name' => 'Bulls',
            'owner_name' => 'TestOwner',
            'discord_id' => 123456789,
            'arena' => 'United Center',
            'conference' => null,
            'division' => null,
            'league_record' => null,
            'conference_record' => null,
            'division_record' => null,
            'home_wins' => null,
            'home_losses' => null,
            'away_wins' => null,
            'away_losses' => null,
            'win_percentage' => null,
            'conference_games_back' => null,
            'division_games_back' => null,
            'games_remaining' => null,
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

    public function testTransformIncludesInternalIds(): void
    {
        $row = $this->makeTeamRow();
        $result = $this->transformer->transform($row);

        $this->assertSame(10, $result['team_id']);
        $this->assertSame(123456789, $result['owner_discord_id']);
    }

    public function testTransformDetailIncludesRecords(): void
    {
        $row = $this->makeTeamDetailRow();
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
        $row = $this->makeTeamDetailRowNullStandings();
        $result = $this->transformer->transformDetail($row);

        $this->assertNull($result['record']['league']);
        $this->assertNull($result['standings']['win_percentage']);
    }
}
