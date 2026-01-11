<?php

declare(strict_types=1);

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Services\PlayerDataConverter;
use Player\PlayerData;

/**
 * PlayerDataConverterTest - Tests for PlayerDataConverter
 */
class PlayerDataConverterTest extends TestCase
{
    // ============================================
    // ARRAY TO PLAYER DATA TESTS
    // ============================================

    public function testArrayToPlayerDataReturnsPlayerDataObject(): void
    {
        $playerData = ['cy' => 1, 'cyt' => 3, 'cy1' => 5000000];

        $result = PlayerDataConverter::arrayToPlayerData($playerData);

        $this->assertInstanceOf(PlayerData::class, $result);
    }

    public function testArrayToPlayerDataSetsContractCurrentYear(): void
    {
        $playerData = ['cy' => 2];

        $result = PlayerDataConverter::arrayToPlayerData($playerData);

        $this->assertSame(2, $result->contractCurrentYear);
    }

    public function testArrayToPlayerDataSetsContractTotalYears(): void
    {
        $playerData = ['cyt' => 4];

        $result = PlayerDataConverter::arrayToPlayerData($playerData);

        $this->assertSame(4, $result->contractTotalYears);
    }

    public function testArrayToPlayerDataSetsContractYear1Salary(): void
    {
        $playerData = ['cy1' => 5000000];

        $result = PlayerDataConverter::arrayToPlayerData($playerData);

        $this->assertSame(5000000, $result->contractYear1Salary);
    }

    public function testArrayToPlayerDataSetsContractYear2Salary(): void
    {
        $playerData = ['cy2' => 5500000];

        $result = PlayerDataConverter::arrayToPlayerData($playerData);

        $this->assertSame(5500000, $result->contractYear2Salary);
    }

    public function testArrayToPlayerDataSetsContractYear3Salary(): void
    {
        $playerData = ['cy3' => 6000000];

        $result = PlayerDataConverter::arrayToPlayerData($playerData);

        $this->assertSame(6000000, $result->contractYear3Salary);
    }

    public function testArrayToPlayerDataSetsContractYear4Salary(): void
    {
        $playerData = ['cy4' => 6500000];

        $result = PlayerDataConverter::arrayToPlayerData($playerData);

        $this->assertSame(6500000, $result->contractYear4Salary);
    }

    public function testArrayToPlayerDataSetsContractYear5Salary(): void
    {
        $playerData = ['cy5' => 7000000];

        $result = PlayerDataConverter::arrayToPlayerData($playerData);

        $this->assertSame(7000000, $result->contractYear5Salary);
    }

    public function testArrayToPlayerDataSetsContractYear6Salary(): void
    {
        $playerData = ['cy6' => 7500000];

        $result = PlayerDataConverter::arrayToPlayerData($playerData);

        $this->assertSame(7500000, $result->contractYear6Salary);
    }

    public function testArrayToPlayerDataSetsYearsOfExperience(): void
    {
        $playerData = ['exp' => 5];

        $result = PlayerDataConverter::arrayToPlayerData($playerData);

        $this->assertSame(5, $result->yearsOfExperience);
    }

    public function testArrayToPlayerDataHandlesEmptyArray(): void
    {
        $playerData = [];

        $result = PlayerDataConverter::arrayToPlayerData($playerData);

        $this->assertSame(0, $result->contractCurrentYear);
        $this->assertSame(0, $result->contractTotalYears);
        $this->assertSame(0, $result->contractYear1Salary);
    }

    public function testArrayToPlayerDataCastsStringValues(): void
    {
        $playerData = [
            'cy' => '2',
            'cyt' => '4',
            'cy1' => '5000000',
        ];

        $result = PlayerDataConverter::arrayToPlayerData($playerData);

        $this->assertSame(2, $result->contractCurrentYear);
        $this->assertSame(4, $result->contractTotalYears);
        $this->assertSame(5000000, $result->contractYear1Salary);
    }

    public function testArrayToPlayerDataWithCompleteContract(): void
    {
        $playerData = [
            'cy' => 1,
            'cyt' => 6,
            'cy1' => 10000000,
            'cy2' => 11000000,
            'cy3' => 12000000,
            'cy4' => 13000000,
            'cy5' => 14000000,
            'cy6' => 15000000,
            'exp' => 10,
        ];

        $result = PlayerDataConverter::arrayToPlayerData($playerData);

        $this->assertSame(1, $result->contractCurrentYear);
        $this->assertSame(6, $result->contractTotalYears);
        $this->assertSame(10000000, $result->contractYear1Salary);
        $this->assertSame(11000000, $result->contractYear2Salary);
        $this->assertSame(12000000, $result->contractYear3Salary);
        $this->assertSame(13000000, $result->contractYear4Salary);
        $this->assertSame(14000000, $result->contractYear5Salary);
        $this->assertSame(15000000, $result->contractYear6Salary);
        $this->assertSame(10, $result->yearsOfExperience);
    }
}
