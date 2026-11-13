<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\Engine\Contracts\SlotRankReaderInterface;
use TradeSuggestions\Profile\LeagueProfiles;
use TradeSuggestions\Profile\SeasonLineup;
use TradeSuggestions\Profile\TeamProfile;
use TradeSuggestions\SlotMapper;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Valuation\CapRoomAsset;

/**
 * Rebuilds one or two teams at one offset after a roster change. It is pure
 * apart from the request-scoped pair memo it receives.
 *
 * The steps: new rosters, new lineups for the changed teams only, the
 * incremental slot re-rank (`SlotRankState::withLineups`), a league profile
 * re-rank over the two new value sets, position strength, and payroll with
 * its league rank.
 */
final class TeamStateRebuilder
{
    public static function before(LeagueSnapshot $s, int $tid, int $offset): TeamState
    {
        $lineup = $s->lineup($offset, $tid);
        $state = $s->slotRanks[$offset];

        return self::teamState(
            $tid,
            $offset,
            $lineup,
            $state,
            $s->profiles[$offset][$tid],
            $s->positionStrength[$offset][$tid],
            $s->payrollByOffset[$offset][$tid],
            $s->payrollRanks[$offset][$tid],
        );
    }

    /**
     * The before-state of one team with some of its players removed, for the
     * re-sign scenarios.
     *
     * @param list<int> $absentPids
     */
    public static function beforeWithout(LeagueSnapshot $s, int $tid, int $offset, PairRecordMemo $memo, array $absentPids): TeamState
    {
        $roster = array_values(array_diff(array_keys($s->lineup($offset, $tid)->players), $absentPids));

        return self::rebuild($s, $offset, $memo, [$tid => $roster])[$tid];
    }

    /**
     * @param list<int> $absentPids players treated as gone (re-sign scenarios)
     * @return array{user: TeamState, partner: TeamState}
     */
    public static function after(LeagueSnapshot $s, TradeCandidate $c, int $offset, PairRecordMemo $memo, array $absentPids = []): array
    {
        $rosters = [
            $c->userTid => self::rosterAfter($s, $offset, $c->userTid, $c->userPids, $c->partnerPids, $absentPids),
            $c->partnerTid => self::rosterAfter($s, $offset, $c->partnerTid, $c->partnerPids, $c->userPids, $absentPids),
        ];
        $states = self::rebuild($s, $offset, $memo, $rosters);

        return ['user' => $states[$c->userTid], 'partner' => $states[$c->partnerTid]];
    }

    /**
     * @param list<int> $sent
     * @param list<int> $received
     * @param list<int> $absent
     * @return list<int>
     */
    private static function rosterAfter(LeagueSnapshot $s, int $offset, int $tid, array $sent, array $received, array $absent): array
    {
        $roster = array_diff(array_keys($s->lineup($offset, $tid)->players), $sent, $absent);
        foreach ($received as $pid) {
            if (!in_array($pid, $absent, true) && $s->players[$pid]->onRosterAt($offset)) {
                $roster[] = $pid;
            }
        }

        return array_values($roster);
    }

    /**
     * @param array<int, list<int>> $rosters tid => pids, one or two teams
     * @return array<int, TeamState>
     */
    private static function rebuild(LeagueSnapshot $s, int $offset, PairRecordMemo $memo, array $rosters): array
    {
        $ratingsByPid = [];
        $per36ByPid = [];
        foreach ($s->projections as $pid => $seasons) {
            $season = $seasons[$offset] ?? null;
            if ($season !== null && $season->onRoster) {
                $ratingsByPid[$pid] = $season->ratings;
                $per36ByPid[$pid] = $season->per36;
            }
        }

        $lineups = [];
        $values = [];
        $payrolls = $s->payrollByOffset[$offset];
        foreach ($rosters as $tid => $pids) {
            $projected = [];
            $salary = 0;
            foreach ($pids as $pid) {
                $season = $s->projections[$pid][$offset];
                $projected[$pid] = ['player' => $s->players[$pid], 'season' => $season];
                $salary += $season->onRoster ? $season->salary : 0;
            }
            $lineups[$tid] = SeasonLineup::build($tid, $offset, $projected);
            $values[$tid] = TeamProfile::valuesFor($lineups[$tid], $ratingsByPid, $per36ByPid);
            $payrolls[$tid] = $salary + ($s->cashByTeamAndOffset[$tid][$offset] ?? 0);
        }

        $state = $s->slotRanks[$offset]->withLineups($lineups, $ratingsByPid, $memo);
        $tids = array_keys($lineups);
        $tidA = $tids[0];
        $tidB = $tids[1] ?? $tidA;
        $profiles = LeagueProfiles::reRank($s->profiles[$offset], $tidA, $values[$tidA], $tidB, $values[$tidB]);
        $payrollRanks = CapRoomAsset::payrollRanks($payrolls);

        $out = [];
        foreach ($lineups as $tid => $lineup) {
            $out[$tid] = self::teamState(
                $tid,
                $offset,
                $lineup,
                $state,
                $profiles[$tid],
                AfterPositionStrength::build($tid, $lineup, $state),
                $payrolls[$tid],
                $payrollRanks[$tid],
            );
        }

        return $out;
    }

    /**
     * @param array<string, \TradeSuggestions\Profile\SlotStrength> $strength
     */
    private static function teamState(
        int $tid,
        int $offset,
        SeasonLineup $lineup,
        SlotRankReaderInterface $ranks,
        TeamProfile $profile,
        array $strength,
        int $payroll,
        int $payrollRank,
    ): TeamState {
        $starter = [];
        $bench = [];
        foreach (SlotMapper::SLOTS as $slot) {
            $starter[$slot] = $ranks->starterRank($slot, $tid);
            $bench[$slot] = $ranks->bestBenchRank($slot, $tid);
        }

        return new TeamState($tid, $offset, $lineup, $starter, $bench, $profile, $strength, $payroll, $payrollRank, count($lineup->players));
    }
}
