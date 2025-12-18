<?php

declare(strict_types=1);

use Player\Player;

/**
 * Team - IBL team information and operations
 * 
 * Extends BaseMysqliRepository for standardized database access.
 * Provides team data, roster queries, and cap calculations.
 * 
 * @see BaseMysqliRepository For base class documentation and error codes
 */
class Team extends BaseMysqliRepository
{
    public $teamID;

    public $city;
    public $name;
    public $color1;
    public $color2;
    public $arena;
    public $capacity;
    public $formerlyKnownAs;

    public $ownerName;
    public $ownerEmail;
    public $discordID;

    public $hasUsedExtensionThisSim;
    public $hasUsedExtensionThisSeason;
    public $hasMLE;
    public $hasLLE;

    public $numberOfPlayers;
    public $numberOfHealthyPlayers;
    public $numberOfOpenRosterSpots;
    public $numberOfHealthyOpenRosterSpots;

    public $seasonRecord;

    const BUYOUT_PERCENTAGE_MAX = 0.40;
    const ROSTER_SPOTS_MAX = 15;

    /**
     * Constructor - inherits from BaseMysqliRepository
     * 
     * @param object $db Active mysqli connection (or duck-typed mock during migration)
     * @throws \RuntimeException If connection is invalid (error code 1002)
     */
    public function __construct(object $db)
    {
        parent::__construct($db);
    }

    /**
     * Factory method to initialize a Team instance
     * 
     * @param object $db Active mysqli connection
     * @param int|string|array $identifier Team ID (int), team name (string), or team data array
     * @return self Initialized Team instance
     */
    public static function initialize(object $db, $identifier): self
    {
        $instance = new self($db);
        $instance->load($identifier);
        return $instance;
    }

    /**
     * Load team data from database
     * 
     * @param int|string|array $identifier Team ID (int), team name (string), or team data array
     * @return void
     */
    protected function load($identifier): void
    {
        ($identifier) ? $identifier : $identifier = League::FREE_AGENTS_TEAMID;

        if (is_array($identifier)) {
            $this->fill($identifier);
            return;
        }

        if (is_numeric($identifier)) {
            $teamID = (int) $identifier;
            $query = "SELECT ibl_team_info.*,
                     ibl_standings.leagueRecord 
                FROM ibl_team_info
                    LEFT JOIN ibl_standings
                    ON ibl_team_info.teamid = ibl_standings.tid
                WHERE ibl_team_info.teamid = ?
                LIMIT 1";
            $teamRow = $this->fetchOne($query, "i", $teamID);
        } elseif (is_string($identifier)) {
            $query = "SELECT ibl_team_info.*,
                     ibl_standings.leagueRecord
                FROM ibl_team_info
                    LEFT JOIN ibl_standings
                    ON ibl_team_info.teamid = ibl_standings.tid
                WHERE ibl_team_info.team_name = ?
                LIMIT 1";
            $teamRow = $this->fetchOne($query, "s", $identifier);
        }

        if ($teamRow === null) {
            throw new \RuntimeException("Team not found: " . $identifier);
        }

        $this->fill($teamRow);
    }


    /**
     * Fill team properties from array data
     * 
     * @param array $teamRow Team data from database
     * @return void
     */
    protected function fill(array $teamRow): void
    {
        $this->teamID = (int) $teamRow['teamid'];

        $this->city = $teamRow['team_city'];
        $this->name = $teamRow['team_name'];
        $this->color1 = $teamRow['color1'];
        $this->color2 = $teamRow['color2'];
        $this->arena = $teamRow['arena'];
        $this->capacity = $teamRow['capacity'];
        $this->formerlyKnownAs = $teamRow['formerly_known_as'];
    
        $this->ownerName = $teamRow['owner_name'];
        $this->ownerEmail = $teamRow['owner_email'];
        $this->discordID = $teamRow['discordID'];
    
        $this->hasUsedExtensionThisSim = $teamRow['Used_Extension_This_Chunk'];
        $this->hasUsedExtensionThisSeason = $teamRow['Used_Extension_This_Season'];
        $this->hasMLE = $teamRow['HasMLE'];
        $this->hasLLE = $teamRow['HasLLE'];

        $this->seasonRecord = $teamRow['leagueRecord'] ?? null;
    }

