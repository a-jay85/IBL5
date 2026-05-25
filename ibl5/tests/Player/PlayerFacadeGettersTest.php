<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\TestCase;
use Player\Player;

class PlayerFacadeGettersTest extends TestCase
{
    public function testGettersReturnNullWhenPlayerDataNotLoaded(): void
    {
        $player = new Player();

        self::assertNull($player->getPlayerID());
        self::assertNull($player->getName());
        self::assertNull($player->getTeamid());
        self::assertNull($player->getPosition());
        self::assertNull($player->getRatingFieldGoalAttempts());
        self::assertNull($player->getRatingClutch());
        self::assertNull($player->getContractYear1Salary());
        self::assertNull($player->getBirdYears());
        self::assertNull($player->getDraftYear());
        self::assertNull($player->getCollegeName());
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
