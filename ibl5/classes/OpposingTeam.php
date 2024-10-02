<?php

class OpposingTeam
{
    public $teamID;
    public $name;
    public $seasonRecord;

    public function __construct($db, $teamID, Shared $shared, $seasonRecordsArray)
    {
        $this->teamID = $teamID;
        $this->name = $shared->getTeamnameFromTid($this->teamID);
        $this->seasonRecord = $seasonRecordsArray[$this->teamID];
    }
}