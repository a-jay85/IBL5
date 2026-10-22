<?php

declare(strict_types=1);

namespace SimRecap;

use JsbParser\TrnFileParser;
use LastSimRecap\LastSimRecapRepository;
use League\LeagueContext;

/**
 * Precomputes the played-only roster, active injuries, in-window trades,
 * per-game facts, and standings for a sim.
 * Called prod-side via ibl5/scripts/simRecapContext.php over SSH.
 *
 * ADR-0093: this repository is SELECT-only and uses the read-only credential
 * injected via db/db.php; it composes no credential of its own.
 *
 * @phpstan-type PlayerLine array{pid:int,name:string,min:int,pts:int,reb:int,ast:int,stl:int,blk:int,fgm:int,fga:int,tpm:int,tpa:int}
 * @phpstan-type GameInjury array{pid:int,name:string,injuryDescription:string,injuryGamesMissed:int}
 * @phpstan-type GameRow array{id:int,season_year:int,box_id:int,game_date:string,visitor_teamid:int,visitor_score:int,home_teamid:int,home_score:int,visitor_name:string|null,home_name:string|null,game_of_that_day:int|null}
 * @phpstan-type BoxLine array{game_date:string,visitor_teamid:int,home_teamid:int,teamid:int,pid:int,name:string,min:int,pts:int,reb:int,ast:int,stl:int,blk:int,fgm:int,fga:int,tpm:int,tpa:int}
 * @phpstan-type GameFact array{key:string,season_year:int,game_date:string,box_id:int|null,game_of_that_day:int|null,visitor_teamid:int,home_teamid:int,visitor_name:string|null,home_name:string|null,visitor_score:int,home_score:int,visitor_top:list<PlayerLine>,home_top:list<PlayerLine>,injuries:list<GameInjury>}
 * @phpstan-type StandingRow array{teamid:int,name:string,wins:int,losses:int,conference:string|null,division:string|null,conf_gb:string|null,streak:string,last10:string}
 * @phpstan-type InjuryRow array{pid:int,name:string,pos:string,date:string,injuryDescription:string,injuryGamesMissed:int,daysRemaining:int,returnDate:string,isNew:bool,current_teamid:int}
 */
final class SimRecapContextRepository extends \Database\BaseMysqliRepository
{
    private const TOP_PERFORMERS_PER_SIDE = 3;

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
     * @return array{sim:int,start_date:string|null,end_date:string|null,roster:array<int,list<array{pid:int,name:string,pos:string,current_teamid:int}>>,active_injuries:list<array{pid:int,name:string,pos:string,date:string,injuryDescription:string,injuryGamesMissed:int,daysRemaining:int,returnDate:string,isNew:bool,current_teamid:int}>,sim_trades:list<array{pid:int,player_name:string|null,from_teamid:int,to_teamid:int,trade_group_id:int|null,trade_date:string}>,games:list<GameFact>,standings:list<StandingRow>}
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
                'games' => [],
                'standings' => [],
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

        $injuries = $this->enrichInjuriesWithCurrentTeam($rawInjuries, $playerMap);
        $trades   = $this->getSimTrades($start, $end);
        $boxLines = $this->getPlayedBoxLines($start, $end);
        $games    = $this->attachGameDetails(
            $this->getGameFacts($start, $end),
            $boxLines,
            $this->getWindowInjuries($start, $end)
        );

        $keepSet = [];
        foreach ($boxLines as $line) {
            $keepSet[$line['pid']] = true;
        }
        foreach ($injuries as $injury) {
            $keepSet[$injury['pid']] = true;
        }
        foreach ($trades as $trade) {
            $keepSet[$trade['pid']] = true;
        }

        return [
            'sim'             => $simId,
            'start_date'      => $start,
            'end_date'        => $end,
            'roster'          => $this->trimRoster($roster, array_keys($keepSet)),
            'active_injuries' => $injuries,
            'sim_trades'      => $trades,
            'games'           => $games,
            'standings'       => $this->getStandingsForTeams($teamIds),
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

    /**
     * Injuries dated inside [start, end], including ones already healed by the
     * window end. Feeds the per-game injury lists; active_injuries only holds
     * injuries still open at $end.
     *
     * @return list<array{pid:int,name:string,date:string,injuryDescription:string,injuryGamesMissed:int}>
     */
    private function getWindowInjuries(string $start, string $end): array
    {
        $dateExpr = LastSimRecapRepository::TRANSACTION_DATE_SQL;

        // $dateExpr is a fixed SQL fragment built from column refs and constants
        // (no user input); concatenate rather than interpolate.
        $sql = "SELECT t.pid,
                       p.name,
                       " . $dateExpr . " AS injury_date,
                       t.injury_description,
                       t.injury_games_missed
                FROM `ibl_jsb_transactions` t
                JOIN `ibl_plr` p ON p.pid = t.pid
                WHERE t.transaction_type = 1
                  AND t.injury_games_missed IS NOT NULL
                  AND " . $dateExpr . " BETWEEN ? AND ?
                ORDER BY injury_date ASC, t.id ASC";

        /** @var list<array{pid:int,name:string,injury_date:string,injury_description:string|null,injury_games_missed:int}> $rows */
        $rows = $this->fetchAll($sql, "ss", $start, $end);

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'pid'               => $row['pid'],
                'name'              => $row['name'],
                'date'              => $row['injury_date'],
                'injuryDescription' => $row['injury_description'] ?? '',
                'injuryGamesMissed' => $row['injury_games_missed'],
            ];
        }

