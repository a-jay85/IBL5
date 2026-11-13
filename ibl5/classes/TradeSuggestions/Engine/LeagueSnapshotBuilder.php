<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use League\League;
use TradeSuggestions\Projection\Horizon;
use TradeSuggestions\Projection\PlayerProjection;

/**
 * Pure assembly of a `LeagueSnapshot` from loaded inputs. It never calls a
 * repository. The one pairwise pass runs here, through `SlotNetTallies::build`;
 * every other derived object comes from `SnapshotCodec::assemble`.
 */
final class LeagueSnapshotBuilder
{
    public static function build(LeagueSnapshotInputs $in): LeagueSnapshot
    {
        $teamIds = [];
        $players = [];
        $teamOfPid = [];
        $projections = [];
        foreach ($in->playersByTid as $tid => $roster) {
            if ($tid < 1 || $tid > League::MAX_REAL_TEAMID) {
                continue;
            }
            $teamIds[] = $tid;
            foreach ($roster as $p) {
                $players[$p->pid] = $p;
                $teamOfPid[$p->pid] = $tid;
                $projections[$p->pid] = PlayerProjection::project($p, $in->scale);
            }
        }
        sort($teamIds);

        $lineups = SnapshotCodec::lineupsFor($teamIds, $players, $teamOfPid, $projections);
        $memo = new PairRecordMemo();
        $tallies = [];
        foreach (Horizon::OFFSETS as $offset) {
            $tallies[$offset] = SlotNetTallies::build(
                $offset,
                $lineups[$offset],
                SnapshotCodec::ratingsAt($projections, $offset),
                $memo,
            );
        }

        $picks = [];
        foreach ($in->pickRows as $rows) {
            foreach ($rows as $row) {
                $yearsAway = $row['year'] - $in->seasonYear;
                if ($yearsAway < 0) {
                    continue;
                }
                $picks[$row['pickid']] = new PickAsset(
                    $row['pickid'],
                    $row['owner_teamid'],
                    $row['teampick_teamid'],
                    $row['year'],
                    $row['round'],
                    $yearsAway,
                );
            }
        }

        $ranksAtZero = SlotRankState::fromTallies($tallies[0], $lineups[0]);
        $resign = [];
        $walk = [];
        foreach ($players as $pid => $p) {
            if ($p->salaryAt(0) <= 0) {
                continue;
            }
            $odds = ResignOdds::forTeam($p, $teamOfPid[$pid], $lineups[0], $ranksAtZero, $in->teamFactors);
            $resign[$pid] = $odds['likelihood'];
            $walk[$pid] = $odds['walks'];
        }

        return SnapshotCodec::assemble(
            seasonYear: $in->seasonYear,
            advancesContractYears: $in->advancesContractYears,
            regularSeason: $in->regularSeason,
            teamIds: $teamIds,
            players: $players,
            teamOfPid: $teamOfPid,
            projections: $projections,
            tallies: $tallies,
            standings: $in->standings,
            streaks: $in->streaks,
            cashByTeamAndOffset: $in->cashByTeamAndOffset,
            picks: $picks,
            draftOrder: $in->draftOrder,
            modes: $in->modes,
            teamFactors: $in->teamFactors,
            resignLikelihood: $resign,
            likelyToWalk: $walk,
            teamNames: $in->teamNames,
        );
    }
}
