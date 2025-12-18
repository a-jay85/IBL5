<?php

namespace Player;

use BaseMysqliRepository;
use Player\Contracts\PlayerRepositoryInterface;

/**
 * PlayerRepository - Database operations for player data
 * 
 * Extends BaseMysqliRepository for standardized prepared statement handling.
 * 
 * @see PlayerRepositoryInterface For method contracts
 * @see BaseMysqliRepository For base class documentation and error codes
 */
class PlayerRepository extends BaseMysqliRepository implements PlayerRepositoryInterface
{
    /**
     * Constructor - inherits from BaseMysqliRepository
     * 
     * @param object $db Active mysqli connection (or duck-typed mock during migration)
     * @throws \RuntimeException If connection is invalid (error code 1002)
     * 
     * TEMPORARY: Accepts duck-typed objects during mysqli migration for testing.
     * Will be strictly \mysqli once migration completes.
     */
    public function __construct(object $db)
    {
        parent::__construct($db);
    }

    /**
     * Load a player by their ID from the current player table
     * 
     * Uses fetchOne from BaseMysqliRepository with prepared statement.
     */
    public function loadByID(int $playerID): PlayerData
    {
        $plrRow = $this->fetchOne(
            "SELECT * FROM ibl_plr WHERE pid = ? LIMIT 1",
            "i",
            $playerID
        );
        
        if ($plrRow === null) {
            throw new \RuntimeException("Player with ID $playerID not found");
        }
        
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
        $playerData->yearsOfExperience = (int) $plrRow['exp'];
        $playerData->birdYears = (int) $plrRow['bird'];
        $playerData->contractCurrentYear = (int) $plrRow['cy'];
        $playerData->contractTotalYears = (int) $plrRow['cyt'];
        $playerData->contractYear1Salary = (int) $plrRow['cy1'];
        $playerData->contractYear2Salary = (int) $plrRow['cy2'];
        $playerData->contractYear3Salary = (int) $plrRow['cy3'];
        $playerData->contractYear4Salary = (int) $plrRow['cy4'];
        $playerData->contractYear5Salary = (int) $plrRow['cy5'];
        $playerData->contractYear6Salary = (int) $plrRow['cy6'];
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
        $value = $row[$key] ?? null;
        return ($value !== null && $value !== '') ? stripslashes($value) : null;
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
     * @see PlayerRepositoryInterface::getFreeAgencyDemands()
     * 
     * Uses fetchOne from BaseMysqliRepository with prepared statement.
     */
    public function getFreeAgencyDemands(string $playerName): array
    {
        $row = $this->fetchOne(
            "SELECT * FROM ibl_demands WHERE name = ?",
            "s",
            $playerName
        );
        
        // Return demand array or empty array with all keys set to 0
        if ($row) {
            return [
                'dem1' => (int) ($row['dem1'] ?? 0),
                'dem2' => (int) ($row['dem2'] ?? 0),
                'dem3' => (int) ($row['dem3'] ?? 0),
                'dem4' => (int) ($row['dem4'] ?? 0),
                'dem5' => (int) ($row['dem5'] ?? 0),
                'dem6' => (int) ($row['dem6'] ?? 0),
            ];
        }
        
        return [
            'dem1' => 0,
            'dem2' => 0,
            'dem3' => 0,
            'dem4' => 0,
            'dem5' => 0,
            'dem6' => 0,
        ];
    }

    /**
     * Get All-Star Game appearances count for a player
     * 
     * @param string $playerName Player name to search for
     * @return int Number of All-Star Game appearances
     */
    public function getAllStarGameCount(string $playerName): int
    {
        $rows = $this->fetchAll(
            "SELECT * FROM ibl_awards WHERE name = ? AND Award LIKE '%Conference All-Star'",
            "s",
            $playerName
        );
        return count($rows);
    }

    /**
     * Get Three-Point Contest appearances count for a player
     * 
     * @param string $playerName Player name to search for
     * @return int Number of Three-Point Contest appearances
     */
    public function getThreePointContestCount(string $playerName): int
    {
        $rows = $this->fetchAll(
            "SELECT * FROM ibl_awards WHERE name = ? AND Award LIKE 'Three-Point Contest%'",
            "s",
            $playerName
        );
        return count($rows);
    }

    /**
     * Get Slam Dunk Competition appearances count for a player
     * 
     * @param string $playerName Player name to search for
     * @return int Number of Slam Dunk Competition appearances
     */
    public function getDunkContestCount(string $playerName): int
    {
        $rows = $this->fetchAll(
            "SELECT * FROM ibl_awards WHERE name = ? AND Award LIKE 'Slam Dunk Competition%'",
            "s",
            $playerName
        );
        return count($rows);
    }

    /**
     * Get Rookie-Sophomore Challenge appearances count for a player
     * 
     * @param string $playerName Player name to search for
     * @return int Number of Rookie-Sophomore Challenge appearances
     */
    public function getRookieSophChallengeCount(string $playerName): int
    {
        $rows = $this->fetchAll(
            "SELECT * FROM ibl_awards WHERE name = ? AND Award LIKE 'Rookie-Sophomore Challenge'",
            "s",
            $playerName
        );
        return count($rows);
    }

    /**
     * Get all sim dates ordered by sim number
     * 
     * @return array Array of sim date records
     */
    public function getAllSimDates(): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_sim_dates ORDER BY sim ASC",
            ""
        );
    }

    /**
     * Get box scores for a player between specific dates
     * 
     * @param int $playerID Player ID
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return array Array of box score records
     */
    public function getBoxScoresBetweenDates(int $playerID, string $startDate, string $endDate): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_box_scores WHERE pid = ? AND Date BETWEEN ? AND ? ORDER BY Date ASC",
            "iss",
            $playerID,
            $startDate,
            $endDate
        );
    }

    /**
     * Get historical stats for a player ordered by year
     * 
     * @param int $playerID Player ID
     * @return array Array of historical stat records
     */
    public function getHistoricalStats(int $playerID): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_hist WHERE pid = ? ORDER BY year ASC",
            "i",
            $playerID
        );
    }

    /**
     * Get playoff stats for a player ordered by year
     * 
     * @param string $playerName Player name
     * @return array Array of playoff stat records
     */
    public function getPlayoffStats(string $playerName): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_playoff_stats WHERE name = ? ORDER BY year ASC",
            "s",
            $playerName
        );
    }

    /**
     * Get heat stats for a player ordered by year
     * 
     * @param string $playerName Player name
     * @return array Array of heat stat records
     */
    public function getHeatStats(string $playerName): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_heat_stats WHERE name = ? ORDER BY year ASC",
            "s",
            $playerName
        );
    }

    /**
     * Get Olympics stats for a player ordered by year
     * 
     * @param string $playerName Player name
     * @return array Array of Olympics stat records
     */
    public function getOlympicsStats(string $playerName): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_olympics_stats WHERE name = ? ORDER BY year ASC",
            "s",
            $playerName
        );
    }

    /**
     * Get all awards for a player ordered by year
     * 
     * @param string $playerName Player name
     * @return array Array of award records
     */
    public function getAwards(string $playerName): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_awards WHERE name = ? ORDER BY year ASC",
            "s",
            $playerName
        );
    }
}
