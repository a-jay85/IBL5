<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\FreeAgencyEntityLoader;
use PHPUnit\Framework\TestCase;
use Player\Player;
use Team\Team;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\Mocks\TestDataFactory;

class FreeAgencyEntityLoaderTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    public function testLoadPlayerReturnsPlayerInstance(): void
    {
        $this->mockDb->setMockData([TestDataFactory::createPlayer(['pid' => 42])]);

        $loader = new FreeAgencyEntityLoader($this->mockDb);
        $player = $loader->loadPlayer(42);

        $this->assertInstanceOf(Player::class, $player);
        $this->assertSame(42, $player->getPlayerID());
    }

    public function testLoadTeamReturnsTeamInstance(): void
    {
        $this->mockDb->setMockData([[
            'teamid'      => 5,
            'team_city'   => 'Boston',
            'team_name'   => 'Boston',
            'color1'      => 'FFFFFF',
            'color2'      => '000000',
            'arena'       => 'Test Arena',
            'capacity'    => 20000,
            'owner_name'  => 'Test Owner',
            'owner_email' => 'owner@test.com',
            'league_record' => null,
        ]]);

        $loader = new FreeAgencyEntityLoader($this->mockDb);
        $team = $loader->loadTeam('Boston');

        $this->assertInstanceOf(Team::class, $team);
    }

    public function testLoadPlayerWithUnknownPidBehavesLikeStaticFactory(): void
    {
        // Both paths should throw a RuntimeException when the player is not found.
        // Empty MockDatabase → fetchOne returns null → PlayerRepository::loadByID throws.
        $loaderEx = null;
        $staticEx = null;

        $loaderDb = new MockDatabase();
        $loader   = new FreeAgencyEntityLoader($loaderDb);
        try {
            $loader->loadPlayer(999);
        } catch (\RuntimeException $e) {
            $loaderEx = $e;
        }

        $staticDb = new MockDatabase();
        try {
            Player::withPlayerID($staticDb, 999);
        } catch (\RuntimeException $e) {
            $staticEx = $e;
        }

        $this->assertNotNull($loaderEx, 'FreeAgencyEntityLoader::loadPlayer() must throw when player not found');
        $this->assertNotNull($staticEx, 'Player::withPlayerID() must throw when player not found');
        $this->assertSame($staticEx->getMessage(), $loaderEx->getMessage());
    }
}
