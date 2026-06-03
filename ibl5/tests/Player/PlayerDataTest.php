<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Player\PlayerData;

class PlayerDataTest extends TestCase
{
    private function playerWithSalaries(): PlayerData
    {
        $playerData = new PlayerData();
        $playerData->contractYear1Salary = 100;
        $playerData->contractYear2Salary = 110;
        $playerData->contractYear3Salary = 120;
        $playerData->contractYear4Salary = 130;
        $playerData->contractYear5Salary = 140;
        $playerData->contractYear6Salary = 150;

        return $playerData;
    }

    #[DataProvider('contractYearProvider')]
    public function testSalaryForContractYearReturnsMatchingSlot(int $contractYear, int $expected): void
    {
        $this->assertSame($expected, $this->playerWithSalaries()->salaryForContractYear($contractYear));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function contractYearProvider(): array
    {
        return [
            'year 1' => [1, 100],
            'year 2' => [2, 110],
            'year 3' => [3, 120],
            'year 4' => [4, 130],
            'year 5' => [5, 140],
            'year 6' => [6, 150],
            'year 0 (out of range) returns 0' => [0, 0],
            'year 7 (out of range) returns 0' => [7, 0],
            'negative year returns 0' => [-1, 0],
        ];
    }

    /**
     * Contract-year slots default to null on a fresh PlayerData; every arm must
     * coalesce null to 0 rather than returning null. Covering each year (not
     * just year 1) locks the `?? 0` on every match arm.
     */
    #[DataProvider('nullSlotYearProvider')]
    public function testSalaryForContractYearReturnsZeroWhenSlotIsNull(int $contractYear): void
    {
        $this->assertSame(0, (new PlayerData())->salaryForContractYear($contractYear));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function nullSlotYearProvider(): array
    {
        return [
            'year 1 null' => [1],
            'year 2 null' => [2],
            'year 3 null' => [3],
            'year 4 null' => [4],
            'year 5 null' => [5],
            'year 6 null' => [6],
        ];
    }
}
