<?php

declare(strict_types=1);

namespace Player;

/**
 * PlayerDataMapper - Maps raw database rows to PlayerData DTOs.
 *
 * Owns the declarative FIELD_MAP and the row→PlayerData transformation logic
 * extracted from PlayerRepository (backlog 7.15). Dependency-free: makes no
 * database calls, so it does not extend BaseMysqliRepository.
 *
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 * @phpstan-import-type HistoricalPlayerRow from \Player\Contracts\PlayerRepositoryInterface
 */
class PlayerDataMapper
{
    /**
     * Declarative field map: PlayerData property → DB column for each mapping category.
     * Used by tests to verify every PlayerData property is accounted for.
     *
     * @var array<string, list<array{target: string, source: string}>>
     */
    public const FIELD_MAP = [
        'basic' => [
            ['target' => 'playerID', 'source' => 'pid'],
            ['target' => 'ordinal', 'source' => 'ordinal'],
            ['target' => 'name', 'source' => 'name'],
            ['target' => 'nickname', 'source' => 'nickname'],
            ['target' => 'age', 'source' => 'age'],
            ['target' => 'teamid', 'source' => 'teamid'],
            ['target' => 'teamName', 'source' => 'teamname'],
            ['target' => 'teamColor1', 'source' => 'color1'],
            ['target' => 'teamColor2', 'source' => 'color2'],
            ['target' => 'position', 'source' => 'pos'],
        ],
        'ratings_current' => [
            ['target' => 'ratingFieldGoalAttempts', 'source' => 'r_fga'],
            ['target' => 'ratingFieldGoalPercentage', 'source' => 'r_fgp'],
            ['target' => 'ratingFreeThrowAttempts', 'source' => 'r_fta'],
            ['target' => 'ratingFreeThrowPercentage', 'source' => 'r_ftp'],
            ['target' => 'ratingThreePointAttempts', 'source' => 'r_3ga'],
            ['target' => 'ratingThreePointPercentage', 'source' => 'r_3gp'],
            ['target' => 'ratingOffensiveRebounds', 'source' => 'r_orb'],
            ['target' => 'ratingDefensiveRebounds', 'source' => 'r_drb'],
            ['target' => 'ratingAssists', 'source' => 'r_ast'],
            ['target' => 'ratingSteals', 'source' => 'r_stl'],
            ['target' => 'ratingTurnovers', 'source' => 'r_tvr'],
            ['target' => 'ratingBlocks', 'source' => 'r_blk'],
            ['target' => 'ratingFouls', 'source' => 'r_foul'],
            ['target' => 'ratingOutsideOffense', 'source' => 'oo'],
            ['target' => 'ratingOutsideDefense', 'source' => 'od'],
            ['target' => 'ratingDriveOffense', 'source' => 'r_drive_off'],
            ['target' => 'ratingDriveDefense', 'source' => 'dd'],
            ['target' => 'ratingPostOffense', 'source' => 'po'],
            ['target' => 'ratingPostDefense', 'source' => 'pd'],
            ['target' => 'ratingTransitionOffense', 'source' => 'r_trans_off'],
            ['target' => 'ratingTransitionDefense', 'source' => 'td'],
            ['target' => 'ratingClutch', 'source' => 'clutch'],
            ['target' => 'ratingConsistency', 'source' => 'consistency'],
            ['target' => 'ratingTalent', 'source' => 'talent'],
            ['target' => 'ratingSkill', 'source' => 'skill'],
            ['target' => 'ratingIntangibles', 'source' => 'intangibles'],
        ],
        'ratings_historical' => [
            ['target' => 'ratingFieldGoalAttempts', 'source' => 'r_2ga'],
            ['target' => 'ratingFieldGoalPercentage', 'source' => 'r_2gp'],
            ['target' => 'ratingFreeThrowAttempts', 'source' => 'r_fta'],
            ['target' => 'ratingFreeThrowPercentage', 'source' => 'r_ftp'],
            ['target' => 'ratingThreePointAttempts', 'source' => 'r_3ga'],
            ['target' => 'ratingThreePointPercentage', 'source' => 'r_3gp'],
            ['target' => 'ratingOffensiveRebounds', 'source' => 'r_orb'],
            ['target' => 'ratingDefensiveRebounds', 'source' => 'r_drb'],
            ['target' => 'ratingAssists', 'source' => 'r_ast'],
            ['target' => 'ratingSteals', 'source' => 'r_stl'],
            ['target' => 'ratingBlocks', 'source' => 'r_blk'],
            ['target' => 'ratingTurnovers', 'source' => 'r_tvr'],
            ['target' => 'ratingOutsideOffense', 'source' => 'r_oo'],
            ['target' => 'ratingOutsideDefense', 'source' => 'r_od'],
            ['target' => 'ratingDriveOffense', 'source' => 'r_drive_off'],
            ['target' => 'ratingDriveDefense', 'source' => 'r_dd'],
            ['target' => 'ratingPostOffense', 'source' => 'r_po'],
            ['target' => 'ratingPostDefense', 'source' => 'r_pd'],
            ['target' => 'ratingTransitionOffense', 'source' => 'r_trans_off'],
            ['target' => 'ratingTransitionDefense', 'source' => 'r_td'],
        ],
        'free_agency' => [
            ['target' => 'freeAgencyLoyalty', 'source' => 'loyalty'],
            ['target' => 'freeAgencyPlayingTime', 'source' => 'playing_time'],
            ['target' => 'freeAgencyPlayForWinner', 'source' => 'winner'],
            ['target' => 'freeAgencyTradition', 'source' => 'tradition'],
            ['target' => 'freeAgencySecurity', 'source' => 'security'],
        ],
        'contract' => [
            ['target' => 'yearsOfExperience', 'source' => 'exp'],
            ['target' => 'birdYears', 'source' => 'bird'],
            ['target' => 'contractCurrentYear', 'source' => 'cy'],
            ['target' => 'contractTotalYears', 'source' => 'cyt'],
            ['target' => 'contractYear1Salary', 'source' => 'salary_yr1'],
            ['target' => 'contractYear2Salary', 'source' => 'salary_yr2'],
            ['target' => 'contractYear3Salary', 'source' => 'salary_yr3'],
            ['target' => 'contractYear4Salary', 'source' => 'salary_yr4'],
            ['target' => 'contractYear5Salary', 'source' => 'salary_yr5'],
            ['target' => 'contractYear6Salary', 'source' => 'salary_yr6'],
        ],
        'draft' => [
            ['target' => 'draftYear', 'source' => 'draftyear'],
            ['target' => 'draftRound', 'source' => 'draftround'],
            ['target' => 'draftPickNumber', 'source' => 'draftpickno'],
            ['target' => 'draftTeamOriginalName', 'source' => 'draftedby'],
            ['target' => 'draftTeamCurrentName', 'source' => 'draftedbycurrentname'],
            ['target' => 'collegeName', 'source' => 'college'],
        ],
        'physical' => [
            ['target' => 'heightFeet', 'source' => 'htft'],
            ['target' => 'heightInches', 'source' => 'htin'],
            ['target' => 'weightPounds', 'source' => 'wt'],
        ],
        'status' => [
            ['target' => 'daysRemainingForInjury', 'source' => 'injured'],
            ['target' => 'isRetired', 'source' => 'retired'],
            ['target' => 'timeDroppedOnWaivers', 'source' => 'droptime'],
        ],
    ];

