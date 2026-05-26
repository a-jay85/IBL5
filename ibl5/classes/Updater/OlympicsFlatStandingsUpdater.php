<?php

declare(strict_types=1);

namespace Updater;

class OlympicsFlatStandingsUpdater extends StandingsUpdater
{
    public function update(): void
    {
        echo "<p>Updating the Olympics standings database table...<p>";
        $this->computeAndInsertStandings();
        echo "<p>The Olympics standings table has been updated.<p>";
    }

    protected function computeAndInsertAll(array $standings, array $teamMap): void
    {
        $month = SeasonPhaseHelper::getMonthForPhase($this->season->phase);
        $startDate = $this->season->beginningYear . "-{$month}-01";
        $endDate = $this->season->endingYear . "-05-30";
        $scheduledCounts = $this->repository->fetchScheduledGameCountsPerTeam($startDate, $endDate);

        $log = '';

        foreach ($standings as $team) {
            $totalGames = $team['wins'] + $team['losses'];
            $pct = $totalGames > 0 ? round($team['wins'] / $totalGames, 3) : 0.000;

            $totalScheduled = $scheduledCounts[$team['teamid']] ?? 0;
            $gamesUnplayed = $totalScheduled - $totalGames;

            $leagueRecord = $team['wins'] . '-' . $team['losses'];
            $confRecord = $team['conf_wins'] . '-' . $team['conf_losses'];
            $divRecord = $team['div_wins'] . '-' . $team['div_losses'];
            $homeRecord = $team['home_wins'] . '-' . $team['home_losses'];
            $awayRecord = $team['away_wins'] . '-' . $team['away_losses'];

            $this->repository->upsertStandings([
                'teamid' => $team['teamid'],
                'teamName' => $team['teamName'],
                'leagueRecord' => $leagueRecord,
                'wins' => $team['wins'],
                'losses' => $team['losses'],
                'pct' => $pct,
                'gamesUnplayed' => $gamesUnplayed,
                'conference' => $team['conference'],
                'confGb' => 0.0,
                'confRecord' => $confRecord,
                'division' => $team['division'],
                'divGb' => 0.0,
                'divRecord' => $divRecord,
                'homeRecord' => $homeRecord,
                'awayRecord' => $awayRecord,
                'confWins' => $team['conf_wins'],
                'confLosses' => $team['conf_losses'],
                'divWins' => $team['div_wins'],
                'divLosses' => $team['div_losses'],
                'homeWins' => $team['home_wins'],
                'homeLosses' => $team['home_losses'],
                'awayWins' => $team['away_wins'],
                'awayLosses' => $team['away_losses'],
            ]);

            $log .= "Inserted standings for team: {$team['teamName']}<br>";
        }

        \UI\DebugOutput::display($log, 'Computed Olympics Standings');
    }
}
