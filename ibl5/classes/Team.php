<?php

declare(strict_types=1);

/**
 * Team - IBL team information and operations
 *
 * Extends BaseMysqliRepository for standardized database access.
 * Provides team data, roster queries, and cap calculations.
 *
 * @see BaseMysqliRepository For base class documentation and error codes
 *
 *
 * @phpstan-type TeamWithStandingsRow array{teamid: int, team_city: string, team_name: string, color1: string, color2: string, arena: string, capacity: int, owner_name: string, owner_email: string, discordID: ?int, Used_Extension_This_Chunk: int, Used_Extension_This_Season: ?int, HasMLE: int, HasLLE: int, leagueRecord: ?string, ...}
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
     * @param \mysqli $db Active mysqli connection
     * @throws \RuntimeException If connection is invalid (error code 1002)
     */
    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    /**
     * Factory method to initialize a Team instance
     *
     * @param \mysqli $db Active mysqli connection
     * @param int|string|array<string, mixed> $identifier Team ID (int), team name (string), or team data array
     * @return self Initialized Team instance
     */
    public static function initialize(\mysqli $db, int|string|array $identifier): self
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

        $this->ownerName = $teamRow['owner_name'];
        $this->ownerEmail = $teamRow['owner_email'];
        $discordID = $teamRow['discordID'] ?? null;
        $this->discordID = $discordID;

        $this->hasUsedExtensionThisSim = $teamRow['Used_Extension_This_Chunk'];
        $this->hasUsedExtensionThisSeason = $teamRow['Used_Extension_This_Season'] ?? 0;
        $this->hasMLE = $teamRow['HasMLE'];
        $this->hasLLE = $teamRow['HasLLE'];

        /** @var string|null $leagueRecord */
        $leagueRecord = $teamRow['leagueRecord'] ?? null;
        $this->seasonRecord = $leagueRecord;
    }
}