    /** @var list<string> Properties set by non-repository logic or only in specific code paths */
    public const EXCLUDED_FROM_FIELD_MAP = [
        'plr',
        'historicalYear',
        'currentSeasonSalary',
        'salaryJSB',
        'decoratedName',
        'nameStatusClass',
    ];

    /**
     * Fill a PlayerData object from a current player row
     *
     * @param PlayerRow $plrRow Database row from `ibl_plr`
     */
    public function fillFromCurrentRow(array $plrRow): PlayerData
    {
        $playerData = new PlayerData();

        // Basic player information
        $this->mapBasicFields($playerData, $plrRow);

        // Ratings - use helper to map array of field pairs
        $this->mapRatingsFromCurrentRow($playerData, $plrRow);

        // Free agency preferences
        $this->mapFreeAgencyFields($playerData, $plrRow);

        // Contract information
        $this->mapContractFields($playerData, $plrRow);

        // Draft information
        $this->mapDraftFields($playerData, $plrRow);

        // Physical attributes
        $this->mapPhysicalFields($playerData, $plrRow);

        // Status fields
        $this->mapStatusFields($playerData, $plrRow);

        return $playerData;
    }

    /**
     * Fill a PlayerData object from a historical player row
     *
     * @param HistoricalPlayerRow $plrRow
     */
    public function fillFromHistoricalRow(array $plrRow): PlayerData
    {
        $playerData = new PlayerData();

        // Basic historical player information
        $playerData->playerID = $plrRow['pid'] ?? null;
        $playerData->historicalYear = $plrRow['year'] ?? null;
        $name = $plrRow['name'] ?? null;
        $playerData->name = $name !== null ? stripslashes($name) : null;
        $team = $plrRow['team'] ?? null;
        $playerData->teamName = $team !== null ? stripslashes($team) : null;
        $playerData->teamid = $plrRow['teamid'] ?? null;

        // Ratings from historical row (note different column names)
        $this->mapRatingsFromHistoricalRow($playerData, $plrRow);

        // Salary
        $playerData->salaryJSB = $plrRow['salary'] ?? null;

        // Initialize contract fields for historical data (values are snapshots, not current)
        $playerData->contractCurrentYear = 0;
        $playerData->contractTotalYears = 0;
        $playerData->contractYear1Salary = 0;
        $playerData->contractYear2Salary = 0;
        $playerData->contractYear3Salary = 0;
        $playerData->contractYear4Salary = 0;
        $playerData->contractYear5Salary = 0;
        $playerData->contractYear6Salary = 0;

        return $playerData;
    }

