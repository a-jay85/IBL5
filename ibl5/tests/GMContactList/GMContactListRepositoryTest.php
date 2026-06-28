<?php

declare(strict_types=1);

namespace Tests\GMContactList;

use GMContactList\GMContactListRepository;
use Tests\WideUnit\WideUnitTestCase;

class GMContactListRepositoryTest extends WideUnitTestCase
{
    private function repo(): GMContactListRepository
    {
        $db = $this->mockDb;
        self::assertNotNull($db);
        return new GMContactListRepository($db);
    }

    public function testGetAllTeamContactsReturnsMappedContacts(): void
    {
        $this->mockDb->setMockData([
            ['teamid' => 1, 'team_city' => 'Springfield', 'team_name' => 'Bears', 'color1' => '#111', 'color2' => '#222', 'owner_name' => 'Alice', 'discord_id' => 123456],
            ['teamid' => 2, 'team_city' => 'Shelbyville', 'team_name' => 'Eagles', 'color1' => '#333', 'color2' => '#444', 'owner_name' => 'Bob', 'discord_id' => null],
        ]);

        $result = $this->repo()->getAllTeamContacts();

        $this->assertSame([
            ['teamid' => 1, 'team_city' => 'Springfield', 'team_name' => 'Bears', 'color1' => '#111', 'color2' => '#222', 'owner_name' => 'Alice', 'discord_id' => 123456],
            ['teamid' => 2, 'team_city' => 'Shelbyville', 'team_name' => 'Eagles', 'color1' => '#333', 'color2' => '#444', 'owner_name' => 'Bob', 'discord_id' => null],
        ], $result);
        $this->assertQueryExecuted('ibl_team_info');
    }

    public function testGetAllTeamContactsReturnsEmptyArrayWhenNoTeams(): void
    {
        $this->mockDb->setMockData([]);

        $this->assertSame([], $this->repo()->getAllTeamContacts());
    }
}
