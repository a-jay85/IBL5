<?php

declare(strict_types=1);

class Game
{
    public string $date;
    public \DateTime|false $dateObject;
    public int $boxScoreID;

    public int $visitorTeamID;
    public int $homeTeamID;

    public int $visitorScore;
    public int $homeScore;

    public bool $isUnplayed;
    public int $winningTeamID;

    public int $opposingTeamID;
    public string $userTeamLocationPrefix;

    /**
     * @param array{Date: string, BoxID: int, Visitor: int, Home: int, VScore: int, HScore: int} $scheduleRow
     */
    public function __construct(array $scheduleRow)
    {
        $this->date = $scheduleRow['Date'];
        $this->dateObject = date_create($this->date);
        $this->boxScoreID = $scheduleRow['BoxID'];

        $this->visitorTeamID = $scheduleRow['Visitor'];
        $this->homeTeamID = $scheduleRow['Home'];

        $this->visitorScore = $scheduleRow['VScore'];
        $this->homeScore = $scheduleRow['HScore'];

        $this->isUnplayed = ($this->visitorScore === $this->homeScore);
        $this->winningTeamID = $this->visitorScore > $this->homeScore ? $this->visitorTeamID : $this->homeTeamID;
    }

    public function getOpposingTeamID(int $userTeamID): int
    {
        $this->opposingTeamID = $this->visitorTeamID === $userTeamID ? $this->homeTeamID : $this->visitorTeamID;
        return $this->opposingTeamID;
    }

    public function getUserTeamLocationPrefix(int $userTeamID): string
    {
        $this->userTeamLocationPrefix = $this->visitorTeamID === $userTeamID ? "@" : "vs";
        return $this->userTeamLocationPrefix;
    }
}