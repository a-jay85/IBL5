<?php

class Season
{
    protected $db;

    public $phase;

    public $beginningYear;
    public $endingYear;

    public $regularSeasonStartDate;
    public $postAllStarStartDate;

    public $lastSimNumber;
    public $lastSimStartDate;
    public $lastSimEndDate;

    public $projectedNextSimEndDate;

    public $allowTrades;
    public $allowWaivers;

    public $freeAgencyNotificationsState;

    const IBL_PRESEASON_MONTH = 9;
    const IBL_HEAT_MONTH = 10;
    const IBL_REGULAR_SEASON_STARTING_MONTH = 11;
    const IBL_ALL_STAR_MONTH = 02;
    const IBL_REGULAR_SEASON_ENDING_MONTH = 05;
    const IBL_PLAYOFF_MONTH = 06;

    public function __construct($db)
    {
        $this->db = $db;

        $this->phase = $this->getSeasonPhase();

        $this->endingYear = (int)$this->getSeasonEndingYear(); // Cast to integer since column type is VARCHAR
        $this->beginningYear = $this->endingYear - 1;

        $this->regularSeasonStartDate = date_create("$this->beginningYear-" . Season::IBL_REGULAR_SEASON_STARTING_MONTH . "-01");
        $this->postAllStarStartDate = date_create("$this->endingYear-" . Season::IBL_ALL_STAR_MONTH . "-04");

        $arrayLastSimDates = $this->getLastSimDatesArray();
        $this->lastSimNumber = $arrayLastSimDates["Sim"];
        $this->lastSimStartDate = $arrayLastSimDates["Start Date"];
        $this->lastSimEndDate = $arrayLastSimDates["End Date"];

        $this->projectedNextSimEndDate = $this->getProjectedNextSimEndDate($db, $this->lastSimEndDate);

        $this->allowTrades = $this->getAllowTradesStatus();
        $this->allowWaivers = $this->getAllowWaiversStatus();

        $this->freeAgencyNotificationsState = $this->getFreeAgencyNotificationsState();
    }

    public function getSeasonPhase()
    {
        $querySeasonPhase = $this->db->sql_query("SELECT value
            FROM ibl_settings
            WHERE name = 'Current Season Phase'
            LIMIT 1");

        return $this->db->sql_result($querySeasonPhase, 0, 'value');
    }

    public function getSeasonEndingYear()
    {
        $querySeasonEndingYear = $this->db->sql_query("SELECT value
            FROM ibl_settings
            WHERE name = 'Current Season Ending Year'
            LIMIT 1");

        return $this->db->sql_result($querySeasonEndingYear, 0, 'value');
    }

    public function getFirstBoxScoreDate()
    {
        $queryFirstBoxScoreDate = $this->db->sql_query("SELECT Date
            FROM ibl_box_scores
            ORDER BY Date ASC
            LIMIT 1");

        return $this->db->sql_result($queryFirstBoxScoreDate, 0, 'Date');
    }

    public function getLastBoxScoreDate()
    {
        $queryLastBoxScoreDate = $this->db->sql_query("SELECT Date
            FROM ibl_box_scores
            ORDER BY Date DESC
            LIMIT 1");

        return $this->db->sql_result($queryLastBoxScoreDate, 0, 'Date');
    }

    public function getLastSimDatesArray()
    {
        $queryLastSimDates = $this->db->sql_query("SELECT *
            FROM ibl_sim_dates
            ORDER BY sim DESC
            LIMIT 1");

        return $this->db->sql_fetch_assoc($queryLastSimDates);
    }

    public function setLastSimDatesArray($newSimNumber, $newSimStartDate, $newSimEndDate)
    {
        $querySimDates = $this->db->sql_query("INSERT INTO ibl_sim_dates
                    (`Sim`,
                    `Start Date`,
                    `End Date`)
            VALUES  ('$newSimNumber',
                    '$newSimStartDate',
                    '$newSimEndDate'); ");

        return $querySimDates;
    }

    public function getProjectedNextSimEndDate($db, $lastSimEndDate)
    {
        $lastSimEndDate = date_create($lastSimEndDate);
        $projectedNextSimEndDate = date_add($lastSimEndDate, date_interval_create_from_date_string(League::getSimLengthInDays($db) . ' days'));

        // override $projectedNextSimEndDate to account for the blank week at end of HEAT
        if (
            $projectedNextSimEndDate > date_create("$this->beginningYear-10-21")
            AND $projectedNextSimEndDate < date_create("$this->beginningYear-11-02")
        ) {
            $projectedNextSimEndDate = date_add($this->regularSeasonStartDate, date_interval_create_from_date_string(League::getSimLengthInDays($db) . ' days'));
        }
    
        // override $projectedNextSimEndDate to account for the All-Star Break
        if (
            $projectedNextSimEndDate > date_create("$this->endingYear-01-31")
            AND $projectedNextSimEndDate <= date_create("$this->endingYear-02-05")
        ) {
            $projectedNextSimEndDate = date_add($this->postAllStarStartDate, date_interval_create_from_date_string(League::getSimLengthInDays($db) . ' days'));
        }

        return $projectedNextSimEndDate;
    }

    public function getAllowTradesStatus()
    {
        $queryAllowTradesStatus = $this->db->sql_query("SELECT value
            FROM ibl_settings
            WHERE name = 'Allow Trades'
            LIMIT 1");

        return $this->db->sql_result($queryAllowTradesStatus, 0, 'value');
    }

    public function getAllowWaiversStatus()
    {
        $queryAllowWaiversStatus = $this->db->sql_query("SELECT value
            FROM ibl_settings
            WHERE name = 'Allow Waiver Moves'
            LIMIT 1");

        return $this->db->sql_result($queryAllowWaiversStatus, 0, 'value');
    }

    public function getFreeAgencyNotificationsState()
    {
        $queryFreeAgencyNotificationsState = $this->db->sql_query("SELECT value
            FROM ibl_settings
            WHERE name = 'Free Agency Notifications'
            LIMIT 1");

        return $this->db->sql_result($queryFreeAgencyNotificationsState, 0, 'value');
    }
}