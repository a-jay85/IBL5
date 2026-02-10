<?php

declare(strict_types=1);

namespace Tests\Api\Transformer;

use Api\Transformer\InjuryTransformer;
use PHPUnit\Framework\TestCase;

class InjuryTransformerTest extends TestCase
{
    private InjuryTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new InjuryTransformer();
    }

    /**
     * @return array<string, mixed>
     */
    private function makeInjuryRow(): array
    {
        return [
            'player_uuid' => 'player-uuid-123',
            'pid' => 4825,
            'name' => 'Kevin Martin',
            'pos' => 'SG',
            'injured' => 5,
            'teamid' => 26,
            'team_uuid' => 'team-uuid-456',
            'team_city' => 'Sacramento',
            'team_name' => 'Kings',
        ];
    }

    public function testTransformIncludesPlayerInfo(): void
    {
        $row = $this->makeInjuryRow();
        $result = $this->transformer->transform($row);

        $this->assertSame('player-uuid-123', $result['player']['uuid']);
        $this->assertSame('Kevin Martin', $result['player']['name']);
        $this->assertSame('SG', $result['player']['position']);
    }

    public function testTransformIncludesTeamInfo(): void
    {
        $row = $this->makeInjuryRow();
        $result = $this->transformer->transform($row);

        $this->assertSame('team-uuid-456', $result['team']['uuid']);
        $this->assertSame('Sacramento', $result['team']['city']);
        $this->assertSame('Kings', $result['team']['name']);
    }

    public function testTransformIncludesInjuryDaysRemaining(): void
    {
        $row = $this->makeInjuryRow();
        $result = $this->transformer->transform($row);

        $this->assertSame(5, $result['injury']['days_remaining']);
    }

    public function testTransformIncludesInternalIdsInNestedObjects(): void
    {
        $row = $this->makeInjuryRow();
        $result = $this->transformer->transform($row);

        $this->assertSame(4825, $result['player']['pid']);
        $this->assertSame(26, $result['team']['team_id']);
    }

    public function testTransformHandlesNullTeam(): void
    {
        $row = $this->makeInjuryRow();
        $row['team_uuid'] = null;
        $row['team_city'] = null;
        $row['team_name'] = null;
        $result = $this->transformer->transform($row);

        $this->assertNull($result['team']['uuid']);
        $this->assertSame('', $result['team']['city']);
        $this->assertSame('', $result['team']['name']);
    }
}
