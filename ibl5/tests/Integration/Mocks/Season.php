<?php

namespace Tests\Integration\Mocks;

/**
 * Mock Season class for testing
 */
class Season
{
    public $phase = 'Regular Season';
    public $endingYear = 2024;
    public $beginningYear = 2023;
    public $regularSeasonStartDate;
    public $postAllStarStartDate;
    public $playoffsStartDate;
    public $playoffsEndDate;
    public $lastSimNumber = 1;
    public $lastSimStartDate = '2024-01-01';
    public $lastSimEndDate = '2024-01-02';
    public $projectedNextSimEndDate = '2024-01-03';
    public $allowTrades = 'Yes';
    public $allowWaivers = 'Yes';
    public $freeAgencyNotificationsState = 'Off';
    
    const IBL_PRESEASON_YEAR = 9998;
    const IBL_OLYMPICS_MONTH = 8;
    const IBL_HEAT_MONTH = 10;
    const IBL_REGULAR_SEASON_STARTING_MONTH = 11;
    const IBL_ALL_STAR_MONTH = 2;
    const IBL_REGULAR_SEASON_ENDING_MONTH = 5;
    const IBL_PLAYOFF_MONTH = 6;
    
    protected $db;
    
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
        $this->postAllStarStartDate = date_create("2024-02-04");
        $this->playoffsStartDate = date_create("2024-06-01");
        $this->playoffsEndDate = date_create("2024-06-30");
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
    private function getLastSimDatesArray()
    {
        return [
            'Sim' => $this->lastSimNumber,
            'Start Date' => $this->lastSimStartDate, // Mocks DATE column format
            'End Date' => $this->lastSimEndDate // Mocks DATE column format
        ];
    }
    
    private function getProjectedNextSimEndDate($db, $lastSimEndDate)
    {
        return $this->projectedNextSimEndDate;
    }
}