    /**
     * Map basic player fields
     *
     * @param PlayerRow $plrRow Database row from `ibl_plr`
     */
    private function mapBasicFields(PlayerData $playerData, array $plrRow): void
    {
        $playerData->playerID = $plrRow['pid'];
        $playerData->ordinal = $plrRow['ordinal'];
        $playerData->name = stripslashes($plrRow['name']);
        $playerData->nickname = $this->getOptionalStrippedValue($plrRow, 'nickname');
        $playerData->age = $plrRow['age'];
        $playerData->teamid = $plrRow['teamid'];
        $playerData->teamName = isset($plrRow['teamname']) ? stripslashes($plrRow['teamname']) : null;
        /** @var string|null $color1 */
        $color1 = $plrRow['color1'] ?? null;
        $playerData->teamColor1 = $color1;
        /** @var string|null $color2 */
        $color2 = $plrRow['color2'] ?? null;
        $playerData->teamColor2 = $color2;
        $playerData->position = $plrRow['pos'] ?? '';
    }

    /**
     * @param PlayerRow $plrRow Database row from `ibl_plr`
     */
    private function mapRatingsFromCurrentRow(PlayerData $playerData, array $plrRow): void
    {
        $playerData->ratingFieldGoalAttempts = $plrRow['r_fga'];
        $playerData->ratingFieldGoalPercentage = $plrRow['r_fgp'];
        $playerData->ratingFreeThrowAttempts = $plrRow['r_fta'];
        $playerData->ratingFreeThrowPercentage = $plrRow['r_ftp'];
        $playerData->ratingThreePointAttempts = $plrRow['r_3ga'];
        $playerData->ratingThreePointPercentage = $plrRow['r_3gp'];
        $playerData->ratingOffensiveRebounds = $plrRow['r_orb'];
        $playerData->ratingDefensiveRebounds = $plrRow['r_drb'];
        $playerData->ratingAssists = $plrRow['r_ast'];
        $playerData->ratingSteals = $plrRow['r_stl'];
        $playerData->ratingTurnovers = $plrRow['r_tvr'];
        $playerData->ratingBlocks = $plrRow['r_blk'];
        $playerData->ratingFouls = $plrRow['r_foul'];
        $playerData->ratingOutsideOffense = $plrRow['oo'];
        $playerData->ratingOutsideDefense = $plrRow['od'];
        $playerData->ratingDriveOffense = $plrRow['r_drive_off'];
        $playerData->ratingDriveDefense = $plrRow['dd'];
        $playerData->ratingPostOffense = $plrRow['po'];
        $playerData->ratingPostDefense = $plrRow['pd'];
        $playerData->ratingTransitionOffense = $plrRow['r_trans_off'];
        $playerData->ratingTransitionDefense = $plrRow['td'];
        $playerData->ratingClutch = $plrRow['clutch'];
        $playerData->ratingConsistency = $plrRow['consistency'];
        $playerData->ratingTalent = $plrRow['talent'];
        $playerData->ratingSkill = $plrRow['skill'];
        $playerData->ratingIntangibles = $plrRow['intangibles'];
    }

    /**
     * @param PlayerRow $plrRow Database row from `ibl_plr`
     */
    private function mapFreeAgencyFields(PlayerData $playerData, array $plrRow): void
    {
        $playerData->freeAgencyLoyalty = $plrRow['loyalty'];
        $playerData->freeAgencyPlayingTime = $plrRow['playing_time'];
        $playerData->freeAgencyPlayForWinner = $plrRow['winner'];
        $playerData->freeAgencyTradition = $plrRow['tradition'];
        $playerData->freeAgencySecurity = $plrRow['security'];
    }

