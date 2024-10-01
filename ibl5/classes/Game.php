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
    public $winningTeamID;

    public function __construct($db, $scheduleRow)
    {
        $this->date = $scheduleRow['Date'];
        $this->dateObject = date_create($this->date);
        $this->boxScoreID = $scheduleRow['BoxID'];

        $this->visitorTeamID = $scheduleRow['Visitor'];
        $this->homeTeamID = $scheduleRow['Home'];

        $this->visitorScore = $scheduleRow['VScore'];
        $this->homeScore = $scheduleRow['HScore'];

        $this->winningTeamID = $this->visitorScore > $this->homeScore ? $this->visitorTeamID : $this->homeTeamID;
    }
}