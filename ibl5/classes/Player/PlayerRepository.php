<?php

declare(strict_types=1);

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
        $playerData->playerID = isset($plrRow['pid']) ? (int) $plrRow['pid'] : null;
        $playerData->ordinal = isset($plrRow['ordinal']) ? (int) $plrRow['ordinal'] : null;
        $playerData->name = isset($plrRow['name']) ? stripslashes($plrRow['name']) : null;
        $playerData->nickname = $this->getOptionalStrippedValue($plrRow, 'nickname');
        $playerData->age = isset($plrRow['age']) ? (int) $plrRow['age'] : null;
        $playerData->teamID = isset($plrRow['tid']) ? (int) $plrRow['tid'] : null;
        $playerData->teamName = isset($plrRow['teamname']) ? stripslashes($plrRow['teamname']) : null;
        $playerData->teamColor1 = $plrRow['color1'] ?? null;
        $playerData->teamColor2 = $plrRow['color2'] ?? null;
        $playerData->position = $plrRow['pos'] ?? null;
    }

    /**
     * Map rating fields from current player row
     */
    private function mapRatingsFromCurrentRow(PlayerData $playerData, array $plrRow): void
    {
        $playerData->ratingFieldGoalAttempts = isset($plrRow['r_fga']) ? (int) $plrRow['r_fga'] : null;
        $playerData->ratingFieldGoalPercentage = isset($plrRow['r_fgp']) ? (int) $plrRow['r_fgp'] : null;
        $playerData->ratingFreeThrowAttempts = isset($plrRow['r_fta']) ? (int) $plrRow['r_fta'] : null;
        $playerData->ratingFreeThrowPercentage = isset($plrRow['r_ftp']) ? (int) $plrRow['r_ftp'] : null;
        $playerData->ratingThreePointAttempts = isset($plrRow['r_tga']) ? (int) $plrRow['r_tga'] : null;
        $playerData->ratingThreePointPercentage = isset($plrRow['r_tgp']) ? (int) $plrRow['r_tgp'] : null;
        $playerData->ratingOffensiveRebounds = isset($plrRow['r_orb']) ? (int) $plrRow['r_orb'] : null;
        $playerData->ratingDefensiveRebounds = isset($plrRow['r_drb']) ? (int) $plrRow['r_drb'] : null;
        $playerData->ratingAssists = isset($plrRow['r_ast']) ? (int) $plrRow['r_ast'] : null;
        $playerData->ratingSteals = isset($plrRow['r_stl']) ? (int) $plrRow['r_stl'] : null;
        $playerData->ratingTurnovers = isset($plrRow['r_to']) ? (int) $plrRow['r_to'] : null;
        $playerData->ratingBlocks = isset($plrRow['r_blk']) ? (int) $plrRow['r_blk'] : null;
        $playerData->ratingFouls = isset($plrRow['r_foul']) ? (int) $plrRow['r_foul'] : null;
        $playerData->ratingOutsideOffense = isset($plrRow['oo']) ? (int) $plrRow['oo'] : null;
        $playerData->ratingOutsideDefense = isset($plrRow['od']) ? (int) $plrRow['od'] : null;
        $playerData->ratingDriveOffense = isset($plrRow['do']) ? (int) $plrRow['do'] : null;
        $playerData->ratingDriveDefense = isset($plrRow['dd']) ? (int) $plrRow['dd'] : null;
        $playerData->ratingPostOffense = isset($plrRow['po']) ? (int) $plrRow['po'] : null;
        $playerData->ratingPostDefense = isset($plrRow['pd']) ? (int) $plrRow['pd'] : null;
        $playerData->ratingTransitionOffense = isset($plrRow['to']) ? (int) $plrRow['to'] : null;
        $playerData->ratingTransitionDefense = isset($plrRow['td']) ? (int) $plrRow['td'] : null;
        $playerData->ratingClutch = isset($plrRow['Clutch']) ? (int) $plrRow['Clutch'] : null;
        $playerData->ratingConsistency = isset($plrRow['Consistency']) ? (int) $plrRow['Consistency'] : null;
        $playerData->ratingTalent = isset($plrRow['talent']) ? (int) $plrRow['talent'] : null;
        $playerData->ratingSkill = isset($plrRow['skill']) ? (int) $plrRow['skill'] : null;
        $playerData->ratingIntangibles = isset($plrRow['intangibles']) ? (int) $plrRow['intangibles'] : null;
    }

    /**
     * Map free agency preference fields
     */
    private function mapFreeAgencyFields(PlayerData $playerData, array $plrRow): void
    {
        $playerData->freeAgencyLoyalty = isset($plrRow['loyalty']) ? (int) $plrRow['loyalty'] : null;
        $playerData->freeAgencyPlayingTime = isset($plrRow['playingTime']) ? (int) $plrRow['playingTime'] : null;
        $playerData->freeAgencyPlayForWinner = isset($plrRow['winner']) ? (int) $plrRow['winner'] : null;
        $playerData->freeAgencyTradition = isset($plrRow['tradition']) ? (int) $plrRow['tradition'] : null;
        $playerData->freeAgencySecurity = isset($plrRow['security']) ? (int) $plrRow['security'] : null;
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
        $playerData->draftYear = isset($plrRow['draftyear']) ? (int) $plrRow['draftyear'] : null;
        $playerData->draftRound = isset($plrRow['draftround']) ? (int) $plrRow['draftround'] : null;
        $playerData->draftPickNumber = isset($plrRow['draftpickno']) ? (int) $plrRow['draftpickno'] : null;
        $playerData->draftTeamOriginalName = $this->getOptionalStrippedValue($plrRow, 'draftedby');
        $playerData->draftTeamCurrentName = $this->getOptionalStrippedValue($plrRow, 'draftedbycurrentname');
        $playerData->collegeName = $this->getOptionalStrippedValue($plrRow, 'college');
    }

    /**
     * Map physical attribute fields
     */
    private function mapPhysicalFields(PlayerData $playerData, array $plrRow): void
    {
        $playerData->heightFeet = isset($plrRow['htft']) ? (int) $plrRow['htft'] : null;
        $playerData->heightInches = isset($plrRow['htin']) ? (int) $plrRow['htin'] : null;
        $playerData->weightPounds = isset($plrRow['wt']) ? (int) $plrRow['wt'] : null;
    }

    /**
     * Map status fields
     */
    private function mapStatusFields(PlayerData $playerData, array $plrRow): void
    {
        $playerData->daysRemainingForInjury = isset($plrRow['injured']) ? (int) $plrRow['injured'] : null;
        $playerData->isRetired = isset($plrRow['retired']) ? (string) $plrRow['retired'] : null;
        $playerData->timeDroppedOnWaivers = isset($plrRow['droptime']) ? (int) $plrRow['droptime'] : null;
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
        $playerData->playerID = isset($plrRow['pid']) ? (int) $plrRow['pid'] : null;
        $playerData->historicalYear = isset($plrRow['year']) ? (int) $plrRow['year'] : null;
        $playerData->name = isset($plrRow['name']) ? stripslashes($plrRow['name']) : null;
        $playerData->teamName = isset($plrRow['team']) ? stripslashes($plrRow['team']) : null;
        $playerData->teamID = isset($plrRow['teamid']) ? (int) $plrRow['teamid'] : null;

        // Ratings from historical row (note different column names)
        $this->mapRatingsFromHistoricalRow($playerData, $plrRow);

        // Salary
        $playerData->salaryJSB = isset($plrRow['salary']) ? (int) $plrRow['salary'] : null;

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
        $playerData->ratingFieldGoalAttempts = isset($plrRow['r_2ga']) ? (int) $plrRow['r_2ga'] : null;
        $playerData->ratingFieldGoalPercentage = isset($plrRow['r_2gp']) ? (int) $plrRow['r_2gp'] : null;
        $playerData->ratingFreeThrowAttempts = isset($plrRow['r_fta']) ? (int) $plrRow['r_fta'] : null;
        $playerData->ratingFreeThrowPercentage = isset($plrRow['r_ftp']) ? (int) $plrRow['r_ftp'] : null;
        $playerData->ratingThreePointAttempts = isset($plrRow['r_3ga']) ? (int) $plrRow['r_3ga'] : null;
        $playerData->ratingThreePointPercentage = isset($plrRow['r_3gp']) ? (int) $plrRow['r_3gp'] : null;
        $playerData->ratingOffensiveRebounds = isset($plrRow['r_orb']) ? (int) $plrRow['r_orb'] : null;
        $playerData->ratingDefensiveRebounds = isset($plrRow['r_drb']) ? (int) $plrRow['r_drb'] : null;
        $playerData->ratingAssists = isset($plrRow['r_ast']) ? (int) $plrRow['r_ast'] : null;
        $playerData->ratingSteals = isset($plrRow['r_stl']) ? (int) $plrRow['r_stl'] : null;
        $playerData->ratingBlocks = isset($plrRow['r_blk']) ? (int) $plrRow['r_blk'] : null;
        $playerData->ratingTurnovers = isset($plrRow['r_tvr']) ? (int) $plrRow['r_tvr'] : null;
        $playerData->ratingOutsideOffense = isset($plrRow['r_oo']) ? (int) $plrRow['r_oo'] : null;
        $playerData->ratingOutsideDefense = isset($plrRow['r_od']) ? (int) $plrRow['r_od'] : null;
        $playerData->ratingDriveOffense = isset($plrRow['r_do']) ? (int) $plrRow['r_do'] : null;
        $playerData->ratingDriveDefense = isset($plrRow['r_dd']) ? (int) $plrRow['r_dd'] : null;
        $playerData->ratingPostOffense = isset($plrRow['r_po']) ? (int) $plrRow['r_po'] : null;
        $playerData->ratingPostDefense = isset($plrRow['r_pd']) ? (int) $plrRow['r_pd'] : null;
        $playerData->ratingTransitionOffense = isset($plrRow['r_to']) ? (int) $plrRow['r_to'] : null;
        $playerData->ratingTransitionDefense = isset($plrRow['r_td']) ? (int) $plrRow['r_td'] : null;
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
     * Returns all simulation date ranges from ibl_sim_dates table.
     * Note: 'Start Date' and 'End Date' columns are DATE type in schema.
     * 
     * @return array Array of sim date records with keys: Sim (int), 'Start Date' (string YYYY-MM-DD), 'End Date' (string YYYY-MM-DD)
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

    /**
     * Get player statistics by player ID
     *
     * @see PlayerRepositoryInterface::getPlayerStats()
     * @param int $playerID Player ID
     * @return array|null Player statistics
     */
    public function getPlayerStats(int $playerID): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM ibl_plr WHERE pid = ? LIMIT 1",
            "i",
            $playerID
        );
    }

    /**
     * Get news articles mentioning a player
     * 
     * @see PlayerRepositoryInterface::getPlayerNews()
     */
    public function getPlayerNews(string $playerName): array
    {
        $searchPattern = '%' . $playerName . '%';
        $searchPatternII = '%' . $playerName . ' II%';
        
        return $this->fetchAll(
            "SELECT sid, title, time FROM nuke_stories 
             WHERE (hometext LIKE ? OR bodytext LIKE ?) 
             AND (hometext NOT LIKE ? OR bodytext NOT LIKE ?) 
             ORDER BY time DESC",
            "ssss",
            $searchPattern,
            $searchPattern,
            $searchPatternII,
            $searchPatternII
        );
    }

    /**
     * Get one-on-one game wins for a player
     * 
     * @see PlayerRepositoryInterface::getOneOnOneWins()
     */
    public function getOneOnOneWins(string $playerName): array
    {
        return $this->fetchAll(
            "SELECT o.gameid, o.winner, o.loser, o.winscore, o.lossscore, p.pid as loser_pid 
             FROM ibl_one_on_one o 
             LEFT JOIN ibl_plr p ON o.loser = p.name 
             WHERE o.winner = ? 
             ORDER BY o.gameid ASC",
            "s",
            $playerName
        );
    }

    /**
     * Get one-on-one game losses for a player
     * 
     * @see PlayerRepositoryInterface::getOneOnOneLosses()
     */
    public function getOneOnOneLosses(string $playerName): array
    {
        return $this->fetchAll(
            "SELECT o.gameid, o.winner, o.loser, o.winscore, o.lossscore, p.pid as winner_pid 
             FROM ibl_one_on_one o 
             LEFT JOIN ibl_plr p ON o.winner = p.name 
             WHERE o.loser = ? 
             ORDER BY o.gameid ASC",
            "s",
            $playerName
        );
    }
}
