<?php

declare(strict_types=1);

namespace LeagueSchedule;

class Game
{
    public string $date;
    public \DateTime|false $dateObject;
    public int $boxScoreID;
    public int $gameOfThatDay;

    public int $visitor_teamid;
    public int $home_teamid;

    public int $visitorScore;
    public int $homeScore;

    public bool $isUnplayed;
    public int $winningTeamID;

    public int $opposingTeamID;
    public string $userTeamLocationPrefix;

    /**
     * @param array{Date: string, BoxID: int, Visitor: int, Home: int, VScore: int, HScore: int, gameOfThatDay?: int|null} $scheduleRow
     */
    public function __construct(array $scheduleRow)
    {
        $this->date = $scheduleRow['Date'];
        $this->dateObject = date_create($this->date);
        $this->boxScoreID = $scheduleRow['BoxID'];
        $this->gameOfThatDay = (int) ($scheduleRow['gameOfThatDay'] ?? 0);

        $this->visitor_teamid = $scheduleRow['Visitor'];
        $this->home_teamid = $scheduleRow['Home'];

        $this->visitorScore = $scheduleRow['VScore'];
        $this->homeScore = $scheduleRow['HScore'];

        $this->isUnplayed = ($this->visitorScore === $this->homeScore);
        $this->winningTeamID = $this->visitorScore > $this->homeScore ? $this->visitor_teamid : $this->home_teamid;
    }

    public function getOpposingTeamID(int $userTeamID): int
    {
        $this->opposingTeamID = $this->visitor_teamid === $userTeamID ? $this->home_teamid : $this->visitor_teamid;
        return $this->opposingTeamID;
    }

    public function getUserTeamLocationPrefix(int $userTeamID): string
    {
        $this->userTeamLocationPrefix = $this->visitor_teamid === $userTeamID ? "@" : "vs";
        return $this->userTeamLocationPrefix;
    }
}