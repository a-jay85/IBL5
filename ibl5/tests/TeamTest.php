<?php

declare(strict_types=1);

/**
 * TeamTest - Tests for Team class
 */
class TeamTest extends \PHPUnit\Framework\TestCase
{
    private \MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->mockDb->setMockData([
            [
                'teamid' => 1,
                'city' => 'Test',
                'name' => 'Team',
                'color1' => '000000',
                'color2' => 'FFFFFF',
                'arena' => 'Test Arena',
                'capacity' => 20000,
                'owner' => 'Test Owner',
                'email' => 'test@test.com',
                'discordid' => '123',
                'hasusedextensionthissim' => 0,
                'hasusedextensionthisseason' => 0,
                'hasMLE' => 1,
                'hasLLE' => 1,
                'wins' => 10,
                'losses' => 5,
            ],
        ]);
    }

    // ============================================
    // CONSTANT TESTS
    // ============================================

    public function testBuyoutPercentageMaxConstant(): void
    {
        $this->assertSame(0.40, \Team::BUYOUT_PERCENTAGE_MAX);
    }

    public function testRosterSpotsMaxConstant(): void
    {
        $this->assertSame(15, \Team::ROSTER_SPOTS_MAX);
    }

    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testCanBeInstantiated(): void
    {
        $team = new \Team($this->mockDb);

        $this->assertInstanceOf(\Team::class, $team);
    }
}
