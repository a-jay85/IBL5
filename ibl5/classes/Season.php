<?php

declare(strict_types=1);

use League\LeagueContext;

/**
 * Season - IBL season information and configuration
 * 
 * Extends BaseMysqliRepository for standardized database access.
 * Provides season phase, dates, settings, and configuration.
 * 
 * @property string $phase Current season phase
 * @property int $beginningYear Season beginning year
 * @property int $endingYear Season ending year
 * @property \DateTimeInterface $regularSeasonStartDate Regular season start date
 * @property \DateTimeInterface $postAllStarStartDate Post All-Star start date
 * @property \DateTimeInterface $playoffsStartDate Playoffs start date
 * @property \DateTimeInterface $playoffsEndDate Playoffs end date
 * @property int $lastSimNumber Last simulation number
 * @property string $lastSimStartDate Last sim start date (YYYY-MM-DD from DATE column)
 * @property string $lastSimEndDate Last sim end date (YYYY-MM-DD from DATE column)
 * @property \DateTimeInterface $projectedNextSimEndDate Projected next sim end date
 * @property string $allowTrades Allow trades status
 * @property string $allowWaivers Allow waivers status
 * @property string $freeAgencyNotificationsState Free agency notifications state
 * 
 * @see BaseMysqliRepository For base class documentation and error codes
 */
class Season extends BaseMysqliRepository
{
    private ?LeagueContext $leagueContext = null;

    public $phase;

    public $beginningYear;
    public $endingYear;

    public $regularSeasonStartDate;
    public $postAllStarStartDate;
    public $playoffsStartDate;
    public $playoffsEndDate;

    public $lastSimNumber;
    public $lastSimStartDate;
    public $lastSimEndDate;

    public $projectedNextSimEndDate;

    public $allowTrades;
    public $allowWaivers;

    public $freeAgencyNotificationsState;

    const IBL_PRESEASON_YEAR = 9998;
    const IBL_HEAT_MONTH = 10;
    const IBL_REGULAR_SEASON_STARTING_MONTH = 11;
    const IBL_ALL_STAR_MONTH = 02;
    const IBL_REGULAR_SEASON_ENDING_MONTH = 05;
    const IBL_PLAYOFF_MONTH = 06;

    /**
     * Constructor - initializes season data from database
     * 
     * @param \mysqli $db Active mysqli connection
     * @param LeagueContext|null $leagueContext Optional league context for multi-league support
     * @throws \RuntimeException If connection is invalid (error code 1002)
     */
    public function __construct(object $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db);
        $this->leagueContext = $leagueContext;

        $this->phase = $this->getSeasonPhase();

        $this->endingYear = (int)$this->getSeasonEndingYear(); // Cast to integer since column type is VARCHAR
        $this->beginningYear = $this->endingYear - 1;

        $this->regularSeasonStartDate = date_create("$this->beginningYear-" . Season::IBL_REGULAR_SEASON_STARTING_MONTH . "-01");
        $this->postAllStarStartDate = date_create("$this->endingYear-" . Season::IBL_ALL_STAR_MONTH . "-04");
        $this->playoffsStartDate = date_create("$this->endingYear-" . Season::IBL_PLAYOFF_MONTH . "-01");
        $this->playoffsEndDate = date_create("$this->endingYear-" . Season::IBL_PLAYOFF_MONTH . "-30");

        $arrayLastSimDates = $this->getLastSimDatesArray();
        $this->lastSimNumber = $arrayLastSimDates["Sim"];
        $this->lastSimStartDate = $arrayLastSimDates["Start Date"];
        $this->lastSimEndDate = $arrayLastSimDates["End Date"];

        $this->projectedNextSimEndDate = $this->getProjectedNextSimEndDate($this->lastSimEndDate);

        $this->allowTrades = $this->getAllowTradesStatus();
        $this->allowWaivers = $this->getAllowWaiversStatus();

