<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\TestCase;
use Player\Player;

class PlayerFacadeGettersTest extends TestCase
{
    public function testGetterThrowsWhenPlayerDataNotLoaded(): void
    {
        $player = new Player();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Player data has not been loaded');
        $player->getPlayerID();
    }

    public function testGetNameStatusClassReturnsEmptyStringWhenPlayerDataNotLoaded(): void
    {
        $player = new Player();

        self::assertSame('', $player->getNameStatusClass());
    }

    public function testGetPlrRowReturnsNullWhenPlayerDataNotLoaded(): void
    {
        $player = new Player();

        self::assertNull($player->getPlrRow());
    }

    public function testGetTeamCityReturnsNullWhenPlayerDataNotLoaded(): void
    {
        $player = new Player();

        self::assertNull($player->getTeamCity());
    }

    public function testGetTeamColor1ReturnsNullWhenPlayerDataNotLoaded(): void
    {
        $player = new Player();

        self::assertNull($player->getTeamColor1());
    }

    public function testGetTeamColor2ReturnsNullWhenPlayerDataNotLoaded(): void
    {
        $player = new Player();

        self::assertNull($player->getTeamColor2());
    }
}
