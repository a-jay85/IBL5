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
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type TeamInfoRow from \Services\CommonMysqliRepository
 *
 * @phpstan-type TeamWithStandingsRow array{teamid: int, team_city: string, team_name: string, color1: string, color2: string, arena: string, capacity: int, owner_name: string, owner_email: string, discordID: ?int, formerly_known_as: ?string, Used_Extension_This_Chunk: int, Used_Extension_This_Season: ?int, HasMLE: int, HasLLE: int, leagueRecord: ?string, ...}
 * @phpstan-type DraftPickRow array{pickid: int, ownerofpick: string, teampick: string, year: string, round: string, notes: ?string, created_at: string, updated_at: string}
 * @phpstan-type FreeAgencyOfferRow array{team: string, name: string, offer1: int, offer2: int, offer3: int, offer4: int, offer5: int, offer6: int, ...}
 */
class Team extends BaseMysqliRepository
{
    public int $teamID;

    public string $city;
    public string $name;
    public string $color1;
    public string $color2;
    public string $arena;
    public int $capacity;
    public ?string $formerlyKnownAs;

    public string $ownerName;
    public string $ownerEmail;
    public int|string|null $discordID;

    public int $hasUsedExtensionThisSim = 0;
    public int $hasUsedExtensionThisSeason = 0;
    public int $hasMLE = 0;
    public int $hasLLE = 0;

    public int $numberOfPlayers;
    public int $numberOfHealthyPlayers;
    public int $numberOfOpenRosterSpots;
    public int $numberOfHealthyOpenRosterSpots;

    public ?string $seasonRecord;

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
     * @param int|string|array<string, mixed> $identifier Team ID (int), team name (string), or team data array
     * @return self Initialized Team instance
     */
    public static function initialize(object $db, int|string|array $identifier): self
    {
        $instance = new self($db);
        $instance->load($identifier);
        return $instance;
    }

    /**
     * Load team data from database
     *
     * @param int|string|array<string, mixed> $identifier Team ID (int), team name (string), or team data array
     * @return void
     */
    protected function load(int|string|array $identifier): void
    {
        if (is_int($identifier) && $identifier === 0) {
            $identifier = League::FREE_AGENTS_TEAMID;
        } elseif (is_string($identifier) && $identifier === '') {
            $identifier = League::FREE_AGENTS_TEAMID;
        }

        if (is_array($identifier)) {
            /** @var TeamWithStandingsRow $identifier */
            $this->fill($identifier);
            return;
        }

        if (is_int($identifier)) {
            $query = "SELECT ibl_team_info.*,
                     ibl_standings.leagueRecord
                FROM ibl_team_info
                    LEFT JOIN ibl_standings
                    ON ibl_team_info.teamid = ibl_standings.tid
                WHERE ibl_team_info.teamid = ?
                LIMIT 1";
            /** @var TeamWithStandingsRow|null $teamRow */
            $teamRow = $this->fetchOne($query, "i", $identifier);
        } else {
            $query = "SELECT ibl_team_info.*,
                     ibl_standings.leagueRecord
                FROM ibl_team_info
                    LEFT JOIN ibl_standings
                    ON ibl_team_info.teamid = ibl_standings.tid
                WHERE ibl_team_info.team_name = ?
                LIMIT 1";
            /** @var TeamWithStandingsRow|null $teamRow */
            $teamRow = $this->fetchOne($query, "s", $identifier);
        }

        if ($teamRow === null) {
            throw new \RuntimeException("Team not found: " . (is_int($identifier) ? (string) $identifier : $identifier));
        }

        $this->fill($teamRow);
    }


    /**
     * Fill team properties from array data
     *
     * @param TeamWithStandingsRow $teamRow Team data from database
     * @return void
     */
    protected function fill(array $teamRow): void
    {
        $this->teamID = $teamRow['teamid'];

        $this->city = $teamRow['team_city'];
        $this->name = $teamRow['team_name'];
        $this->color1 = $teamRow['color1'];
        $this->color2 = $teamRow['color2'];
        $this->arena = $teamRow['arena'];
        $this->capacity = $teamRow['capacity'];
        $this->formerlyKnownAs = $teamRow['formerly_known_as'] ?? null;

        $this->ownerName = $teamRow['owner_name'];
        $this->ownerEmail = $teamRow['owner_email'];
        $discordID = $teamRow['discordID'] ?? null;
        $this->discordID = $discordID;

        $this->hasUsedExtensionThisSim = (int) $teamRow['Used_Extension_This_Chunk'];
        $this->hasUsedExtensionThisSeason = (int) ($teamRow['Used_Extension_This_Season'] ?? 0);
        $this->hasMLE = (int) $teamRow['HasMLE'];
        $this->hasLLE = (int) $teamRow['HasLLE'];

        /** @var string|null $leagueRecord */
        $leagueRecord = $teamRow['leagueRecord'] ?? null;
        $this->seasonRecord = $leagueRecord;
    }

