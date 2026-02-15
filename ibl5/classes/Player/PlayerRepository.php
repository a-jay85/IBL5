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
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 */
class PlayerRepository extends BaseMysqliRepository implements PlayerRepositoryInterface
{
    /** @var array{allStar: int, threePoint: int, dunkContest: int, rookieSoph: int}|null */
    private ?array $cachedAllStarWeekendCounts = null;
    private ?string $cachedAllStarWeekendPlayerName = null;

    /**
     * Constructor - inherits from BaseMysqliRepository
     *
     * @param \mysqli $db Active mysqli connection
     * @throws \RuntimeException If connection is invalid (error code 1002)
     */
    public function __construct(\mysqli $db)
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
        /** @var PlayerRow|null $plrRow */
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
     *
     * @param PlayerRow $plrRow Database row from ibl_plr
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
     *
     * @param PlayerRow $plrRow Database row from ibl_plr
     */
    private function mapBasicFields(PlayerData $playerData, array $plrRow): void
    {
        $playerData->playerID = $plrRow['pid'];
        $playerData->ordinal = $plrRow['ordinal'];
        $playerData->name = stripslashes($plrRow['name']);
        $playerData->nickname = $this->getOptionalStrippedValue($plrRow, 'nickname');
        $playerData->age = $plrRow['age'];
        $playerData->teamID = $plrRow['tid'];
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
     * Map rating fields from current player row
     *
     * @param PlayerRow $plrRow Database row from ibl_plr
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
     *
     * @param PlayerRow $plrRow Database row from ibl_plr
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
     *
     * @param PlayerRow $plrRow Database row from ibl_plr
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
     *
     * @param PlayerRow $plrRow Database row from ibl_plr
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
     * @param PlayerRow $plrRow Database row from ibl_plr
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
     * @param PlayerRow $plrRow Database row from ibl_plr
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
     * Fill a PlayerData object from a historical player row
     *
     * @param array{pid: ?int, year: ?int, name: ?string, team: ?string, teamid: ?int, salary: ?int, r_2ga: ?int, r_2gp: ?int, r_fta: ?int, r_ftp: ?int, r_3ga: ?int, r_3gp: ?int, r_orb: ?int, r_drb: ?int, r_ast: ?int, r_stl: ?int, r_blk: ?int, r_tvr: ?int, r_oo: ?int, r_od: ?int, r_do: ?int, r_dd: ?int, r_po: ?int, r_pd: ?int, r_to: ?int, r_td: ?int, ...} $plrRow
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
        $playerData->teamID = $plrRow['teamid'] ?? null;

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
     * Map rating fields from historical player row (different column names than current)
     *
     * @param array{r_2ga: ?int, r_2gp: ?int, r_fta: ?int, r_ftp: ?int, r_3ga: ?int, r_3gp: ?int, r_orb: ?int, r_drb: ?int, r_ast: ?int, r_stl: ?int, r_blk: ?int, r_tvr: ?int, r_oo: ?int, r_od: ?int, r_do: ?int, r_dd: ?int, r_po: ?int, r_pd: ?int, r_to: ?int, r_td: ?int, ...} $plrRow
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
        $playerData->ratingDriveOffense = $plrRow['r_do'] ?? null;
        $playerData->ratingDriveDefense = $plrRow['r_dd'] ?? null;
        $playerData->ratingPostOffense = $plrRow['r_po'] ?? null;
        $playerData->ratingPostDefense = $plrRow['r_pd'] ?? null;
        $playerData->ratingTransitionOffense = $plrRow['r_to'] ?? null;
        $playerData->ratingTransitionDefense = $plrRow['r_td'] ?? null;
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
        if ($row !== null) {
            /** @var array{dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int, ...} $row */
            return [
                'dem1' => $row['dem1'] ?? 0,
                'dem2' => $row['dem2'] ?? 0,
                'dem3' => $row['dem3'] ?? 0,
                'dem4' => $row['dem4'] ?? 0,
                'dem5' => $row['dem5'] ?? 0,
                'dem6' => $row['dem6'] ?? 0,
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
     * @see PlayerRepositoryInterface::getAllStarGameCount()
     */
    public function getAllStarGameCount(string $playerName): int
    {
        return $this->getAllStarWeekendCounts($playerName)['allStar'];
    }

    /**
     * @see PlayerRepositoryInterface::getThreePointContestCount()
     */
    public function getThreePointContestCount(string $playerName): int
    {
        return $this->getAllStarWeekendCounts($playerName)['threePoint'];
    }

    /**
     * @see PlayerRepositoryInterface::getDunkContestCount()
     */
    public function getDunkContestCount(string $playerName): int
    {
        return $this->getAllStarWeekendCounts($playerName)['dunkContest'];
    }

    /**
     * @see PlayerRepositoryInterface::getRookieSophChallengeCount()
     */
    public function getRookieSophChallengeCount(string $playerName): int
    {
        return $this->getAllStarWeekendCounts($playerName)['rookieSoph'];
    }

    /**
     * Get all All-Star Weekend event counts in a single query
     *
     * @return array{allStar: int, threePoint: int, dunkContest: int, rookieSoph: int}
     */
    private function getAllStarWeekendCounts(string $playerName): array
    {
        if ($this->cachedAllStarWeekendCounts !== null && $this->cachedAllStarWeekendPlayerName === $playerName) {
            return $this->cachedAllStarWeekendCounts;
        }

        /** @var array{allStar: int, threePoint: int, dunkContest: int, rookieSoph: int}|null $result */
        $result = $this->fetchOne(
            "SELECT
                SUM(CASE WHEN Award LIKE '%Conference All-Star' THEN 1 ELSE 0 END) AS allStar,
                SUM(CASE WHEN Award LIKE 'Three-Point Contest%' THEN 1 ELSE 0 END) AS threePoint,
                SUM(CASE WHEN Award LIKE 'Slam Dunk Competition%' THEN 1 ELSE 0 END) AS dunkContest,
                SUM(CASE WHEN Award LIKE 'Rookie-Sophomore Challenge' THEN 1 ELSE 0 END) AS rookieSoph
            FROM ibl_awards
            WHERE name = ?",
            "s",
            $playerName
        );

        $this->cachedAllStarWeekendPlayerName = $playerName;
        $this->cachedAllStarWeekendCounts = [
            'allStar' => (int) ($result['allStar'] ?? 0),
            'threePoint' => (int) ($result['threePoint'] ?? 0),
            'dunkContest' => (int) ($result['dunkContest'] ?? 0),
            'rookieSoph' => (int) ($result['rookieSoph'] ?? 0),
        ];

        return $this->cachedAllStarWeekendCounts;
    }

    /**
     * Get all sim dates ordered by sim number
     *
     * Returns all simulation date ranges from ibl_sim_dates table.
     * Note: 'Start Date' and 'End Date' columns are DATE type in schema.
     *
     * @return array<int, array<string, mixed>> Array of sim date records
     */
    public function getAllSimDates(): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_sim_dates ORDER BY sim ASC",
            ""
        );
    }

    /**
     * @see PlayerRepositoryInterface::getHistoricalStats()
     * @return array<int, array<string, mixed>>
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
     * @see PlayerRepositoryInterface::getPlayoffStats()
     * @return list<array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int}>
     */
    public function getPlayoffStats(string $playerName): array
    {
        /** @var list<array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int}> */
        return $this->fetchAll(
            "SELECT * FROM ibl_playoff_stats WHERE name = ? ORDER BY year ASC",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerRepositoryInterface::getHeatStats()
     * @return list<array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int}>
     */
    public function getHeatStats(string $playerName): array
    {
        /** @var list<array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int}> */
        return $this->fetchAll(
            "SELECT * FROM ibl_heat_stats WHERE name = ? ORDER BY year ASC",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerRepositoryInterface::getOlympicsStats()
     * @return array<int, array<string, mixed>>
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
     * @see PlayerRepositoryInterface::getAwards()
     * @return array<int, array<string, mixed>>
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
     * @see PlayerRepositoryInterface::getPlayerStats()
     * @return PlayerRow|null
     */
    public function getPlayerStats(int $playerID): ?array
    {
        /** @var PlayerRow|null */
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
