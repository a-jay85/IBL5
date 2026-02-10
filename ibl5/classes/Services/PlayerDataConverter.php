<?php

declare(strict_types=1);

namespace Services;

use Player\PlayerData;

/**
 * PlayerDataConverter - Converts raw player data arrays to PlayerData objects
 * 
 * This service provides a single location for converting between database array formats
 * and the typed PlayerData object. It's used across multiple modules that need to work
 * with player contract and salary information.
 */
class PlayerDataConverter
{
    /**
     * Convert player array data to PlayerData object
     *
     * @param array{cy?: int|string|null, cyt?: int|string|null, cy1?: int|string|null, cy2?: int|string|null, cy3?: int|string|null, cy4?: int|string|null, cy5?: int|string|null, cy6?: int|string|null, exp?: int|string|null} $playerData Raw player data array from database
     * @return PlayerData PlayerData object with contract and salary data
     */
    public static function arrayToPlayerData(array $playerData): PlayerData
    {
        $data = new PlayerData();
        $data->contractCurrentYear = (int) ($playerData['cy'] ?? 0);
        $data->contractTotalYears = (int) ($playerData['cyt'] ?? 0);
        $data->contractYear1Salary = (int) ($playerData['cy1'] ?? 0);
        $data->contractYear2Salary = (int) ($playerData['cy2'] ?? 0);
        $data->contractYear3Salary = (int) ($playerData['cy3'] ?? 0);
        $data->contractYear4Salary = (int) ($playerData['cy4'] ?? 0);
        $data->contractYear5Salary = (int) ($playerData['cy5'] ?? 0);
        $data->contractYear6Salary = (int) ($playerData['cy6'] ?? 0);
        $data->yearsOfExperience = (int) ($playerData['exp'] ?? 0);
        return $data;
    }
}
