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
     * @param array{game_date: string, box_id: int, visitor_teamid: int, home_teamid: int, visitor_score: int, home_score: int, game_of_that_day?: int|null} $scheduleRow
     */
    public function __construct(array $scheduleRow)
    {
        $this->date = $scheduleRow['game_date'];
        $this->dateObject = date_create($this->date);
        $this->boxScoreID = $scheduleRow['box_id'];
        $this->gameOfThatDay = (int) ($scheduleRow['game_of_that_day'] ?? 0);

        $this->visitor_teamid = $scheduleRow['visitor_teamid'];
        $this->home_teamid = $scheduleRow['home_teamid'];

        $this->visitorScore = $scheduleRow['visitor_score'];
        $this->homeScore = $scheduleRow['home_score'];

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