    /**
     * Get buyout players for this team
     * 
     * @return array<int, array> Array of buyout player rows
     */
    public function getBuyoutsResult(): array
    {
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE tid = ?
              AND name LIKE '%Buyout%'
            ORDER BY name ASC",
            "i",
            $this->teamID
        );
    }
    
    /**
     * Get draft history for this team
     * 
     * @return array<int, array> Array of drafted player rows
     */
    public function getDraftHistoryResult(): array
    {
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE draftedby LIKE ?
            ORDER BY draftyear DESC,
                     draftround,
                     draftpickno ASC",
            "s",
            $this->name
        );
    }

    /**
     * Get draft picks owned by this team
     * 
     * @return array<int, array> Array of draft pick rows
     */
    public function getDraftPicksResult(): array
    {
        return $this->fetchAll(
            "SELECT *
            FROM ibl_draft_picks
            WHERE ownerofpick = ?
            ORDER BY year, round, teampick ASC",
            "s",
            $this->name
        );
    }

    /**
     * Get free agency offers made by this team
     * 
     * @return array<int, array> Array of offer rows
     */
    public function getFreeAgencyOffersResult(): array
    {
        return $this->fetchAll(
            "SELECT *
            FROM ibl_fa_offers
            WHERE team = ?
            ORDER BY name ASC",
            "s",
            $this->name
        );
    }

    /**
     * Get free agency roster ordered by name
     * 
     * @return array<int, array> Array of player rows
     */
    public function getFreeAgencyRosterOrderedByNameResult(): array
    {
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE tid = ?
              AND retired = 0
              AND cyt != cy
            ORDER BY name ASC",
            "i",
            $this->teamID
        );
    }


    /**
     * Get healthy and injured players ordered by name
     * 
     * @param Season|null $season Season object for free agency filtering
     * @return array<int, array> Array of player rows
     */
    public function getHealthyAndInjuredPlayersOrderedByNameResult($season = null): array
    {
        $freeAgencyCondition = '';
        if ($season && $season->phase === 'Free Agency') {
            // During Free Agency, only count players who have a salary for next year
            $freeAgencyCondition = " AND (
                (cy = 0 AND cy1 > 0) OR
                (cy = 0 AND cy2 > 0) OR
                (cy = 1 AND cy2 > 0) OR
                (cy = 2 AND cy3 > 0) OR
                (cy = 3 AND cy4 > 0) OR
                (cy = 4 AND cy5 > 0) OR
                (cy = 5 AND cy6 > 0)
            )";
        }
        
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE teamname = ?
              AND tid = ?
              AND retired = '0'
              AND ordinal <= '" . JSB::WAIVERS_ORDINAL . "'" . $freeAgencyCondition . "
            ORDER BY name ASC",
            "si",
            $this->name,
            $this->teamID
        );
    }

    /**
     * Get healthy players ordered by name
     * 
     * @param Season|null $season Season object for free agency filtering
     * @return array<int, array> Array of player rows
     */
    public function getHealthyPlayersOrderedByNameResult($season = null): array
    {
        $freeAgencyCondition = '';
        if ($season && $season->phase === 'Free Agency') {
            // During Free Agency, only count players who have a salary for next year
            $freeAgencyCondition = " AND (
                (cy = 0 AND cy1 > 0) OR
                (cy = 0 AND cy2 > 0) OR
                (cy = 1 AND cy2 > 0) OR
                (cy = 2 AND cy3 > 0) OR
                (cy = 3 AND cy4 > 0) OR
                (cy = 4 AND cy5 > 0) OR
                (cy = 5 AND cy6 > 0)
            )";
        }
        
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE teamname = ?
              AND tid = ?
              AND retired = '0'
              AND ordinal <= '" . JSB::WAIVERS_ORDINAL ."'" . $freeAgencyCondition . "
              AND injured = '0'
            ORDER BY name ASC",
            "si",
            $this->name,
            $this->teamID
        );
    }

    /**
     * Get player ID of last sim starter for a position
     * 
     * @param string $position Position code (e.g., 'PG', 'SG', 'SF', 'PF', 'C')
     * @return int Player ID
     */
    public function getLastSimStarterPlayerIDForPosition(string $position): int
    {
        $result = $this->fetchOne(
            "SELECT pid
            FROM ibl_plr
            WHERE tid = ?
              AND retired = 0
              AND " . $position . "Depth = 1",
            "i",
            $this->teamID
        );
        return $result ? (int) $result['pid'] : 0;
    }

    /**
     * Get player ID of currently set starter for a position
     * 
     * @param string $position Position code (e.g., 'PG', 'SG', 'SF', 'PF', 'C')
     * @return int Player ID
     */
    public function getCurrentlySetStarterPlayerIDForPosition(string $position): int
    {
        $result = $this->fetchOne(
            "SELECT pid
            FROM ibl_plr
            WHERE tid = ?
              AND retired = 0
              AND dc_" . $position . "Depth = 1",
            "i",
            $this->teamID
        );
        return $result ? (int) $result['pid'] : 0;
    }

    /**
     * Get players under contract by position
     * 
     * @param string $position Position code (e.g., 'PG', 'SG', 'SF', 'PF', 'C')
     * @return array<int, array> Array of player rows
     */
    public function getPlayersUnderContractByPositionResult(string $position): array
    {
        return $this->fetchAll(
            "SELECT * 
            FROM ibl_plr
            WHERE teamname = ?
              AND pos = ?
              AND cy1 != 0
              AND retired = 0",
            "ss",
            $this->name,
            $position
        );
    }

    /**
     * Get roster under contract ordered by name
     * 
     * @return array<int, array> Array of player rows
     */
    public function getRosterUnderContractOrderedByNameResult(): array
    {
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE tid = ?
              AND retired = 0
            ORDER BY name ASC",
            "i",
            $this->teamID
        );
    }

    /**
     * Get roster under contract ordered by ordinal
     * 
     * @return array<int, array> Array of player rows
     */
    public function getRosterUnderContractOrderedByOrdinalResult(): array
    {
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE tid = ?
              AND retired = 0
            ORDER BY ordinal ASC",
            "i",
            $this->teamID
        );
    }


    /**
     * Get salary cap array for all contract years
     * 
     * @param Season $season Season object for free agency handling
     * @return array<string, int> Array of salary cap spent by year
     */
    public function getSalaryCapArray(Season $season): array
    {  
        $salaryCapSpent[] = 0;
        $resultContracts = $this->getRosterUnderContractOrderedByNameResult();
    
        foreach ($resultContracts as $contract) {
            $yearUnderContract = $contract['cy'];
            if ($season->phase == "Free Agency") {
                $yearUnderContract++;
            }
            
            $i = 1;
            while ($yearUnderContract <= $contract['cyt']) {
                $fieldString = "cy" . $yearUnderContract;
                $salaryCapSpent["year" . $i] += $contract["$fieldString"];
                $yearUnderContract++;
                $i++;
            }
        }

        return $salaryCapSpent;
    }

    /**
     * Get total current season salaries from player result array
     * 
     * @param array<int, array> $result Array of player rows
     * @return int Total current season salaries
     */
    public function getTotalCurrentSeasonSalariesFromPlrResult(array $result): int
    {
        $totalCurrentSeasonSalaries = 0;

        $playerArray = $this->convertPlrResultIntoPlayerArray($result);
        foreach ($playerArray as $player) {
            $totalCurrentSeasonSalaries += $player->getCurrentSeasonSalary();
        }
        return $totalCurrentSeasonSalaries;
    }

    /**
     * Get total next season salaries from player result array
     * 
     * @param array<int, array> $result Array of player rows
     * @return int Total next season salaries
     */
    public function getTotalNextSeasonSalariesFromPlrResult(array $result): int
    {
        $totalNextSeasonSalaries = 0;

        $playerArray = $this->convertPlrResultIntoPlayerArray($result);
        foreach ($playerArray as $player) {
            $totalNextSeasonSalaries += $player->getNextSeasonSalary();
        }
        return $totalNextSeasonSalaries;
    }

    /**
     * Check if team can add contract without going over hard cap
     * 
     * @param int $currentSeasonContractValueToBeAdded Contract value to add
     * @return bool True if under hard cap, false otherwise
     */
    public function canAddContractWithoutGoingOverHardCap(int $currentSeasonContractValueToBeAdded): bool
    {
        $teamResult = $this->getRosterUnderContractOrderedByNameResult();
        $totalCurrentSeasonSalaries = $this->getTotalCurrentSeasonSalariesFromPlrResult($teamResult);
        $projectedTotalCurrentSeasonSalaries = $totalCurrentSeasonSalaries + $currentSeasonContractValueToBeAdded;

        if ($projectedTotalCurrentSeasonSalaries <= League::HARD_CAP_MAX) {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Check if team can add buyout without exceeding buyout limit
     * 
     * @param int $currentSeasonBuyoutValueToBeAdded Buyout value to add
     * @return bool True if under buyout limit, false otherwise
     */
    public function canAddBuyoutWithoutExceedingBuyoutLimit(int $currentSeasonBuyoutValueToBeAdded): bool
    {
        $buyoutsResult = $this->getBuyoutsResult();
        $totalCurrentSeasonBuyouts = $this->getTotalCurrentSeasonSalariesFromPlrResult($buyoutsResult);
        $projectedTotalCurrentSeasonBuyouts = $totalCurrentSeasonBuyouts + $currentSeasonBuyoutValueToBeAdded;
        $buyoutLimit = League::HARD_CAP_MAX * self::BUYOUT_PERCENTAGE_MAX;

        if ($projectedTotalCurrentSeasonBuyouts <= $buyoutLimit) {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Convert player result array into Player objects
     * 
     * @param array<int, array> $result Array of player rows
     * @return array<int, Player> Array of Player objects indexed by player ID
     */
    public function convertPlrResultIntoPlayerArray(array $result): array
    {
        $array = array();
        foreach ($result as $plrRow) {
            $playerID = $plrRow['pid'];
            $array[$playerID] = Player::withPlayerID($this->db, $playerID);
        }
        return $array;
    }
}