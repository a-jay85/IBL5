<?php

declare(strict_types=1);

namespace SimRecap;

use JsbParser\TrnFileParser;
use LastSimRecap\LastSimRecapRepository;
use League\LeagueContext;

/**
 * Precomputes current rosters, active injuries, and in-window trades for a sim.
 * Called prod-side via ibl5/scripts/simRecapContext.php over SSH.
 *
 * ADR-0093: this repository is SELECT-only and uses the read-only credential
 * injected via db/db.php; it composes no credential of its own.
 */
final class SimRecapContextRepository extends \BaseMysqliRepository
{
    private LastSimRecapRepository $lastSimRecap;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null, ?LastSimRecapRepository $lastSimRecap = null)
    {
        parent::__construct($db, $leagueContext);
        $this->lastSimRecap = $lastSimRecap ?? new LastSimRecapRepository($db, $leagueContext);
    }

    /**
     * Builds the full roster context for a sim. Fault-tolerant: an unknown or
     * window-less sim returns a well-formed empty context rather than throwing.
     *
     * @return array{sim:int,start_date:string|null,end_date:string|null,roster:array<int,list<array{pid:int,name:string,pos:string,current_teamid:int}>>,active_injuries:list<array{pid:int,name:string,pos:string,date:string,injuryDescription:string,injuryGamesMissed:int,daysRemaining:int,returnDate:string,isNew:bool,current_teamid:int}>,sim_trades:list<array{pid:int,player_name:string|null,from_teamid:int,to_teamid:int,trade_group_id:int|null,trade_date:string}>}
     */
    public function buildContext(int $simId): array
    {
        $window = $this->getSimWindow($simId);
        if ($window === null) {
            return [
                'sim' => $simId,
                'start_date' => null,
                'end_date' => null,
                'roster' => [],
                'active_injuries' => [],
                'sim_trades' => [],
            ];
        }

        $start = $window['start_date'];
        $end   = $window['end_date'];

        $teamIds = $this->getTeamIdsInWindow($start, $end);

        $allPids     = [];
        $rosterByTeam = [];
        foreach ($teamIds as $tid) {
            $pids = $this->lastSimRecap->getTeamRosterPids($tid);
            $allPids       = array_merge($allPids, $pids);
            $rosterByTeam[$tid] = $pids;
        }

        $playerMap = [];
        foreach ($this->getPlayerLines($allPids) as $player) {
            $playerMap[$player['pid']] = $player;
        }

        /** @var array<int,list<array{pid:int,name:string,pos:string,current_teamid:int}>> $roster */
        $roster = [];
        foreach ($rosterByTeam as $tid => $pids) {
            $lines = [];
            foreach ($pids as $pid) {
                if (isset($playerMap[$pid])) {
                    $lines[] = $playerMap[$pid];
                }
            }
            $roster[$tid] = $lines;
        }

        $rawInjuries = $this->lastSimRecap->getActiveInjuriesForPlayers($allPids, $end);

        return [
            'sim'             => $simId,
            'start_date'      => $start,
            'end_date'        => $end,
            'roster'          => $roster,
            'active_injuries' => $this->enrichInjuriesWithCurrentTeam($rawInjuries, $playerMap),
            'sim_trades'      => $this->getSimTrades($start, $end),
        ];
    }

    /**
     * @return array{start_date:string,end_date:string}|null
     */
    private function getSimWindow(int $simId): ?array
    {
        /** @var array{start_date:string|null,end_date:string|null}|null $row */
        $row = $this->fetchOne(
            "SELECT start_date, end_date FROM `ibl_sim_dates` WHERE sim = ?",
            "i",
            $simId
        );

        if ($row === null) {
            return null;
        }

        $start = $row['start_date'];
        $end   = $row['end_date'];

        // Treat '' identically to null (insertRow() cannot bind NULL, so tests seed '').
        if ($start === null || $start === '' || $end === null || $end === '') {
            return null;
        }

        return ['start_date' => $start, 'end_date' => $end];
    }

    /**
     * @return list<int>
     */
    private function getTeamIdsInWindow(string $start, string $end): array
    {
        /** @var list<array{tid:int}> $rows */
        $rows = $this->fetchAll(
            "SELECT DISTINCT visitor_teamid AS tid FROM `ibl_schedule`
             WHERE game_date BETWEEN ? AND ?
             UNION
             SELECT DISTINCT home_teamid FROM `ibl_schedule`
             WHERE game_date BETWEEN ? AND ?",
            "ssss",
            $start,
            $end,
            $start,
            $end
        );

        return array_map(static fn (array $r): int => $r['tid'], $rows);
    }

    /**
     * @param list<int> $pids
     * @return list<array{pid:int,name:string,pos:string,current_teamid:int}>
     */
    private function getPlayerLines(array $pids): array
    {
        /** @var list<array{pid:int,name:string,pos:string,current_teamid:int}> $rows */
        $rows = $this->fetchAllInList(
            "SELECT pid, name, pos, teamid AS current_teamid FROM `ibl_plr` WHERE pid IN ({IN})",
            'i',
            $pids
        );

        return $rows;
    }

    /**
     * Merges current_teamid (from ibl_plr.teamid) into each injury row.
     * The from_teamid on a TYPE_INJURY row is a frozen historical marker — it
     * reflects which team the player was on at injury time, not now. We enrich
     * with ibl_plr.teamid (the live value) so callers never mis-attribute a
     * traded-and-injured player to his old team.
     *
     * @param list<array{pid:int,name:string,pos:string,date:string,injuryDescription:string,injuryGamesMissed:int,daysRemaining:int,returnDate:string,isNew:bool}> $injuries
     * @param array<int,array{pid:int,name:string,pos:string,current_teamid:int}> $playerMap
     * @return list<array{pid:int,name:string,pos:string,date:string,injuryDescription:string,injuryGamesMissed:int,daysRemaining:int,returnDate:string,isNew:bool,current_teamid:int}>
     */
    private function enrichInjuriesWithCurrentTeam(array $injuries, array $playerMap): array
    {
        $out = [];
        foreach ($injuries as $injury) {
            $pid = $injury['pid'];
            $injury['current_teamid'] = isset($playerMap[$pid]) ? $playerMap[$pid]['current_teamid'] : 0;
            $out[] = $injury;
        }
        return $out;
    }

    /**
     * @return list<array{pid:int,player_name:string|null,from_teamid:int,to_teamid:int,trade_group_id:int|null,trade_date:string}>
     */
    private function getSimTrades(string $start, string $end): array
    {
        $dateExpr = LastSimRecapRepository::TRANSACTION_DATE_SQL;

        // $dateExpr is a fixed SQL fragment built from column refs and constants
        // (no user input); concatenate rather than interpolate to satisfy the
        // sqlStringInterpolation rule.
        $sql = "SELECT t.pid,
                       t.player_name,
                       t.from_teamid,
                       t.to_teamid,
                       t.trade_group_id,
                       " . $dateExpr . " AS trade_date
                FROM `ibl_jsb_transactions` t
                WHERE t.transaction_type = ?
                  AND t.is_draft_pick = 0
                  AND " . $dateExpr . " BETWEEN ? AND ?
                ORDER BY trade_date ASC, t.trade_group_id ASC, t.id ASC";

        /** @var list<array{pid:int,player_name:string|null,from_teamid:int,to_teamid:int,trade_group_id:int|null,trade_date:string}> $rows */
        $rows = $this->fetchAll($sql, "iss", TrnFileParser::TYPE_TRADE, $start, $end);

        return $rows;
    }
}
