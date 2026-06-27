<?php

declare(strict_types=1);

namespace Tests\GMContactList;

use GMContactList\GMContactListRepository;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

class GMContactListRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    public function testGetAllTeamContactsReturnsMappedContacts(): void
    {
        $this->mockDb->setMockData([
            ['teamid' => 1, 'team_city' => 'Springfield', 'team_name' => 'Bears', 'color1' => '#111', 'color2' => '#222', 'owner_name' => 'Alice', 'discord_id' => 123456],
            ['teamid' => 2, 'team_city' => 'Shelbyville', 'team_name' => 'Eagles', 'color1' => '#333', 'color2' => '#444', 'owner_name' => 'Bob', 'discord_id' => null],
        ]);
        $repo = new GMContactListRepository($this->mockDb);

        $result = $repo->getAllTeamContacts();

        $this->assertSame([
            ['teamid' => 1, 'team_city' => 'Springfield', 'team_name' => 'Bears', 'color1' => '#111', 'color2' => '#222', 'owner_name' => 'Alice', 'discord_id' => 123456],
            ['teamid' => 2, 'team_city' => 'Shelbyville', 'team_name' => 'Eagles', 'color1' => '#333', 'color2' => '#444', 'owner_name' => 'Bob', 'discord_id' => null],
        ], $result);
        $this->assertQueryExecuted('ibl_team_info');
    }

    public function testGetAllTeamContactsReturnsEmptyArrayWhenNoTeams(): void
    {
        $this->mockDb->setMockData([]);
        $repo = new GMContactListRepository($this->mockDb);

        $this->assertSame([], $repo->getAllTeamContacts());
    }

    private function assertQueryExecuted(string $substring): void
    {
        $queries = $this->mockDb->getExecutedQueries();
        $found = false;
        foreach ($queries as $query) {
            if (str_contains($query, $substring)) {
                $found = true;
                break;
            }
        }
        self::assertTrue(
            $found,
            "Expected a query containing '{$substring}' but none was found. Queries: " . implode("\n", $queries)
        );
    }
}
