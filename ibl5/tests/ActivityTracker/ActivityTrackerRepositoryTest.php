<?php

declare(strict_types=1);

namespace Tests\ActivityTracker;

use ActivityTracker\ActivityTrackerRepository;
use ActivityTracker\Contracts\ActivityTrackerRepositoryInterface;
use PHPUnit\Framework\TestCase;

class ActivityTrackerRepositoryTest extends TestCase
{
    private \MockDatabase $mockDb;
    private object $mockMysqliDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->setupMockMysqliDb();
    }

    private function setupMockMysqliDb(): void
    {
        $mockDb = $this->mockDb;

        $this->mockMysqliDb = new class($mockDb) extends \mysqli {
            private \MockDatabase $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct(\MockDatabase $mockDb)
            {
                $this->mockDb = $mockDb;
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $query): \MockPreparedStatement|false
            {
                return new \MockPreparedStatement($this->mockDb, $query);
            }
        };
    }

    public function testImplementsInterface(): void
    {
        $repository = new ActivityTrackerRepository($this->mockMysqliDb);

        $this->assertInstanceOf(ActivityTrackerRepositoryInterface::class, $repository);
    }

    public function testGetTeamActivityReturnsEmptyArrayWhenNoTeams(): void
    {
        $this->mockDb->setMockData([]);
        $repository = new ActivityTrackerRepository($this->mockMysqliDb);

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
        $repository = new ActivityTrackerRepository($this->mockMysqliDb);

        $result = $repository->getTeamActivity();

        $this->assertCount(1, $result);
        $this->assertSame('Hawks', $result[0]['team_name']);
        $this->assertSame('Atlanta', $result[0]['team_city']);
    }
}
