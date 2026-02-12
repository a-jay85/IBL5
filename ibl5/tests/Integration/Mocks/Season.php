<?php

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
    public string $freeAgencyNotificationsState = 'Off';
    
    const IBL_PRESEASON_YEAR = 9998;
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
    
    public function getSeasonEndingYear(): string
    {
        return (string)$this->endingYear;
    }
    
    /**
     * Get last sim dates array (mock implementation)
     * 
     * Note: 'Start Date' and 'End Date' mimic DATE column format (YYYY-MM-DD)
     * 
     * @return array Array with keys: Sim, 'Start Date', 'End Date'
     */
    private function getLastSimDatesArray(): array
    {
        return [
            'Sim' => $this->lastSimNumber,
            'Start Date' => $this->lastSimStartDate, // Mocks DATE column format
            'End Date' => $this->lastSimEndDate // Mocks DATE column format
        ];
    }
    
    private function getProjectedNextSimEndDate(object $db, string $lastSimEndDate): string
    {
        return $this->projectedNextSimEndDate;
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
