<?php

declare(strict_types=1);

namespace Tests\ActivityTracker;

use ActivityTracker\ActivityTrackerRepository;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

class ActivityTrackerRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    public function testGetTeamActivityReturnsEmptyArrayWhenNoTeams(): void
    {
        $this->mockDb->setMockData([]);
        $repository = new ActivityTrackerRepository($this->mockDb);

        $result = $repository->getTeamActivity();

        $this->assertSame([], $result);
    }

    public function testGetTeamActivityReturnsTeamData(): void
    {
        $this->mockDb->setMockData([
            [
                'teamid' => 1,
                'team_name' => 'Hawks',
                'team_city' => 'Atlanta',
                'color1' => 'E03A3E',
                'color2' => 'C1D32F',
                'depth' => '2025-01-15',
                'sim_depth' => '2025-01-14',
                'asg_vote' => 'Yes',
                'eoy_vote' => 'No',
            ],
        ]);
        $repository = new ActivityTrackerRepository($this->mockDb);

        $result = $repository->getTeamActivity();

        $this->assertCount(1, $result);
        $this->assertSame('Hawks', $result[0]['team_name']);
        $this->assertSame('Atlanta', $result[0]['team_city']);
    }
}
