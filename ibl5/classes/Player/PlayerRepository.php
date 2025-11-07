<?php

namespace Player;

/**
 * PlayerRepository - Handles data loading and persistence for players
 * 
 * This class follows the Repository pattern, encapsulating data access logic.
 * It's responsible for loading player data from the database and populating PlayerData objects.
 */
class PlayerRepository
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Load a player by their ID from the current player table
     */
    public function loadByID(int $playerID): PlayerData
    {
        $query = "SELECT *
            FROM ibl_plr
            WHERE pid = $playerID
            LIMIT 1;";
        $result = $this->db->sql_query($query);
        $plrRow = $this->db->sql_fetch_assoc($result);
        return $this->fillFromCurrentRow($plrRow);
    }

    /**
     * Fill a PlayerData object from a current player row
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
     * Map basic player fields
     */
    private function mapBasicFields(PlayerData $playerData, array $plrRow): void
    {
        $playerData->playerID = $plrRow['pid'];
        $playerData->ordinal = $plrRow['ordinal'];
        $playerData->name = stripslashes($plrRow['name']);
        $playerData->nickname = $this->getOptionalStrippedValue($plrRow, 'nickname');
        $playerData->age = $plrRow['age'];
        $playerData->teamID = $plrRow['tid'];
        $playerData->teamName = stripslashes($plrRow['teamname']);
        $playerData->position = $plrRow['pos'];
    }

    /**
     * Map rating fields from current player row
     */
    private function mapRatingsFromCurrentRow(PlayerData $playerData, array $plrRow): void
    {
        $playerData->ratingFieldGoalAttempts = $plrRow['r_fga'];
        $playerData->ratingFieldGoalPercentage = $plrRow['r_fgp'];
        $playerData->ratingFreeThrowAttempts = $plrRow['r_fta'];
        $playerData->ratingFreeThrowPercentage = $plrRow['r_ftp'];
        $playerData->ratingThreePointAttempts = $plrRow['r_tga'];
        $playerData->ratingThreePointPercentage = $plrRow['r_tgp'];
        $playerData->ratingOffensiveRebounds = $plrRow['r_orb'];
        $playerData->ratingDefensiveRebounds = $plrRow['r_drb'];
        $playerData->ratingAssists = $plrRow['r_ast'];
        $playerData->ratingSteals = $plrRow['r_stl'];
        $playerData->ratingTurnovers = $plrRow['r_to'];
        $playerData->ratingBlocks = $plrRow['r_blk'];
        $playerData->ratingFouls = $plrRow['r_foul'];
        $playerData->ratingOutsideOffense = $plrRow['oo'];
        $playerData->ratingOutsideDefense = $plrRow['od'];
        $playerData->ratingDriveOffense = $plrRow['do'];
        $playerData->ratingDriveDefense = $plrRow['dd'];
        $playerData->ratingPostOffense = $plrRow['po'];
        $playerData->ratingPostDefense = $plrRow['pd'];
        $playerData->ratingTransitionOffense = $plrRow['to'];
        $playerData->ratingTransitionDefense = $plrRow['td'];
        $playerData->ratingClutch = $plrRow['Clutch'];
        $playerData->ratingConsistency = $plrRow['Consistency'];
        $playerData->ratingTalent = $plrRow['talent'];
        $playerData->ratingSkill = $plrRow['skill'];
        $playerData->ratingIntangibles = $plrRow['intangibles'];
    }

    /**
     * Map free agency preference fields
     */
    private function mapFreeAgencyFields(PlayerData $playerData, array $plrRow): void
    {
        $playerData->freeAgencyLoyalty = $plrRow['loyalty'];
        $playerData->freeAgencyPlayingTime = $plrRow['playingTime'];
        $playerData->freeAgencyPlayForWinner = $plrRow['winner'];
        $playerData->freeAgencyTradition = $plrRow['tradition'];
        $playerData->freeAgencySecurity = $plrRow['security'];
    }

    /**
     * Map contract fields
     */
    private function mapContractFields(PlayerData $playerData, array $plrRow): void
    {
        $playerData->yearsOfExperience = $plrRow['exp'];
        $playerData->birdYears = $plrRow['bird'];
        $playerData->contractCurrentYear = $plrRow['cy'];
        $playerData->contractTotalYears = $plrRow['cyt'];
        $playerData->contractYear1Salary = $plrRow['cy1'];
        $playerData->contractYear2Salary = $plrRow['cy2'];
        $playerData->contractYear3Salary = $plrRow['cy3'];
        $playerData->contractYear4Salary = $plrRow['cy4'];
        $playerData->contractYear5Salary = $plrRow['cy5'];
        $playerData->contractYear6Salary = $plrRow['cy6'];
    }

    /**
     * Map draft fields
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
     */
    private function mapPhysicalFields(PlayerData $playerData, array $plrRow): void
    {
        $playerData->heightFeet = $plrRow['htft'];
        $playerData->heightInches = $plrRow['htin'];
        $playerData->weightPounds = $plrRow['wt'];
    }

    /**
     * Map status fields
     */
    private function mapStatusFields(PlayerData $playerData, array $plrRow): void
    {
        $playerData->daysRemainingForInjury = $plrRow['injured'];
        $playerData->isRetired = $plrRow['retired'];
        $playerData->timeDroppedOnWaivers = $plrRow['droptime'];
    }

    /**
     * Helper method to get optional string value with stripslashes, or null if not set/empty
     */
    private function getOptionalStrippedValue(array $row, string $key): ?string
    {
        return (isset($row[$key]) && $row[$key] !== '') ? stripslashes($row[$key]) : null;
    }

    /**
     * Fill a PlayerData object from a historical player row
     */
    public function fillFromHistoricalRow(array $plrRow): PlayerData
    {
        $playerData = new PlayerData();

        // Basic historical player information
        $playerData->playerID = $plrRow['pid'];
        $playerData->historicalYear = $plrRow['year'];
        $playerData->name = stripslashes($plrRow['name']);
        $playerData->teamName = stripslashes($plrRow['team']);
        $playerData->teamID = $plrRow['teamid'];
        
        // Ratings from historical row (note different column names)
        $this->mapRatingsFromHistoricalRow($playerData, $plrRow);
        
        // Salary
        $playerData->salaryJSB = $plrRow['salary'];

        return $playerData;
    }

    /**
     * Map rating fields from historical player row (different column names than current)
     */
    private function mapRatingsFromHistoricalRow(PlayerData $playerData, array $plrRow): void
    {
        $playerData->ratingFieldGoalAttempts = $plrRow['r_2ga'];
        $playerData->ratingFieldGoalPercentage = $plrRow['r_2gp'];
        $playerData->ratingFreeThrowAttempts = $plrRow['r_fta'];
        $playerData->ratingFreeThrowPercentage = $plrRow['r_ftp'];
        $playerData->ratingThreePointAttempts = $plrRow['r_3ga'];
        $playerData->ratingThreePointPercentage = $plrRow['r_3gp'];
        $playerData->ratingOffensiveRebounds = $plrRow['r_orb'];
        $playerData->ratingDefensiveRebounds = $plrRow['r_drb'];
        $playerData->ratingAssists = $plrRow['r_ast'];
        $playerData->ratingSteals = $plrRow['r_stl'];
        $playerData->ratingBlocks = $plrRow['r_blk'];
        $playerData->ratingTurnovers = $plrRow['r_tvr'];
        $playerData->ratingOutsideOffense = $plrRow['r_oo'];
        $playerData->ratingOutsideDefense = $plrRow['r_od'];
        $playerData->ratingDriveOffense = $plrRow['r_do'];
        $playerData->ratingDriveDefense = $plrRow['r_dd'];
        $playerData->ratingPostOffense = $plrRow['r_po'];
        $playerData->ratingPostDefense = $plrRow['r_pd'];
        $playerData->ratingTransitionOffense = $plrRow['r_to'];
        $playerData->ratingTransitionDefense = $plrRow['r_td'];
    }

    /**
     * Query free agency demands for a player
     */
    public function getFreeAgencyDemands(string $playerName)
    {
        $query = "SELECT *
            FROM ibl_demands
            WHERE name=\"$playerName\"";
        return $this->db->sql_query($query);
    }
}
