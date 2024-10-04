<?php

class Game
{
    public $date;
    public $dateObject;
    public $boxScoreID;

    public $visitorTeamID;
    public $homeTeamID;

    public $visitorScore;
    public $homeScore;

    public $isUnplayed;
    public $winningTeamID;

    public $opposingTeamID;
    public $userTeamLocationPrefix;

    public function __construct($scheduleRow)
    {
        $this->date = $scheduleRow['Date'];
        $this->dateObject = date_create($this->date);
        $this->boxScoreID = $scheduleRow['BoxID'];

        $this->visitorTeamID = $scheduleRow['Visitor'];
        $this->homeTeamID = $scheduleRow['Home'];

        $this->visitorScore = $scheduleRow['VScore'];
        $this->homeScore = $scheduleRow['HScore'];

        $this->isUnplayed = ($this->visitorScore == $this->homeScore);
        $this->winningTeamID = $this->visitorScore > $this->homeScore ? $this->visitorTeamID : $this->homeTeamID;
    }

    public function getOpposingTeamID($userTeamID)
    {
        $this->opposingTeamID = $this->visitorTeamID == $userTeamID ? $this->homeTeamID : $this->visitorTeamID;
        return $this->opposingTeamID;
    }

    public function getUserTeamLocationPrefix($userTeamID)
    {
        $this->userTeamLocationPrefix = $this->visitorTeamID == $userTeamID ? "@" : "vs";
        return $this->userTeamLocationPrefix;
    }
}