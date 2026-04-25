<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

/**
 * Mock Season class for testing
 */
class Season
{
    public string $phase = 'Regular Season';
    public int $endingYear = 2024;
    public int $beginningYear = 2023;
    public ?\DateTime $regularSeasonStartDate = null;
    public ?\DateTime $postAllStarStartDate = null;
    public ?\DateTime $playoffsStartDate = null;
    public ?\DateTime $playoffsEndDate = null;
    public int $lastSimNumber = 1;
    public string $lastSimStartDate = '2024-01-01';
    public string $lastSimEndDate = '2024-01-02';
    public \DateTimeInterface|string $projectedNextSimEndDate;
    public string $allowTrades = 'Yes';
    public string $allowWaivers = 'Yes';
    public string $showDraftLink = 'Off';
    public string $freeAgencyNotificationsState = 'Off';

    public ?string $lastRegularSeasonGameDate = null;
    public int $simLengthInDays = 7;

    const IBL_OLYMPICS_MONTH = 8;
    const IBL_HEAT_MONTH = 10;
    const IBL_REGULAR_SEASON_STARTING_MONTH = 11;
    const IBL_ALL_STAR_MONTH = 2;
    const IBL_REGULAR_SEASON_ENDING_MONTH = 5;
    const IBL_PLAYOFF_MONTH = 6;

    const IBL_ALL_STAR_BREAK_START_DAY = 1;
    const IBL_RISING_STARS_GAME_DAY = 2;
    const IBL_ALL_STAR_GAME_DAY = 3;
    const IBL_ALL_STAR_BREAK_END_DAY = 4;
    const IBL_POST_ALL_STAR_FIRST_DAY = 5;
    
    protected object $db;
    
    /**
     * Mock constructor for testing - accepts mysqli or legacy db object
     * 
     * @param object $db Database connection (mysqli or legacy mock)
     */
    public function __construct(object $db)
    {
        $this->db = $db;
        // Initialize properties without database calls for testing
        $this->phase = 'Regular Season';
        $this->endingYear = 2024;
        $this->beginningYear = 2023;
        $this->regularSeasonStartDate = date_create("2023-11-01");
        $this->postAllStarStartDate = date_create("2024-02-05");
        $this->playoffsStartDate = date_create("2024-06-01");
        $this->playoffsEndDate = date_create("2024-06-30");
        $this->projectedNextSimEndDate = date_create("2024-01-10");
    }
    
    // Mock the methods that would normally query the database
    public function getSeasonPhase(): string
    {
        return $this->phase;
    }

    /**
     * Check if current season phase is free agency (mock implementation)
     */
    public function isFreeAgencyPhase(): bool
    {
        return $this->phase === 'Free Agency';
    }

    /**
     * Check if current season phase is an offseason phase (Draft or Free Agency)
     */
    public function isOffseasonPhase(): bool
    {
        return $this->phase === 'Draft' || $this->phase === 'Free Agency';
    }

    /**
     * Check if trades are currently allowed (mock implementation)
     *
     * @see Season::areTradesAllowed()
     */
    public function areTradesAllowed(): bool
    {
        if ($this->phase === 'Draft' || $this->phase === 'Free Agency' || $this->phase === 'Preseason') {
            return true;
        }

        return $this->allowTrades === 'Yes';
    }

    /**
     * Check if waivers are currently allowed (mock implementation)
     *
     * @see Season::areWaiversAllowed()
     */
    public function areWaiversAllowed(): bool
    {
        if (in_array($this->phase, ['HEAT', 'Regular Season', 'Playoffs'], true)) {
            return true;
        }

        if ($this->phase === 'Draft') {
            return false;
        }

        return $this->allowWaivers === 'Yes';
    }
    
    public function getSeasonEndingYear(): string
    {
        return (string)$this->endingYear;
    }
    
    /**
     * Get last sim dates array (mock implementation)
     *
     * @return array Array with keys: sim, start_date, end_date
     */
    private function getLastSimDatesArray(): array
    {
        return [
            'sim' => $this->lastSimNumber,
            'start_date' => $this->lastSimStartDate,
            'end_date' => $this->lastSimEndDate,
        ];
    }
    
    public function getProjectedNextSimEndDate(string $lastSimEndDate): \DateTimeInterface
    {
        $lastSimEndDateObj = new \DateTime($lastSimEndDate);
        $interval = new \DateInterval('P' . $this->simLengthInDays . 'D');
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
        if ($this->lastRegularSeasonGameDate !== null && $this->playoffsStartDate !== null) {
            $lastRSGameDate = new \DateTime($this->lastRegularSeasonGameDate);
            $gapStartDate = date_add(clone $lastRSGameDate, new \DateInterval('P1D'));

            if ($gapStartDate < $this->playoffsStartDate) {
                if ($lastSimEndDateObj < $gapStartDate && $projectedNextSimEndDate >= $gapStartDate) {
                    $gapDays = (int) $gapStartDate->diff($this->playoffsStartDate)->days;
                    $projectedNextSimEndDate = date_add(
                        $projectedNextSimEndDate,
                        new \DateInterval('P' . $gapDays . 'D')
                    );
                } elseif ($lastSimEndDateObj >= $gapStartDate && $lastSimEndDateObj < $this->playoffsStartDate) {
                    $remainingGapDays = (int) $lastSimEndDateObj->diff($this->playoffsStartDate)->days;
                    $projectedNextSimEndDate = date_add(
                        $projectedNextSimEndDate,
                        new \DateInterval('P' . $remainingGapDays . 'D')
                    );
                }
            }
        }

        return $projectedNextSimEndDate;
    }

    /**
     * Get last box score date (mock implementation)
     */
    public function getLastBoxScoreDate(): string
    {
        return $this->lastSimEndDate;
    }

    /**
     * Get first box score date (mock implementation)
     */
    public function getFirstBoxScoreDate(): string
    {
        return $this->lastSimStartDate;
    }

    /**
     * Set last sim dates array (mock implementation)
     *
     * @return int Number of affected rows (always 1 for mock)
     */
    public function setLastSimDatesArray(string $newSimNumber, string $newSimStartDate, string $newSimEndDate): int
    {
        $this->lastSimNumber = (int) $newSimNumber;
        $this->lastSimStartDate = $newSimStartDate;
        $this->lastSimEndDate = $newSimEndDate;
        return 1;
    }
}
