<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use Tests\WideUnit\Mocks\TestDataFactory;

class InjuriesEntryPointTest extends ModuleEntryPointTestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function fullTeamData(array $overrides = []): array
    {
        return array_merge(TestDataFactory::createTeam(), [
            'used_extension_this_chunk' => 0,
            'used_extension_this_season' => 0,
            'league_record' => '10-5',
        ], $overrides);
    }

    public function testRendersInjuredPlayersList(): void
    {
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([
            TestDataFactory::createPlayer(['injured' => 3, 'injdays' => 3]),
        ]);
        $output = $this->runModule('Injuries');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_plr');
    }

    public function testRendersWithNoInjuredPlayers(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Injuries');

        $this->assertNotEmpty($output);
    }
}
