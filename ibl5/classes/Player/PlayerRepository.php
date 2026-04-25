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
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type HistoricalPlayerRow from PlayerRepositoryInterface
 * @phpstan-import-type AwardRow from PlayerRepositoryInterface
 * @phpstan-import-type PlayerNewsRow from PlayerRepositoryInterface
 * @phpstan-import-type OneOnOneWinRow from PlayerRepositoryInterface
 * @phpstan-import-type OneOnOneLossRow from PlayerRepositoryInterface
 *
 * @see PlayerRepositoryInterface For method contracts
 * @see BaseMysqliRepository For base class documentation and error codes
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
            "SELECT p.*, t.team_name AS teamname, t.color1, t.color2
             FROM ibl_plr p
             LEFT JOIN ibl_team_info t ON p.teamid = t.teamid
             WHERE p.pid = ? LIMIT 1",
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
     * Map free agency preference fields
     *
     * @param PlayerRow $plrRow Database row from ibl_plr
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
     * Map rating fields from historical player row (different column names than current)
     *
     * @param array{r_2ga: ?int, r_2gp: ?int, r_fta: ?int, r_ftp: ?int, r_3ga: ?int, r_3gp: ?int, r_orb: ?int, r_drb: ?int, r_ast: ?int, r_stl: ?int, r_blk: ?int, r_tvr: ?int, r_oo: ?int, r_od: ?int, r_drive_off: ?int, r_dd: ?int, r_po: ?int, r_pd: ?int, r_trans_off: ?int, r_td: ?int, ...} $plrRow
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

    /**
     * @see PlayerRepositoryInterface::getFreeAgencyDemands()
     * 
     * Uses fetchOne from BaseMysqliRepository with prepared statement.
     */
    public function getFreeAgencyDemands(int $playerID): array
    {
        $row = $this->fetchOne(
            "SELECT * FROM ibl_demands WHERE pid = ?",
            "i",
            $playerID
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
                SUM(CASE WHEN award LIKE '%Conference All-Star' THEN 1 ELSE 0 END) AS allStar,
                SUM(CASE WHEN award LIKE 'Three-Point Contest%' THEN 1 ELSE 0 END) AS threePoint,
                SUM(CASE WHEN award LIKE 'Slam Dunk Competition%' THEN 1 ELSE 0 END) AS dunkContest,
                SUM(CASE WHEN award LIKE 'Rookie-Sophomore Challenge' THEN 1 ELSE 0 END) AS rookieSoph
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
     * @return array<int, array<string, mixed>> Array of sim date records (keys: sim, start_date, end_date)
     */
    public function getAllSimDates(): array
    {
        return $this->fetchAll(
            "SELECT sim, start_date, end_date FROM ibl_sim_dates ORDER BY sim ASC",
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
            self::buildPerSeasonStatsQuery(2),
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
            self::buildPerSeasonStatsQuery(3),
            "s",
            $playerName
        );
    }

    /**
     * Build inlined per-season stats query with predicate pushed before GROUP BY.
     *
     * Replaces SELECT from ibl_playoff_stats / ibl_heat_stats views.
     */
    private static function buildPerSeasonStatsQuery(int $gameType): string
    {
        return "SELECT bs.season_year AS year, MIN(bs.pos) AS pos, bs.pid, p.name,
            fs.team_name AS team,
            CAST(COUNT(*) AS SIGNED) AS games,
            CAST(SUM(bs.game_min) AS SIGNED) AS minutes,
            CAST(SUM(bs.calc_fg_made) AS SIGNED) AS fgm,
            CAST(SUM(bs.game_2ga + bs.game_3ga) AS SIGNED) AS fga,
            CAST(SUM(bs.game_ftm) AS SIGNED) AS ftm,
            CAST(SUM(bs.game_fta) AS SIGNED) AS fta,
            CAST(SUM(bs.game_3gm) AS SIGNED) AS tgm,
            CAST(SUM(bs.game_3ga) AS SIGNED) AS tga,
            CAST(SUM(bs.game_orb) AS SIGNED) AS orb,
            CAST(SUM(bs.calc_rebounds) AS SIGNED) AS reb,
            CAST(SUM(bs.game_ast) AS SIGNED) AS ast,
            CAST(SUM(bs.game_stl) AS SIGNED) AS stl,
            CAST(SUM(bs.game_tov) AS SIGNED) AS tvr,
            CAST(SUM(bs.game_blk) AS SIGNED) AS blk,
            CAST(SUM(bs.game_pf) AS SIGNED) AS pf,
            CAST(SUM(bs.calc_points) AS SIGNED) AS pts
        FROM ibl_box_scores bs
        JOIN ibl_plr p ON bs.pid = p.pid
        JOIN ibl_franchise_seasons fs ON bs.teamid = fs.franchise_id
            AND bs.season_year = fs.season_ending_year
        WHERE bs.game_type = {$gameType} AND p.name = ?
        GROUP BY bs.pid, p.name, bs.season_year, fs.team_name
        ORDER BY year ASC";
    }

    /**
     * @see PlayerRepositoryInterface::getOlympicsStats()
     * @return array<int, array<string, mixed>>
     */
    public function getOlympicsStats(int $playerID): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_olympics_stats WHERE pid = ? ORDER BY year ASC",
            "i",
            $playerID
        );
    }

    /**
     * @see PlayerRepositoryInterface::getAwards()
     *
     * @return list<AwardRow>
     */
    public function getAwards(string $playerName): array
    {
        /** @var list<AwardRow> */
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
     *
     * @return list<PlayerNewsRow>
     */
    public function getPlayerNews(string $playerName): array
    {
        $searchPattern = '%' . $playerName . '%';
        $searchPatternII = '%' . $playerName . ' II%';

        /** @var list<PlayerNewsRow> */
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
     *
     * @return list<OneOnOneWinRow>
     */
    public function getOneOnOneWins(string $playerName): array
    {
        /** @var list<OneOnOneWinRow> */
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
     *
     * @return list<OneOnOneLossRow>
     */
    public function getOneOnOneLosses(string $playerName): array
    {
        /** @var list<OneOnOneLossRow> */
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

    /**
     * @see PlayerRepositoryInterface::getPlayerIdByUuid()
     */
    public function getPlayerIdByUuid(string $uuid): ?int
    {
        /** @var array{pid: int}|null $row */
        $row = $this->fetchOne(
            "SELECT pid FROM ibl_plr WHERE uuid = ?",
            "s",
            $uuid
        );

        return $row !== null ? $row['pid'] : null;
    }
}