    /**
     * Get buyout players for this team
     *
     * @return list<PlayerRow> Array of buyout player rows
     */
    public function getBuyoutsResult(): array
    {
        /** @var list<PlayerRow> */
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
     * @return list<PlayerRow> Array of drafted player rows
     */
    public function getDraftHistoryResult(): array
    {
        /** @var list<PlayerRow> */
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
     * @return list<DraftPickRow> Array of draft pick rows
     */
    public function getDraftPicksResult(): array
    {
        /** @var list<DraftPickRow> */
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
     * @return list<FreeAgencyOfferRow> Array of offer rows
     */
    public function getFreeAgencyOffersResult(): array
    {
        /** @var list<FreeAgencyOfferRow> */
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
     * @return list<PlayerRow> Array of player rows
     */
    public function getFreeAgencyRosterOrderedByNameResult(): array
    {
        /** @var list<PlayerRow> */
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
     * @return list<PlayerRow> Array of player rows
     */
    public function getHealthyAndInjuredPlayersOrderedByNameResult(?Season $season = null): array
    {
        $freeAgencyCondition = '';
        if ($season !== null && $season->phase === 'Free Agency') {
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

        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE teamname = ?
              AND tid = ?
              AND retired = 0
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
     * @return list<PlayerRow> Array of player rows
     */
    public function getHealthyPlayersOrderedByNameResult(?Season $season = null): array
    {
        $freeAgencyCondition = '';
        if ($season !== null && $season->phase === 'Free Agency') {
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

        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE teamname = ?
              AND tid = ?
              AND retired = 0
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
        /** @var array{pid: int}|null $result */
        $result = $this->fetchOne(
            "SELECT pid
            FROM ibl_plr
            WHERE tid = ?
              AND retired = 0
              AND " . $position . "Depth = 1",
            "i",
            $this->teamID
        );
        return $result !== null ? $result['pid'] : 0;
    }

    /**
     * Get player ID of currently set starter for a position
     * 
     * @param string $position Position code (e.g., 'PG', 'SG', 'SF', 'PF', 'C')
     * @return int Player ID
     */
    public function getCurrentlySetStarterPlayerIDForPosition(string $position): int
    {
        /** @var array{pid: int}|null $result */
        $result = $this->fetchOne(
            "SELECT pid
            FROM ibl_plr
            WHERE tid = ?
              AND retired = 0
              AND dc_" . $position . "Depth = 1",
            "i",
            $this->teamID
        );
        return $result !== null ? $result['pid'] : 0;
    }

    /**
     * Get all players under contract (all positions)
     *
     * @return list<PlayerRow> Array of player rows
     */
    public function getAllPlayersUnderContractResult(): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE teamname = ?
              AND cy1 != 0
              AND retired = 0",
            "s",
            $this->name
        );
    }

    /**
     * Get players under contract by position
     *
     * @param string $position Position code (e.g., 'PG', 'SG', 'SF', 'PF', 'C')
     * @return list<PlayerRow> Array of player rows
     */
    public function getPlayersUnderContractByPositionResult(string $position): array
    {
        /** @var list<PlayerRow> */
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
     * @return list<PlayerRow> Array of player rows
     */
    public function getRosterUnderContractOrderedByNameResult(): array
    {
        /** @var list<PlayerRow> */
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
     * @return list<PlayerRow> Array of player rows
     */
    public function getRosterUnderContractOrderedByOrdinalResult(): array
    {
        /** @var list<PlayerRow> */
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
        /** @var array<string, int> $salaryCapSpent */
        $salaryCapSpent = [];
        $resultContracts = $this->getRosterUnderContractOrderedByNameResult();

        foreach ($resultContracts as $contract) {
            $yearUnderContract = $contract['cy'] ?? 0;
            if ($season->phase === "Free Agency") {
                $yearUnderContract++;
            }

            $cyt = $contract['cyt'] ?? 0;
            $i = 1;
            while ($yearUnderContract <= $cyt) {
                $fieldString = "cy" . $yearUnderContract;
                $key = "year" . $i;
                if (!isset($salaryCapSpent[$key])) {
                    $salaryCapSpent[$key] = 0;
                }
                $salaryCapSpent[$key] += (int) ($contract[$fieldString] ?? 0);
                $yearUnderContract++;
                $i++;
            }
        }

        return $salaryCapSpent;
    }

    /**
     * Get total current season salaries from player result array
     *
     * @param list<PlayerRow> $result Array of player rows
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
     * @param list<PlayerRow> $result Array of player rows
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
     * @param list<PlayerRow> $result Array of player rows
     * @return array<int, Player> Array of Player objects indexed by player ID
     */
    public function convertPlrResultIntoPlayerArray(array $result): array
    {
        $array = [];
        foreach ($result as $plrRow) {
            $playerID = (int) $plrRow['pid'];
            $array[$playerID] = Player::withPlrRow($this->db, $plrRow);
        }
        return $array;
    }
}