        return $out;
    }

    /**
     * Played schedule rows in the window, with names and the box-score ordinal.
     * Unplayed (0-0) rows are dropped. game_of_that_day comes from
     * ibl_box_scores_teams (MIN over the two team rows) and is null when the
     * game has no team box row.
     *
     * @return list<GameRow>
     */
    private function getGameFacts(string $start, string $end): array
    {
        /** @var list<GameRow> $rows */
        $rows = $this->fetchAll(
            "SELECT s.id, s.season_year, s.box_id, s.game_date,
                    s.visitor_teamid, s.visitor_score, s.home_teamid, s.home_score,
                    sv.team_name AS visitor_name, sh.team_name AS home_name,
                    bst.game_of_that_day
             FROM `ibl_schedule` s
             LEFT JOIN `ibl_standings` sv ON sv.teamid = s.visitor_teamid
             LEFT JOIN `ibl_standings` sh ON sh.teamid = s.home_teamid
             LEFT JOIN (SELECT game_date, visitor_teamid, home_teamid,
                               MIN(game_of_that_day) AS game_of_that_day
                        FROM `ibl_box_scores_teams`
                        WHERE game_date BETWEEN ? AND ?
                        GROUP BY game_date, visitor_teamid, home_teamid) bst
                    ON bst.game_date = s.game_date
                   AND bst.visitor_teamid = s.visitor_teamid
                   AND bst.home_teamid = s.home_teamid
             WHERE s.game_date BETWEEN ? AND ?
               AND s.visitor_score + s.home_score > 0
             ORDER BY s.game_date ASC, bst.game_of_that_day ASC, s.id ASC",
            "ssss",
            $start,
            $end,
            $start,
            $end
        );

        return $rows;
    }

    /**
     * Box-score lines for players who actually played (game_min > 0) in the window.
     * Names come from ibl_plr because ibl_box_scores.name is an unreliable snapshot.
     *
     * @return list<BoxLine>
     */
    private function getPlayedBoxLines(string $start, string $end): array
    {
        /** @var list<BoxLine> $rows */
        $rows = $this->fetchAll(
            "SELECT b.game_date, b.visitor_teamid, b.home_teamid, b.teamid, b.pid,
                    p.name, b.game_min AS min, b.calc_points AS pts,
                    b.calc_rebounds AS reb, b.game_ast AS ast, b.game_stl AS stl,
                    b.game_blk AS blk, b.calc_fg_made AS fgm,
                    (b.game_2ga + b.game_3ga) AS fga, b.game_3gm AS tpm, b.game_3ga AS tpa
             FROM `ibl_box_scores` b
             JOIN `ibl_plr` p ON p.pid = b.pid
             WHERE b.game_date BETWEEN ? AND ?
               AND b.pid IS NOT NULL
               AND b.game_min > 0",
            "ss",
            $start,
            $end
        );

        return $rows;
    }

    /**
     * @param list<int> $teamIds
     * @return list<StandingRow>
     */
    private function getStandingsForTeams(array $teamIds): array
    {
        if ($teamIds === []) {
            return [];
        }

        /** @var list<array{teamid:int,name:string,wins:int,losses:int,conference:string|null,division:string|null,conf_gb:string|null,streak_type:string|null,streak:int|null,last_win:int|null,last_loss:int|null}> $rows */
        $rows = $this->fetchAllInList(
            "SELECT st.teamid, st.team_name AS name, st.wins, st.losses, st.conference,
                    st.division, st.conf_gb, pw.streak_type, pw.streak,
                    pw.last_win, pw.last_loss
             FROM `ibl_standings` st
             LEFT JOIN `ibl_power` pw ON pw.teamid = st.teamid
             WHERE st.teamid IN ({IN})
             ORDER BY st.conference ASC, st.wins DESC, st.losses ASC, st.teamid ASC",
            'i',
            $teamIds
        );

        $out = [];
        foreach ($rows as $row) {
            $hasPower = $row['streak_type'] !== null;
            $out[] = [
                'teamid'     => $row['teamid'],
                'name'       => $row['name'],
                'wins'       => $row['wins'],
                'losses'     => $row['losses'],
                'conference' => $row['conference'],
                'division'   => $row['division'],
                'conf_gb'    => $row['conf_gb'],
                'streak'     => $hasPower ? $row['streak_type'] . (string) $row['streak'] : '',
                'last10'     => $hasPower ? (string) $row['last_win'] . '-' . (string) $row['last_loss'] : '',
            ];
        }

        return $out;
    }

    /**
     * Attaches top performers (per side) and game-date injuries to each game and
     * assigns the script-owned key "g<N>" in final order.
     *
     * @param list<GameRow> $games
     * @param list<BoxLine> $boxLines
     * @param list<array{pid:int,name:string,date:string,injuryDescription:string,injuryGamesMissed:int}> $injuries
     * @return list<GameFact>
     */
    private function attachGameDetails(array $games, array $boxLines, array $injuries): array
    {
        /** @var array<string,list<BoxLine>> $byGame */
        $byGame = [];
        foreach ($boxLines as $line) {
            $byGame[$line['game_date'] . '|' . $line['visitor_teamid'] . '|' . $line['home_teamid']][] = $line;
        }

        $out = [];
        foreach ($games as $i => $game) {
            $lines = $byGame[$game['game_date'] . '|' . $game['visitor_teamid'] . '|' . $game['home_teamid']] ?? [];

            $visitorLines = [];
            $homeLines    = [];
            $playedPids   = [];
            foreach ($lines as $line) {
                $playedPids[$line['pid']] = true;
                $playerLine = [
                    'pid'  => $line['pid'],
                    'name' => $line['name'],
                    'min'  => $line['min'],
                    'pts'  => $line['pts'],
                    'reb'  => $line['reb'],
                    'ast'  => $line['ast'],
                    'stl'  => $line['stl'],
                    'blk'  => $line['blk'],
                    'fgm'  => $line['fgm'],
                    'fga'  => $line['fga'],
                    'tpm'  => $line['tpm'],
                    'tpa'  => $line['tpa'],
                ];
                if ($line['teamid'] === $game['visitor_teamid']) {
                    $visitorLines[] = $playerLine;
                } else {
                    $homeLines[] = $playerLine;
                }
            }

            $gameInjuries = [];
            foreach ($injuries as $injury) {
                if ($injury['date'] === $game['game_date'] && isset($playedPids[$injury['pid']])) {
                    $gameInjuries[] = [
                        'pid'               => $injury['pid'],
                        'name'              => $injury['name'],
                        'injuryDescription' => $injury['injuryDescription'],
                        'injuryGamesMissed' => $injury['injuryGamesMissed'],
                    ];
                }
            }

            $out[] = [
                'key'              => 'g' . ($i + 1),
                'season_year'      => $game['season_year'],
                'game_date'        => $game['game_date'],
                'box_id'           => $game['box_id'] === 0 ? null : $game['box_id'],
                'game_of_that_day' => $game['game_of_that_day'],
                'visitor_teamid'   => $game['visitor_teamid'],
                'home_teamid'      => $game['home_teamid'],
                'visitor_name'     => $game['visitor_name'],
                'home_name'        => $game['home_name'],
                'visitor_score'    => $game['visitor_score'],
                'home_score'       => $game['home_score'],
                'visitor_top'      => $this->topPerformers($visitorLines),
                'home_top'         => $this->topPerformers($homeLines),
                'injuries'         => $gameInjuries,
            ];
        }

        return $out;
    }

    /**
     * @param list<PlayerLine> $lines
     * @return list<PlayerLine>
     */
    private function topPerformers(array $lines): array
    {
        usort(
            $lines,
            static fn (array $a, array $b): int => [$b['pts'], $b['reb'], $a['pid']] <=> [$a['pts'], $a['reb'], $b['pid']]
        );

        return array_slice($lines, 0, self::TOP_PERFORMERS_PER_SIDE);
    }

    /**
     * Keeps only the pids in $keepPids on each team and drops teams left empty.
     *
     * @param array<int,list<array{pid:int,name:string,pos:string,current_teamid:int}>> $roster
     * @param list<int> $keepPids
     * @return array<int,list<array{pid:int,name:string,pos:string,current_teamid:int}>>
     */
    private function trimRoster(array $roster, array $keepPids): array
    {
        $keep = array_flip($keepPids);

        $out = [];
        foreach ($roster as $tid => $players) {
            $kept = array_values(array_filter(
                $players,
                static fn (array $p): bool => isset($keep[$p['pid']])
            ));
            if ($kept !== []) {
                $out[$tid] = $kept;
            }
        }

        return $out;
    }
}
