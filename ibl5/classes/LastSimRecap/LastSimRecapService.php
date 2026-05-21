<?php

declare(strict_types=1);

namespace LastSimRecap;

use LastSimRecap\Contracts\LastSimRecapRepositoryInterface;
use LastSimRecap\Contracts\LastSimRecapServiceInterface;
use LastSimRecap\Dto\RecapGame;
use LastSimRecap\Dto\RecapInjury;
use LastSimRecap\Dto\RecapSlate;
use LastSimRecap\Dto\RecapStarter;
use Repositories\Contracts\PlayerLookupRepositoryInterface;

class LastSimRecapService implements LastSimRecapServiceInterface
{
    private const POSITIONS = ['PG', 'SG', 'SF', 'PF', 'C'];

    public function __construct(
        private readonly LastSimRecapRepositoryInterface $repo,
        private readonly PlayerLookupRepositoryInterface $playerLookup,
    ) {}

    public function buildSlateForTeam(int $tid): ?RecapSlate
    {
        $window = $this->repo->getLastSimWindow();
        if ($window === null) {
            return null;
        }

        $games = $this->repo->getGamesForTeamInWindow($tid, $window['startDate'], $window['endDate']);
        if ($games === []) {
            return null;
        }

        $teamInfo = $this->repo->getTeamInfo($tid);
        if ($teamInfo === null) {
            return null;
        }

        $recapGames = [];
        foreach ($games as $g) {
            $recapGames[] = $this->buildGame($tid, $g);
        }

        $wins = 0;
        $losses = 0;
        $netMargin = 0;
        $bestMargin = null;
        $bestIdx = 0;
        $worstMargin = null;
        $worstIdx = 0;

        foreach ($recapGames as $idx => $rg) {
            if ($rg->won) {
                $wins++;
            } else {
                $losses++;
            }
            $netMargin += $rg->margin;

            if ($bestMargin === null || $rg->margin > $bestMargin) {
                $bestMargin = $rg->margin;
                $bestIdx = $idx;
            }
            if ($worstMargin === null || $rg->margin < $worstMargin) {
                $worstMargin = $rg->margin;
                $worstIdx = $idx;
            }
        }

        $bestLabel = $this->formatMarginLabel($recapGames[$bestIdx]);
        $worstLabel = $this->formatMarginLabel($recapGames[$worstIdx]);

        // End-of-window record reflects every game played up to the window's
        // last date (inclusive). Slate header W/L counts only games in the
        // window, but the badge under the team name shows season W-L.
        $teamRecord = $this->repo->getTeamRecordAsOf($tid, $window['endDate']);

        return new RecapSlate(
            teamTid: $tid,
            teamCity: $teamInfo['city'],
            teamName: $teamInfo['name'],
            simNumber: $window['sim'],
            startDate: $window['startDate'],
            endDate: $window['endDate'],
            wins: $wins,
            losses: $losses,
            netMargin: $netMargin,
            bestLabel: $bestLabel,
            worstLabel: $worstLabel,
            teamWins: $teamRecord['wins'],
            teamLosses: $teamRecord['losses'],
            games: $recapGames,
        );
    }

