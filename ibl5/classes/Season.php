<?php

class Season
{
    protected $db;

    public $phase;

    public $beginningYear;
    public $endingYear;

    public $lastSimStartDate;
    public $lastSimEndDate;

    public function __construct($db)
    {
        $this->db = $db;

        $this->phase = $this->getSeasonPhase();

        $this->endingYear = $this->getSeasonEndingYear();
        $this->beginningYear = $this->endingYear - 1;

        $arrayLastSimDates = $this->getLastSimDatesArray();
        $this->lastSimStartDate = $arrayLastSimDates["Start Date"];
        $this->lastSimEndDate = $arrayLastSimDates["End Date"];
    }

    public function getSeasonPhase()
    {
        $querySeasonPhase = $this->db->sql_query("SELECT value
            FROM ibl_settings
            WHERE name = 'Current Season Phase'
            LIMIT 1");

        return $this->db->sql_result($querySeasonPhase, 0);
    }

    public function getSeasonEndingYear()
    {
        $querySeasonEndingYear = $this->db->sql_query("SELECT value
            FROM ibl_settings
            WHERE name = 'Current Season Ending Year'
            LIMIT 1");

        return $this->db->sql_result($querySeasonEndingYear, 0);
    }

    public function getLastSimDatesArray()
    {
        $queryLastSimDates = $this->db->sql_query("SELECT *
            FROM ibl_sim_dates
            ORDER BY sim DESC
            LIMIT 1");

        return $this->db->sql_fetch_assoc($queryLastSimDates);
    }
}