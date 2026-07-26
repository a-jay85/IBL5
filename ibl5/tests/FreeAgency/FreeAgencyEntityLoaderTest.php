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

        // @phpstan-ignore-next-line (asserting return type as intentional contract test)
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

        // @phpstan-ignore-next-line (asserting return type as intentional contract test)
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

    public function testLoadTeamWithUnknownNameBehavesLikeStaticFactory(): void
    {
        // Both the loader and Team::initialize() throw when no row is found.
        $loaderEx = null;
        $staticEx = null;

        $loaderDb = new MockDatabase();
        $loader   = new FreeAgencyEntityLoader($loaderDb);
        try {
            $loader->loadTeam('NonExistentTeam');
        } catch (\RuntimeException $e) {
            $loaderEx = $e;
        }

        $staticDb = new MockDatabase();
        try {
            Team::initialize($staticDb, 'NonExistentTeam');
        } catch (\RuntimeException $e) {
            $staticEx = $e;
        }

        $this->assertNotNull($loaderEx, 'FreeAgencyEntityLoader::loadTeam() must throw when team not found');
        $this->assertNotNull($staticEx, 'Team::initialize() must throw when team not found');
        $this->assertSame($staticEx->getMessage(), $loaderEx->getMessage());
    }

    public function testLoadTeamWithEmptyStringDoesNotThrow(): void
    {
        // '' maps to FREE_AGENTS_TEAMID (0) inside Team::load(); provide a row
        // for teamid=0 so the query succeeds and no RuntimeException is raised.
        $this->mockDb->setMockData([[
            'teamid'        => 0,
            'team_name'     => 'Free Agents',
            'team_city'     => 'League',
            'color1'        => '888888',
            'color2'        => 'FFFFFF',
            'arena'         => '',
            'capacity'      => 0,
            'owner_name'    => '',
            'owner_email'   => '',
            'league_record' => null,
        ]]);

        $loader = new FreeAgencyEntityLoader($this->mockDb);
        $team   = $loader->loadTeam('');

        // @phpstan-ignore-next-line (asserting return type as intentional contract test)
        $this->assertInstanceOf(Team::class, $team);
    }
}