    /**
     * @param array{schedId:int,boxId:int,date:string,visitor:int,vScore:int,home:int,hScore:int,year:int} $g
     */
    private function buildGame(int $tid, array $g): RecapGame
    {
        $home = $g['home'] === $tid;
        $oppTid = $home ? $g['visitor'] : $g['home'];
        $yourScore = $home ? $g['hScore'] : $g['vScore'];
        $oppScore = $home ? $g['vScore'] : $g['hScore'];
        $won = $yourScore > $oppScore;
        $margin = $yourScore - $oppScore;

        $oppInfo = $this->repo->getTeamInfo($oppTid);
        $oppCity = $oppInfo['city'] ?? '';
        $oppName = $oppInfo['name'] ?? '';

        $lines = $this->repo->getTeamBoxscoreLines($g['visitor'], $g['home'], $g['date']);
        $margins = [];
        $qLabels = [];
        $ot = false;
        $oppPreWins = 0;
        $oppPreLosses = 0;
        $gameOfThatDay = 0;

        if ($lines !== null) {
            $ot = $lines['visOT'] > 0 || $lines['homeOT'] > 0;
            for ($i = 0; $i < 4; $i++) {
                $yourQ = $home ? $lines['homeQ'][$i] : $lines['visQ'][$i];
                $oppQ = $home ? $lines['visQ'][$i] : $lines['homeQ'][$i];
                $margins[] = $yourQ - $oppQ;
                $qLabels[] = 'Q' . ($i + 1);
            }
            if ($ot) {
                $yourOT = $home ? $lines['homeOT'] : $lines['visOT'];
                $oppOT = $home ? $lines['visOT'] : $lines['homeOT'];
                $margins[] = $yourOT - $oppOT;
                $qLabels[] = 'OT';
            }
            $oppPreWins = $home ? $lines['visitorPreWins'] : $lines['homePreWins'];
            $oppPreLosses = $home ? $lines['visitorPreLosses'] : $lines['homePreLosses'];
            $gameOfThatDay = $lines['gameOfThatDay'];
        }

        // Active injuries as of game date for both teams.
        $yourRoster = $this->repo->getTeamRosterPids($tid);
        $oppRoster = $this->repo->getTeamRosterPids($oppTid);

        $yourInjuries = $this->mapInjuries(
            $yourRoster === [] ? [] : $this->repo->getActiveInjuriesForPlayers($yourRoster, $g['date'])
        );
        $oppInjuries = $this->mapInjuries(
            $oppRoster === [] ? [] : $this->repo->getActiveInjuriesForPlayers($oppRoster, $g['date'])
        );

        // Position-battle starters: snapshot for your team, fallback to box.
        $yourStarters = $this->repo->getStarterPidsFromSnapshot($tid, $g['date'])
            ?? $this->repo->getStarterPidsFromBoxScores($g['schedId'], $tid);
        $oppStarters = $this->repo->getStarterPidsFromSnapshot($oppTid, $g['date'])
            ?? $this->repo->getStarterPidsFromBoxScores($g['schedId'], $oppTid);

        // Build a set of pids with a NEW injury today for quick hurt-flag lookup.
        $hurtPids = [];
        foreach ($yourInjuries as $inj) {
            if ($inj->isNew) {
                $hurtPids[$inj->pid] = true;
            }
        }

        $starters = [];
        foreach (self::POSITIONS as $pos) {
            $yourPid = $yourStarters[$pos] ?? 0;
            $oppPid = $oppStarters[$pos] ?? 0;
            $yourLine = $yourPid !== 0 ? $this->repo->getPlayerLineForGame($yourPid, $g['schedId']) : null;
            $oppLine = $oppPid !== 0 ? $this->repo->getPlayerLineForGame($oppPid, $g['schedId']) : null;

            $starters[] = new RecapStarter(
                pos: $pos,
                youPid: $yourPid,
                youName: $this->shortName($this->lookupPlayerName($yourPid) ?: ($yourLine['name'] ?? '')),
                youPts: $yourLine['pts'] ?? 0,
                youHurt: isset($hurtPids[$yourPid]),
                oppPid: $oppPid,
                oppName: $this->shortName($this->lookupPlayerName($oppPid) ?: ($oppLine['name'] ?? '')),
                oppPts: $oppLine['pts'] ?? 0,
            );
        }

        return new RecapGame(
            schedId: $g['schedId'],
            boxId: $g['boxId'],
            gameOfThatDay: $gameOfThatDay,
            date: $g['date'],
            home: $home,
            won: $won,
            yourScore: $yourScore,
            oppScore: $oppScore,
            margin: $margin,
            ot: $ot,
            margins: $margins,
            qLabels: $qLabels,
            oppTid: $oppTid,
            oppCity: $oppCity,
            oppName: $oppName,
            oppPreWins: $oppPreWins,
            oppPreLosses: $oppPreLosses,
            yourInjuries: $yourInjuries,
            oppInjuries: $oppInjuries,
            starters: $starters,
        );
    }

    /**
     * @param list<array{pid:int,name:string,pos:string,date:string,injuryDescription:string,injuryGamesMissed:int,daysRemaining:int,isNew:bool}> $rows
     * @return list<RecapInjury>
     */
    private function mapInjuries(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[] = new RecapInjury(
                pid: $r['pid'],
                name: $this->shortName($r['name']),
                pos: $r['pos'],
                description: $r['injuryDescription'],
                gamesMissed: $r['injuryGamesMissed'],
                daysRemaining: $r['daysRemaining'],
                isNew: $r['isNew'],
            );
        }
        return $out;
    }

    private function lookupPlayerName(int $pid): string
    {
        if ($pid === 0) {
            return '';
        }
        $player = $this->playerLookup->getPlayerByID($pid);
        return is_string($player['name'] ?? null) ? $player['name'] : '';
    }

    /**
     * "First Last" → "F. Last". Empty or single-token input is returned unchanged.
     */
    private function shortName(string $full): string
    {
        $trim = trim($full);
        if ($trim === '') {
            return '';
        }
        $parts = preg_split('/\s+/', $trim);
        if ($parts === false || count($parts) < 2) {
            return $trim;
        }
        $first = $parts[0];
        $last = (string) end($parts);
        return strtoupper(substr($first, 0, 1)) . '. ' . $last;
    }

    private function formatMarginLabel(RecapGame $g): string
    {
        $sign = $g->margin >= 0 ? '+' : '−';
        $abs = abs($g->margin);
        $venue = $g->home ? 'vs' : '@';
        return $sign . $abs . ' ' . $venue . ' ' . $g->oppName;
    }
}
