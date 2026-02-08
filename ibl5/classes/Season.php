<?php

declare(strict_types=1);

/**
 * Season - IBL season information and configuration
 * 
 * Extends BaseMysqliRepository for standardized database access.
 * Provides season phase, dates, settings, and configuration.
 * 
 * @property string $phase Current season phase
 * @property int $beginningYear Season beginning year
 * @property int $endingYear Season ending year
 * @property \DateTime $regularSeasonStartDate Regular season start date
 * @property \DateTime $postAllStarStartDate Post All-Star start date
 * @property \DateTime $playoffsStartDate Playoffs start date
 * @property \DateTime $playoffsEndDate Playoffs end date
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
    public string $phase;

    public int $beginningYear;
    public int $endingYear;

    public \DateTime $regularSeasonStartDate;
    public \DateTime $postAllStarStartDate;
    public \DateTime $playoffsStartDate;
    public \DateTime $playoffsEndDate;

    public int $lastSimNumber;
    public string $lastSimStartDate;
    public string $lastSimEndDate;

    public \DateTimeInterface $projectedNextSimEndDate;

    public string $allowTrades;
    public string $allowWaivers;

    public string $freeAgencyNotificationsState;

    const IBL_PRESEASON_YEAR = 9998;
    const IBL_OLYMPICS_MONTH = 8;
    const IBL_HEAT_MONTH = 10;
    const IBL_REGULAR_SEASON_STARTING_MONTH = 11;
    const IBL_ALL_STAR_MONTH = 02;
    const IBL_REGULAR_SEASON_ENDING_MONTH = 05;
    const IBL_PLAYOFF_MONTH = 06;

    /**
     * Constructor - initializes season data from database
     * 
     * @param \mysqli $db Active mysqli connection
     * @throws \RuntimeException If connection is invalid (error code 1002)
     */
    public function __construct(object $db)
    {
        parent::__construct($db);

        // Bulk-fetch all needed settings in a single query
        $settings = $this->getBulkSettings([
            'Current Season Phase',
            'Current Season Ending Year',
            'Allow Trades',
            'Allow Waiver Moves',
            'Free Agency Notifications',
        ]);

        $this->phase = $settings['Current Season Phase'] ?? '';

        $this->endingYear = (int)($settings['Current Season Ending Year'] ?? '0');
        $this->beginningYear = $this->endingYear - 1;

        $this->regularSeasonStartDate = new \DateTime("$this->beginningYear-" . Season::IBL_REGULAR_SEASON_STARTING_MONTH . "-01");
        $this->postAllStarStartDate = new \DateTime("$this->endingYear-" . Season::IBL_ALL_STAR_MONTH . "-04");
        $this->playoffsStartDate = new \DateTime("$this->endingYear-" . Season::IBL_PLAYOFF_MONTH . "-01");
        $this->playoffsEndDate = new \DateTime("$this->endingYear-" . Season::IBL_PLAYOFF_MONTH . "-30");

        $arrayLastSimDates = $this->getLastSimDatesArray();
        $this->lastSimNumber = $arrayLastSimDates["Sim"];
        $this->lastSimStartDate = $arrayLastSimDates["Start Date"];
        $this->lastSimEndDate = $arrayLastSimDates["End Date"];

        $this->projectedNextSimEndDate = $this->getProjectedNextSimEndDate($this->lastSimEndDate);

        $this->allowTrades = $settings['Allow Trades'] ?? '';
        $this->allowWaivers = $settings['Allow Waiver Moves'] ?? '';

        $this->freeAgencyNotificationsState = $settings['Free Agency Notifications'] ?? '';
    }

    /**
     * Get the phase-specific simulation number for the current sim
     *
     * Calculates which sim number this is within the current phase.
     * Example: Overall Sim #42 might be Regular Season Sim #15.
     *
     * @return int Phase-specific sim number (falls back to overall if 0)
     */
    public function getPhaseSpecificSimNumber(): int
    {
        return $this->calculatePhaseSimNumber($this->lastSimNumber, $this->phase, $this->endingYear);
    }

    /**
     * Calculate phase-specific sim number for any sim/phase/season combination
     *
     * Counts sims within the phase's date range up to the given overall sim number.
     * Uses `End Date` (not `Start Date`) because the first sim of a phase can have
     * a Start Date in the prior phase's month.
     *
     * @param int $overallSimNumber The overall sim number to calculate for
     * @param string $phase The season phase
     * @param int $seasonYear The season ending year
     * @return int Phase-specific sim number (falls back to overall if 0)
     */
    public function calculatePhaseSimNumber(int $overallSimNumber, string $phase, int $seasonYear): int
    {
        $beginningYear = $seasonYear - 1;

        switch ($phase) {
            case 'Preseason':
                $phaseStartDate = sprintf('%d-%02d-01', self::IBL_PRESEASON_YEAR, self::IBL_REGULAR_SEASON_STARTING_MONTH);
                $phaseEndDate = sprintf('%d-%02d-30', self::IBL_PRESEASON_YEAR + 1, self::IBL_REGULAR_SEASON_ENDING_MONTH);
                break;
            case 'HEAT':
                $phaseStartDate = sprintf('%d-%02d-01', $beginningYear, self::IBL_HEAT_MONTH);
                $phaseEndDate = sprintf('%d-%02d-30', $beginningYear, self::IBL_HEAT_MONTH);
                break;
            case 'Playoffs':
                $phaseStartDate = sprintf('%d-%02d-01', $seasonYear, self::IBL_PLAYOFF_MONTH);
                $phaseEndDate = sprintf('%d-%02d-30', $seasonYear, self::IBL_PLAYOFF_MONTH);
                break;
            default: // Regular Season (and fallback for other phases)
                $phaseStartDate = sprintf('%d-%02d-01', $beginningYear, self::IBL_REGULAR_SEASON_STARTING_MONTH);
                $phaseEndDate = sprintf('%d-%02d-30', $seasonYear, self::IBL_REGULAR_SEASON_ENDING_MONTH);
                break;
        }

        /** @var array{cnt: int}|null $result */
        $result = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM ibl_sim_dates WHERE `End Date` BETWEEN ? AND ? AND Sim <= ?",
            "ssi",
            $phaseStartDate,
            $phaseEndDate,
            $overallSimNumber
        );

        $phaseSimNumber = $result['cnt'] ?? 0;

        // Fallback to overall sim number for non-game phases (Draft, Free Agency, etc.)
        return $phaseSimNumber > 0 ? $phaseSimNumber : $overallSimNumber;
    }

    /**
     * Bulk-fetch multiple settings in a single query
     *
     * @param list<string> $names Setting names to fetch
     * @return array<string, string> Map of setting name → value
     */
    private function getBulkSettings(array $names): array
    {
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $types = str_repeat('s', count($names));

        /** @var list<array{name: string, value: string}> $rows */
        $rows = $this->fetchAll(
            "SELECT name, value FROM ibl_settings WHERE name IN ({$placeholders})",
            $types,
            ...$names
        );

        /** @var array<string, string> $map */
        $map = [];
        foreach ($rows as $row) {
            $map[$row['name']] = $row['value'];
        }

        return $map;
    }

    /**
     * Get current season phase
     * 
     * @return string Current season phase (e.g., 'Regular Season', 'Playoffs', 'Free Agency')
     */
    public function getSeasonPhase(): string
    {
        /** @var array{value: string}|null $result */
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
        /** @var array{value: string}|null $result */
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
        /** @var array{Date: string}|null $result */
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
        /** @var array{Date: string}|null $result */
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
     * @return array{Sim: int, 'Start Date': string, 'End Date': string}
     */
    public function getLastSimDatesArray(): array
    {
        /** @var array{Sim: int, 'Start Date': string, 'End Date': string}|null $result */
        $result = $this->fetchOne(
            "SELECT * FROM ibl_sim_dates ORDER BY sim DESC LIMIT 1"
        );

        return $result ?? ['Sim' => 0, 'Start Date' => '', 'End Date' => ''];
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

        $lastSimEndDateObj = new \DateTime($lastSimEndDate);
        $interval = new \DateInterval('P' . $simLengthInDays . 'D');
        $projectedNextSimEndDate = date_add($lastSimEndDateObj, $interval);

        // override $projectedNextSimEndDate to account for the All-Star Break
        $allStarBreakStart = new \DateTime("$this->endingYear-01-31");
        $allStarBreakEnd = new \DateTime("$this->endingYear-02-05");
        if (
            $projectedNextSimEndDate > $allStarBreakStart
            && $projectedNextSimEndDate <= $allStarBreakEnd
        ) {
            $postAllStarStart = new \DateTime("$this->endingYear-" . self::IBL_ALL_STAR_MONTH . "-04");
            $projectedNextSimEndDate = date_add($postAllStarStart, new \DateInterval('P' . $simLengthInDays . 'D'));
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
        /** @var array{value: string}|null $result */
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
        /** @var array{value: string}|null $result */
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
        /** @var array{value: string}|null $result */
        $result = $this->fetchOne(
            "SELECT value FROM ibl_settings WHERE name = ? LIMIT 1",
            "s",
            "Free Agency Notifications"
        );

        return $result['value'] ?? '';
    }
}