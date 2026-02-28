<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\TestCase;
use Player\PlayerNameDecorator;
use Player\PlayerData;

class PlayerNameDecoratorTest extends TestCase
{
    private PlayerNameDecorator $decorator;

    protected function setUp(): void
    {
        $this->decorator = new PlayerNameDecorator();
    }

    public function testDecoratePlayerNameReturnsRawName(): void
    {
        $playerData = new PlayerData();
        $playerData->name = "John Doe";
        $playerData->teamID = 5;
        $playerData->ordinal = \JSB::WAIVERS_ORDINAL + 1;
        $playerData->contractCurrentYear = 1;
        $playerData->contractTotalYears = 3;

        $result = $this->decorator->decoratePlayerName($playerData);

        $this->assertSame("John Doe", $result);
    }

    public function testDecoratePlayerNameReturnsRawNameForExpiringContract(): void
    {
        $playerData = new PlayerData();
        $playerData->name = "John Doe";
        $playerData->teamID = 5;
        $playerData->ordinal = 1;
        $playerData->contractCurrentYear = 3;
        $playerData->contractTotalYears = 3;

        $result = $this->decorator->decoratePlayerName($playerData);

        $this->assertSame("John Doe", $result);
    }

    public function testGetStatusClassReturnsWaivedForHighOrdinal(): void
    {
        $playerData = new PlayerData();
        $playerData->name = "John Doe";
        $playerData->teamID = 5;
        $playerData->ordinal = \JSB::WAIVERS_ORDINAL + 1;
        $playerData->contractCurrentYear = 1;
        $playerData->contractTotalYears = 3;

        $result = $this->decorator->getNameStatusClass($playerData);

        $this->assertSame('player-waived', $result);
    }

    public function testGetStatusClassReturnsExpiringForMatchingContractYears(): void
    {
        $playerData = new PlayerData();
        $playerData->name = "John Doe";
        $playerData->teamID = 5;
        $playerData->ordinal = 1;
        $playerData->contractCurrentYear = 3;
        $playerData->contractTotalYears = 3;

        $result = $this->decorator->getNameStatusClass($playerData);

        $this->assertSame('player-expiring', $result);
    }

    public function testGetStatusClassReturnsEmptyForRegularPlayer(): void
    {
        $playerData = new PlayerData();
        $playerData->name = "John Doe";
        $playerData->teamID = 5;
        $playerData->ordinal = 1;
        $playerData->contractCurrentYear = 2;
        $playerData->contractTotalYears = 3;

        $result = $this->decorator->getNameStatusClass($playerData);

        $this->assertSame('', $result);
    }

    public function testGetStatusClassReturnsEmptyForFreeAgent(): void
    {
        $playerData = new PlayerData();
        $playerData->name = "John Doe";
        $playerData->teamID = 0;
        $playerData->ordinal = 1;
        $playerData->contractCurrentYear = 1;
        $playerData->contractTotalYears = 3;

        $result = $this->decorator->getNameStatusClass($playerData);

        $this->assertSame('', $result);
    }

    public function testWaivedTakesPriorityOverExpiring(): void
    {
        $playerData = new PlayerData();
        $playerData->name = "John Doe";
        $playerData->teamID = 5;
        $playerData->ordinal = \JSB::WAIVERS_ORDINAL + 1;
        $playerData->contractCurrentYear = 3;
        $playerData->contractTotalYears = 3;

        $result = $this->decorator->getNameStatusClass($playerData);

        $this->assertSame('player-waived', $result);
    }
}
