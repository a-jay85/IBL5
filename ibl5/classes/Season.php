<?php

declare(strict_types=1);

/**
 * Season - IBL season information and configuration
 *
 * Entity class providing season phase, dates, settings, and configuration.
 * Database queries are delegated to SeasonQueryRepository.
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
 */
class Season
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

    private ?string $lastRegularSeasonGameDate = null;

    const IBL_PRESEASON_YEAR = 9998;
    const IBL_OLYMPICS_MONTH = 8;
    const IBL_HEAT_MONTH = 10;
    const IBL_REGULAR_SEASON_STARTING_MONTH = 11;
    const IBL_ALL_STAR_MONTH = 02;
    const IBL_REGULAR_SEASON_ENDING_MONTH = 05;
    const IBL_PLAYOFF_MONTH = 06;

    const IBL_ALL_STAR_BREAK_START_DAY = 1;   // Feb 1 - first day with no regular season games
    const IBL_RISING_STARS_GAME_DAY = 2;      // Feb 2 - Rising Stars game
    const IBL_ALL_STAR_GAME_DAY = 3;          // Feb 3 - All-Star Game
    const IBL_ALL_STAR_BREAK_END_DAY = 4;     // Feb 4 - last day with no regular season games
    const IBL_POST_ALL_STAR_FIRST_DAY = 5;    // Feb 5 - first valid sim day after break

    private \mysqli $db;
    private Season\Contracts\SeasonQueryRepositoryInterface $queryRepo;

    /**
     * Constructor - initializes season data from database
     *
     * @param \mysqli $db Active mysqli connection
     */
    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->queryRepo = new Season\SeasonQueryRepository($db);

        // Bulk-fetch all needed settings in a single query
        $settings = $this->queryRepo->getBulkSettings([
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
        $this->postAllStarStartDate = new \DateTime(sprintf('%d-%02d-%02d', $this->endingYear, self::IBL_ALL_STAR_MONTH, self::IBL_POST_ALL_STAR_FIRST_DAY));
        $this->playoffsStartDate = new \DateTime("$this->endingYear-" . Season::IBL_PLAYOFF_MONTH . "-01");
        $this->playoffsEndDate = new \DateTime("$this->endingYear-" . Season::IBL_PLAYOFF_MONTH . "-30");

        $this->lastRegularSeasonGameDate = $this->queryRepo->getLastRegularSeasonGameDate($this->endingYear);

        $arrayLastSimDates = $this->queryRepo->getLastSimDatesArray();
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
        return $this->queryRepo->calculatePhaseSimNumber($this->lastSimNumber, $this->phase, $this->endingYear);
    }

    /**
     * Calculate phase-specific sim number for any sim/phase/season combination
     *
     * Delegates to SeasonQueryRepository.
     *
     * @param int $overallSimNumber The overall sim number to calculate for
     * @param string $phase The season phase
     * @param int $seasonYear The season ending year
     * @return int Phase-specific sim number (falls back to overall if 0)
     */
    public function calculatePhaseSimNumber(int $overallSimNumber, string $phase, int $seasonYear): int
    {
        return $this->queryRepo->calculatePhaseSimNumber($overallSimNumber, $phase, $seasonYear);
    }

    /**
     * Get first box score date
     *
     * Delegates to SeasonQueryRepository.
     *
     * @return string First box score date from database
     */
    public function getFirstBoxScoreDate(): string
    {
        return $this->queryRepo->getFirstBoxScoreDate();
    }

    /**
     * Get last box score date
     *
     * Delegates to SeasonQueryRepository.
     *
     * @return string Last box score date from database
     */
    public function getLastBoxScoreDate(): string
    {
        return $this->queryRepo->getLastBoxScoreDate();
    }

    /**
     * Set last sim dates array
     *
     * Delegates to SeasonQueryRepository.
     *
     * @param string $newSimNumber New sim number
     * @param string $newSimStartDate New sim start date (YYYY-MM-DD format)
     * @param string $newSimEndDate New sim end date (YYYY-MM-DD format)
     * @return int Number of affected rows
     */
    public function setLastSimDatesArray(string $newSimNumber, string $newSimStartDate, string $newSimEndDate): int
    {
        return $this->queryRepo->setLastSimDatesArray($newSimNumber, $newSimStartDate, $newSimEndDate);
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
        $projectedNextSimEndDate = date_add(clone $lastSimEndDateObj, $interval);

        // Adjust projected end date to skip over the All-Star Break (Feb 1-4)
        $breakFirstDay = new \DateTime(sprintf(
            '%d-%02d-%02d',
            $this->endingYear,
            self::IBL_ALL_STAR_MONTH,
            self::IBL_ALL_STAR_BREAK_START_DAY
        ));
        $breakLengthDays = self::IBL_ALL_STAR_BREAK_END_DAY - self::IBL_ALL_STAR_BREAK_START_DAY + 1;

        if ($lastSimEndDateObj < $breakFirstDay && $projectedNextSimEndDate >= $breakFirstDay) {
            $projectedNextSimEndDate = date_add(
                $projectedNextSimEndDate,
                new \DateInterval('P' . $breakLengthDays . 'D')
            );
        }

        // Adjust projected end date to skip the RS-to-Playoffs gap
        if ($this->lastRegularSeasonGameDate !== null) {
            $lastRSGameDate = new \DateTime($this->lastRegularSeasonGameDate);
            $gapStartDate = date_add(clone $lastRSGameDate, new \DateInterval('P1D'));

            if ($gapStartDate < $this->playoffsStartDate) {
                if ($lastSimEndDateObj < $gapStartDate && $projectedNextSimEndDate >= $gapStartDate) {
                    $gapDays = (int) $gapStartDate->diff($this->playoffsStartDate)->days;
                    $projectedNextSimEndDate = date_add(
                        $projectedNextSimEndDate,
                        new \DateInterval('P' . $gapDays . 'D')
                    );
                }
            }
        }

        return $projectedNextSimEndDate;
    }
}
