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

/**
 * @phpstan-import-type TeamBoxscoreLines from LastSimRecapRepositoryInterface
 * @phpstan-import-type StarterMap from LastSimRecapRepositoryInterface
 */
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

        $teamInfo = $this->repo->getTeamInfo($tid);
        if ($teamInfo === null) {
            return null;
        }

        $games = $this->repo->getGamesForTeamInWindow($tid, $window['startDate'], $window['endDate']);

        if ($games === []) {
            return $this->makeSlate($tid, $teamInfo, $window, 0, 0, 0, '', '', 0, 0, []);
        }

        $recapGames = [];
        foreach ($games as $g) {
            $recapGames[] = $this->buildGame($tid, $g);
        }

        $summary = $this->summarize($recapGames);

        $bestLabel = $this->formatMarginLabel($recapGames[$summary['bestIdx']]);
        $worstLabel = $this->formatMarginLabel($recapGames[$summary['worstIdx']]);

        // End-of-window record reflects every game played up to the window's
        // last date (inclusive). Slate header W/L counts only games in the
        // window, but the badge under the team name shows season W-L.
        $teamRecord = $this->repo->getTeamRecordAsOf($tid, $window['endDate']);

        return $this->makeSlate(
            $tid,
            $teamInfo,
            $window,
            $summary['wins'],
            $summary['losses'],
            $summary['netMargin'],
            $bestLabel,
            $worstLabel,
            $teamRecord['wins'],
            $teamRecord['losses'],
            $recapGames,
        );
    }

    /**
     * Fold the slate's games into W/L totals, net margin, and the indexes of
     * the best and worst margins. Ties keep the first game in list order.
     * Game 0 seeds both extremes, so the caller must pass a non-empty list.
     *
     * @param non-empty-list<RecapGame> $recapGames
     * @return array{wins: int, losses: int, netMargin: int, bestIdx: int<0, max>, worstIdx: int<0, max>}
     */
    private function summarize(array $recapGames): array
    {
        $wins = 0;
        $losses = 0;
        $netMargin = 0;
        $bestMargin = $recapGames[0]->margin;
        $bestIdx = 0;
        $worstMargin = $recapGames[0]->margin;
        $worstIdx = 0;

        foreach ($recapGames as $idx => $rg) {
            if ($rg->won) {
                $wins++;
            } else {
                $losses++;
            }
            $netMargin += $rg->margin;

            if ($rg->margin > $bestMargin) {
                $bestMargin = $rg->margin;
                $bestIdx = $idx;
            }
            if ($rg->margin < $worstMargin) {
                $worstMargin = $rg->margin;
                $worstIdx = $idx;
            }
        }

        return [
            'wins' => $wins,
            'losses' => $losses,
            'netMargin' => $netMargin,
            'bestIdx' => $bestIdx,
            'worstIdx' => $worstIdx,
        ];
    }

    /**
     * @param array{tid: int, city: string, name: string} $teamInfo
     * @param array{sim: int, startDate: string, endDate: string} $window
     * @param list<RecapGame> $games
     */
    private function makeSlate(
        int $tid,
        array $teamInfo,
        array $window,
        int $wins,
        int $losses,
        int $netMargin,
        string $bestLabel,
        string $worstLabel,
        int $teamWins,
        int $teamLosses,
        array $games,
    ): RecapSlate {
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
            teamWins: $teamWins,
            teamLosses: $teamLosses,
            games: $games,
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

        $quarters = $this->buildQuarterMargins(
            $this->repo->getTeamBoxscoreLines($g['visitor'], $g['home'], $g['date']),
            $home,
        );

        // Active injuries as of game date for both teams.
        $yourRoster = $this->repo->getTeamRosterPids($tid);
        $oppRoster = $this->repo->getTeamRosterPids($oppTid);

        $yourInjuries = $this->mapInjuries(
            $yourRoster === [] ? [] : $this->repo->getActiveInjuriesForPlayers($yourRoster, $g['date'])
        );
        $oppInjuries = $this->mapInjuries(
            $oppRoster === [] ? [] : $this->repo->getActiveInjuriesForPlayers($oppRoster, $g['date'])
        );

        $yourStarters = $this->resolveStarterPids($tid, $g);
        $oppStarters = $this->resolveStarterPids($oppTid, $g);
        $starters = $this->buildStarters($yourStarters, $oppStarters, $yourInjuries, $g['schedId']);

        return new RecapGame(
            schedId: $g['schedId'],
            boxId: $g['boxId'],
            gameOfThatDay: $quarters['gameOfThatDay'],
            date: $g['date'],
            home: $home,
            won: $won,
            yourScore: $yourScore,
            oppScore: $oppScore,
            margin: $margin,
            ot: $quarters['ot'],
            margins: $quarters['margins'],
            qLabels: $quarters['qLabels'],
            oppTid: $oppTid,
            oppCity: $oppCity,
            oppName: $oppName,
            oppPreWins: $quarters['oppPreWins'],
            oppPreLosses: $quarters['oppPreLosses'],
            yourInjuries: $yourInjuries,
            oppInjuries: $oppInjuries,
            starters: $starters,
        );
    }

    /**
     * Quarter margins from your side, plus the opponent's pre-game record
     * and the game's league-wide ordinal for that day. Missing box-score
     * lines yield empty margins and zeroed fields.
     *
     * @param TeamBoxscoreLines|null $lines
     * @return array{margins: list<int>, qLabels: list<string>, ot: bool, oppPreWins: int, oppPreLosses: int, gameOfThatDay: int}
     */
    private function buildQuarterMargins(?array $lines, bool $home): array
    {
        if ($lines === null) {
            return ['margins' => [], 'qLabels' => [], 'ot' => false, 'oppPreWins' => 0, 'oppPreLosses' => 0, 'gameOfThatDay' => 0];
        }

        $ot = $lines['visOT'] > 0 || $lines['homeOT'] > 0;
        $margins = [];
        $qLabels = [];
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

        return [
            'margins' => $margins,
            'qLabels' => $qLabels,
            'ot' => $ot,
            'oppPreWins' => $home ? $lines['visitorPreWins'] : $lines['homePreWins'],
            'oppPreLosses' => $home ? $lines['visitorPreLosses'] : $lines['homePreLosses'],
            'gameOfThatDay' => $lines['gameOfThatDay'],
        ];
    }

    /**
     * Position-battle starters: last-sim depth columns (primary), saved
     * snapshot, then box-score minutes as final fallback. Each source
     * returns a whole map or null. The first non-null map wins and later
     * sources are not queried.
     *
     * @param array{schedId:int,boxId:int,date:string,visitor:int,vScore:int,home:int,hScore:int,year:int} $g
     * @return StarterMap
     */
    private function resolveStarterPids(int $tid, array $g): array
    {
        return $this->repo->getStarterPidsFromLastSim($tid)
            ?? $this->repo->getStarterPidsFromSnapshot($tid, $g['date'])
            ?? $this->repo->getStarterPidsFromBoxScores($g['schedId'], $tid);
    }

    /**
     * @param array<string, int> $yourStarters
     * @param array<string, int> $oppStarters
     * @param list<RecapInjury> $yourInjuries
     * @return list<RecapStarter>
     */
    private function buildStarters(array $yourStarters, array $oppStarters, array $yourInjuries, int $schedId): array
    {
        // Pids with a NEW injury today, for the hurt flag. Your team only.
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
            $yourLine = $yourPid !== 0 ? $this->repo->getPlayerLineForGame($yourPid, $schedId) : null;
            $oppLine = $oppPid !== 0 ? $this->repo->getPlayerLineForGame($oppPid, $schedId) : null;

            $yourLookup = $this->lookupPlayerName($yourPid);
            $oppLookup = $this->lookupPlayerName($oppPid);

            $starters[] = new RecapStarter(
                pos: $pos,
                youPid: $yourPid,
                youName: $this->shortName($yourLookup !== '' ? $yourLookup : ($yourLine['name'] ?? '')),
                youPts: $yourLine['pts'] ?? 0,
                youReb: $yourLine['reb'] ?? 0,
                youAst: $yourLine['ast'] ?? 0,
                youStl: $yourLine['stl'] ?? 0,
                youBlk: $yourLine['blk'] ?? 0,
                youHurt: $hurtPids[$yourPid] ?? false,
                oppPid: $oppPid,
                oppName: $this->shortName($oppLookup !== '' ? $oppLookup : ($oppLine['name'] ?? '')),
                oppPts: $oppLine['pts'] ?? 0,
                oppReb: $oppLine['reb'] ?? 0,
                oppAst: $oppLine['ast'] ?? 0,
                oppStl: $oppLine['stl'] ?? 0,
                oppBlk: $oppLine['blk'] ?? 0,
            );
        }

        return $starters;
    }

    /**
     * @param list<array{pid:int,name:string,pos:string,date:string,injuryDescription:string,injuryGamesMissed:int,daysRemaining:int,returnDate:string,isNew:bool}> $rows
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
                returnDate: $r['returnDate'],
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