    /**
     * @param PlayerRow $plrRow Database row from `ibl_plr`
     */
    private function mapContractFields(PlayerData $playerData, array $plrRow): void
    {
        $playerData->yearsOfExperience = $plrRow['exp'];
        $playerData->birdYears = $plrRow['bird'];
        $playerData->contractCurrentYear = $plrRow['cy'];
        $playerData->contractTotalYears = $plrRow['cyt'];
        $playerData->contractYear1Salary = $plrRow['salary_yr1'];
        $playerData->contractYear2Salary = $plrRow['salary_yr2'];
        $playerData->contractYear3Salary = $plrRow['salary_yr3'];
        $playerData->contractYear4Salary = $plrRow['salary_yr4'];
        $playerData->contractYear5Salary = $plrRow['salary_yr5'];
        $playerData->contractYear6Salary = $plrRow['salary_yr6'];
    }

    /**
     * Map draft fields
     *
     * @param PlayerRow $plrRow Database row from `ibl_plr`
     */
    private function mapDraftFields(PlayerData $playerData, array $plrRow): void
    {
        $playerData->draftYear = $plrRow['draftyear'];
        $playerData->draftRound = $plrRow['draftround'];
        $playerData->draftPickNumber = $plrRow['draftpickno'];
        $playerData->draftTeamOriginalName = $this->getOptionalStrippedValue($plrRow, 'draftedby');
        $playerData->draftTeamCurrentName = $this->getOptionalStrippedValue($plrRow, 'draftedbycurrentname');
        $playerData->collegeName = $this->getOptionalStrippedValue($plrRow, 'college');
    }

    /**
     * Map physical attribute fields
     *
     * @param PlayerRow $plrRow Database row from `ibl_plr`
     */
    private function mapPhysicalFields(PlayerData $playerData, array $plrRow): void
    {
        $htft = $plrRow['htft'] ?? null;
        $playerData->heightFeet = $htft !== null ? (int) $htft : null;
        $htin = $plrRow['htin'] ?? null;
        $playerData->heightInches = $htin !== null ? (int) $htin : null;
        $wt = $plrRow['wt'] ?? null;
        $playerData->weightPounds = $wt !== null ? (int) $wt : null;
    }

    /**
     * Map status fields
     *
     * @param PlayerRow $plrRow Database row from `ibl_plr`
     */
    private function mapStatusFields(PlayerData $playerData, array $plrRow): void
    {
        $playerData->daysRemainingForInjury = $plrRow['injured'];
        $playerData->isRetired = $plrRow['retired'] ?? null;
        $playerData->timeDroppedOnWaivers = $plrRow['droptime'];
    }

    /**
     * Helper method to get optional string value with stripslashes, or null if not set/empty
     *
     * @param array<string, mixed> $row
     */
    private function getOptionalStrippedValue(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) || $value === '') {
            return null;
        }
        return stripslashes($value);
    }

    /**
     * @param HistoricalPlayerRow $plrRow
     */
    private function mapRatingsFromHistoricalRow(PlayerData $playerData, array $plrRow): void
    {
        $playerData->ratingFieldGoalAttempts = $plrRow['r_2ga'] ?? null;
        $playerData->ratingFieldGoalPercentage = $plrRow['r_2gp'] ?? null;
        $playerData->ratingFreeThrowAttempts = $plrRow['r_fta'] ?? null;
        $playerData->ratingFreeThrowPercentage = $plrRow['r_ftp'] ?? null;
        $playerData->ratingThreePointAttempts = $plrRow['r_3ga'] ?? null;
        $playerData->ratingThreePointPercentage = $plrRow['r_3gp'] ?? null;
        $playerData->ratingOffensiveRebounds = $plrRow['r_orb'] ?? null;
        $playerData->ratingDefensiveRebounds = $plrRow['r_drb'] ?? null;
        $playerData->ratingAssists = $plrRow['r_ast'] ?? null;
        $playerData->ratingSteals = $plrRow['r_stl'] ?? null;
        $playerData->ratingBlocks = $plrRow['r_blk'] ?? null;
        $playerData->ratingTurnovers = $plrRow['r_tvr'] ?? null;
        $playerData->ratingOutsideOffense = $plrRow['r_oo'] ?? null;
        $playerData->ratingOutsideDefense = $plrRow['r_od'] ?? null;
        $playerData->ratingDriveOffense = $plrRow['r_drive_off'] ?? null;
        $playerData->ratingDriveDefense = $plrRow['r_dd'] ?? null;
        $playerData->ratingPostOffense = $plrRow['r_po'] ?? null;
        $playerData->ratingPostDefense = $plrRow['r_pd'] ?? null;
        $playerData->ratingTransitionOffense = $plrRow['r_trans_off'] ?? null;
        $playerData->ratingTransitionDefense = $plrRow['r_td'] ?? null;
    }
}