        $this->freeAgencyNotificationsState = $this->getFreeAgencyNotificationsState();
    }

    /**
     * Get current season phase
     * 
     * @return string Current season phase (e.g., 'Regular Season', 'Playoffs', 'Free Agency')
     */
    public function getSeasonPhase(): string
    {
        $result = $this->fetchOne(
            "SELECT value FROM ibl_settings WHERE name = ? LIMIT 1",
            "s",
            "Current Season Phase"
        );

        return $result['value'] ?? '';
    }

    /**
     * Get season ending year
     * 
     * @return string Season ending year (e.g., '2024')
     */
    public function getSeasonEndingYear(): string
    {
        $result = $this->fetchOne(
            "SELECT value FROM ibl_settings WHERE name = ? LIMIT 1",
            "s",
            "Current Season Ending Year"
        );

        return $result['value'] ?? '';
    }

    /**
     * Get first box score date
     * 
     * @return string First box score date from database
     */
    public function getFirstBoxScoreDate(): string
    {
        $result = $this->fetchOne(
            "SELECT Date FROM ibl_box_scores ORDER BY Date ASC LIMIT 1"
        );

        return $result['Date'] ?? '';
    }

    /**
     * Get last box score date
     * 
     * @return string Last box score date from database
     */
    public function getLastBoxScoreDate(): string
    {
        $result = $this->fetchOne(
            "SELECT Date FROM ibl_box_scores ORDER BY Date DESC LIMIT 1"
        );

        return $result['Date'] ?? '';
    }

    /**
     * Get last sim dates array
     * 
     * Returns the most recent simulation date range from ibl_sim_dates.
     * Note: 'Start Date' and 'End Date' columns are DATE type in schema.
     * 
     * @return array Array with keys: Sim (int), 'Start Date' (string YYYY-MM-DD), 'End Date' (string YYYY-MM-DD)
     */
    public function getLastSimDatesArray(): array
    {
        $result = $this->fetchOne(
            "SELECT * FROM ibl_sim_dates ORDER BY sim DESC LIMIT 1"
        );

        return $result ?? [];
    }

    /**
     * Set last sim dates array
     * 
     * Inserts a new simulation date range into ibl_sim_dates.
     * Note: 'Start Date' and 'End Date' columns are DATE type in schema.
     * 
     * @param string $newSimNumber New sim number
     * @param string $newSimStartDate New sim start date (YYYY-MM-DD format)
     * @param string $newSimEndDate New sim end date (YYYY-MM-DD format)
     * @return int Number of affected rows
     */
    public function setLastSimDatesArray(string $newSimNumber, string $newSimStartDate, string $newSimEndDate): int
    {
        return $this->execute(
            "INSERT INTO ibl_sim_dates (`Sim`, `Start Date`, `End Date`) VALUES (?, ?, ?)",
            "sss",
            $newSimNumber,
            $newSimStartDate,
            $newSimEndDate
        );
    }

    /**
     * Get projected next sim end date
     * 
     * @param string $lastSimEndDate Last sim end date (YYYY-MM-DD format from DATE column)
     * @return \DateTimeInterface Projected next sim end date
     */
    public function getProjectedNextSimEndDate(string $lastSimEndDate): \DateTimeInterface
    {
        $league = new League($this->db);
        $simLengthInDays = $league->getSimLengthInDays();
        
        $lastSimEndDate = date_create($lastSimEndDate);
        $projectedNextSimEndDate = date_add($lastSimEndDate, date_interval_create_from_date_string($simLengthInDays . ' days'));
    
        // override $projectedNextSimEndDate to account for the All-Star Break
        if (
            $projectedNextSimEndDate > date_create("$this->endingYear-01-31")
            AND $projectedNextSimEndDate <= date_create("$this->endingYear-02-05")
        ) {
            $projectedNextSimEndDate = date_add($this->postAllStarStartDate, date_interval_create_from_date_string($simLengthInDays . ' days'));
        }

        return $projectedNextSimEndDate;
    }

    /**
     * Get allow trades status
     * 
     * @return string Status of allowing trades ('Yes' or 'No')
     */
    public function getAllowTradesStatus(): string
    {
        $result = $this->fetchOne(
            "SELECT value FROM ibl_settings WHERE name = ? LIMIT 1",
            "s",
            "Allow Trades"
        );

        return $result['value'] ?? '';
    }

    /**
     * Get allow waivers status
     * 
     * @return string Status of allowing waivers ('Yes' or 'No')
     */
    public function getAllowWaiversStatus(): string
    {
        $result = $this->fetchOne(
            "SELECT value FROM ibl_settings WHERE name = ? LIMIT 1",
            "s",
            "Allow Waiver Moves"
        );

        return $result['value'] ?? '';
    }

    /**
     * Get free agency notifications state
     * 
     * @return string State of free agency notifications ('On' or 'Off')
     */
    public function getFreeAgencyNotificationsState(): string
    {
        $result = $this->fetchOne(
            "SELECT value FROM ibl_settings WHERE name = ? LIMIT 1",
            "s",
            "Free Agency Notifications"
        );

        return $result['value'] ?? '';
    }
}