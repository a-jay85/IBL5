<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\ProductionLine;
use TradeSuggestions\ProductionSource;
use TradeSuggestions\Profile\LeagueProfiles;
use TradeSuggestions\Profile\SeasonLineup;
use TradeSuggestions\Profile\TeamProfile;
use TradeSuggestions\Profile\TeamSituation;
use TradeSuggestions\Projection\Horizon;
use TradeSuggestions\Projection\InjuryStatus;
use TradeSuggestions\Projection\ProjectedSeason;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\Projection\RatingSet;
use TradeSuggestions\TeamMode;
use TradeSuggestions\Valuation\CapRoomAsset;
use TradeSuggestions\Valuation\DraftSlotIndex;
use TradeSuggestions\Valuation\FairSalaryTable;
use TradeSuggestions\Valuation\TeamFactors;

/**
 * Array round trip for `LeagueSnapshot`, in the plain scalar arrays
 * `DatabaseCache::set` accepts. No reflection (D4): every field is named.
 *
 * The encoding stores inputs and the per-slot net tallies. Decode rebuilds the
 * derived objects through `assemble` with sorting only, so the warm path makes
 * no `DominanceComparator::record` call.
 *
 * @phpstan-import-type ProjectedDraftOrderResult from \ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface
 * @phpstan-import-type StandingsRow from LeagueSnapshot
 * @phpstan-import-type StreakRow from LeagueSnapshot
 */
final class SnapshotCodec
{
    public const int VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public static function encode(LeagueSnapshot $s): array
    {
        $players = [];
        foreach ($s->players as $pid => $p) {
            $players[$pid] = self::encodePlayer($p);
        }
        $projections = [];
        foreach ($s->projections as $pid => $seasons) {
            foreach ($seasons as $offset => $season) {
                $projections[$pid][$offset] = [
                    'offset' => $season->offset,
                    'age' => $season->age,
                    'salary' => $season->salary,
                    'onRoster' => $season->onRoster,
                    'ratings' => $season->ratings->toArray(),
                    'per36' => $season->per36,
                ];
            }
        }
        $tallies = [];
        foreach ($s->slotRanks as $offset => $state) {
            $tallies[$offset] = $state->tallies()->toArray();
        }
        $picks = [];
        foreach ($s->picks as $pickId => $pick) {
            $picks[$pickId] = [
                'pickId' => $pick->pickId,
                'ownerTid' => $pick->ownerTid,
                'originalTid' => $pick->originalTid,
                'year' => $pick->year,
                'round' => $pick->round,
                'yearsAway' => $pick->yearsAway,
            ];
        }
        $modes = [];
        foreach ($s->modes as $tid => $mode) {
            $modes[$tid] = $mode->name;
        }
        $factors = [];
        foreach ($s->teamFactors as $tid => $f) {
            $factors[$tid] = [
                'tid' => $f->tid,
                'wins' => $f->wins,
                'losses' => $f->losses,
                'tradWins' => $f->tradWins,
                'tradLosses' => $f->tradLosses,
            ];
        }

        return [
            'v' => self::VERSION,
            'seasonYear' => $s->seasonYear,
            'advancesContractYears' => $s->advancesContractYears,
            'regularSeason' => $s->regularSeason,
            'teamIds' => $s->teamIds,
            'players' => $players,
            'teamOfPid' => $s->teamOfPid,
            'projections' => $projections,
            'tallies' => $tallies,
            'standings' => $s->standings,
            'streaks' => $s->streaks,
            'cash' => $s->cashByTeamAndOffset,
            'picks' => $picks,
            'draftOrder' => $s->draftOrder,
            'modes' => $modes,
            'teamFactors' => $factors,
            'resignLikelihood' => $s->resignLikelihood,
            'likelyToWalk' => $s->likelyToWalk,
            'teamNames' => $s->teamNames,
        ];
    }

    /**
     * @param array<mixed> $data
     * @throws \UnexpectedValueException on a missing or different version, or a malformed payload
     */
    public static function decode(array $data): LeagueSnapshot
    {
        if (($data['v'] ?? null) !== self::VERSION) {
            throw new \UnexpectedValueException('Snapshot payload version mismatch');
        }

        $players = [];
        foreach (self::arr($data, 'players') as $pid => $row) {
            $players[self::key($pid)] = self::decodePlayer(self::asArray($row));
        }
        $projections = [];
        foreach (self::arr($data, 'projections') as $pid => $seasons) {
            foreach (self::asArray($seasons) as $offset => $raw) {
                $row = self::asArray($raw);
                $per36 = [];
                foreach (self::arr($row, 'per36') as $k => $v) {
                    $per36[(string) $k] = self::num($v);
                }
                $projections[self::key($pid)][self::key($offset)] = new ProjectedSeason(
                    offset: self::int($row, 'offset'),
                    age: self::int($row, 'age'),
                    salary: self::int($row, 'salary'),
                    onRoster: self::bool($row, 'onRoster'),
                    ratings: RatingSet::fromRow(self::stringKeyed(self::arr($row, 'ratings'))),
                    per36: $per36,
                );
            }
        }
        $tallies = [];
        foreach (self::arr($data, 'tallies') as $offset => $raw) {
            $tallies[self::key($offset)] = SlotNetTallies::fromArray(self::asArray($raw));
        }
        $picks = [];
        foreach (self::arr($data, 'picks') as $pickId => $raw) {
            $row = self::asArray($raw);
            $picks[self::key($pickId)] = new PickAsset(
                self::int($row, 'pickId'),
                self::int($row, 'ownerTid'),
                self::int($row, 'originalTid'),
                self::int($row, 'year'),
                self::int($row, 'round'),
                self::int($row, 'yearsAway'),
            );
        }
        $modes = [];
        foreach (self::arr($data, 'modes') as $tid => $name) {
            $modes[self::key($tid)] = self::mode($name);
        }
        $factors = [];
        foreach (self::arr($data, 'teamFactors') as $tid => $raw) {
            $row = self::asArray($raw);
            $factors[self::key($tid)] = new TeamFactors(
                self::int($row, 'tid'),
                self::int($row, 'wins'),
                self::int($row, 'losses'),
                self::int($row, 'tradWins'),
                self::int($row, 'tradLosses'),
            );
        }

        /** @var array<int, StandingsRow> $standings */
        $standings = self::standingsFrom(self::arr($data, 'standings'));
        /** @var array<int, StreakRow> $streaks */
        $streaks = self::intGrid(self::arr($data, 'streaks'));
        /** @var ProjectedDraftOrderResult $draftOrder */
        $draftOrder = self::arr($data, 'draftOrder');

        $resign = [];
        foreach (self::arr($data, 'resignLikelihood') as $pid => $v) {
            $resign[self::key($pid)] = self::num($v);
        }
        $walk = [];
        foreach (self::arr($data, 'likelyToWalk') as $pid => $v) {
            $walk[self::key($pid)] = $v === true;
        }
        $names = [];
        foreach (self::arr($data, 'teamNames') as $tid => $v) {
            $names[self::key($tid)] = is_string($v) ? $v : '';
        }
        $teamOfPid = [];
        foreach (self::arr($data, 'teamOfPid') as $pid => $tid) {
            $teamOfPid[self::key($pid)] = self::key($tid);
        }
        $teamIds = array_map(self::key(...), array_values(self::arr($data, 'teamIds')));

        return self::assemble(
            seasonYear: self::int($data, 'seasonYear'),
            advancesContractYears: self::bool($data, 'advancesContractYears'),
            regularSeason: self::bool($data, 'regularSeason'),
            teamIds: $teamIds,
            players: $players,
            teamOfPid: $teamOfPid,
            projections: $projections,
            tallies: $tallies,
            standings: $standings,
            streaks: $streaks,
            cashByTeamAndOffset: self::cashGrid(self::arr($data, 'cash')),
            picks: $picks,
            draftOrder: $draftOrder,
            modes: $modes,
            teamFactors: $factors,
            resignLikelihood: $resign,
            likelyToWalk: $walk,
            teamNames: $names,
        );
    }

    /**
     * Rebuild every derived object from stored inputs and tallies. Shared by the
     * cold builder and the warm decode, so the derivation is written once. It
     * sorts the tallies and never runs a pairwise pass.
     *
     * @param list<int> $teamIds
     * @param array<int, ProjectionPlayer> $players
     * @param array<int, int> $teamOfPid
     * @param array<int, array<int, ProjectedSeason>> $projections
     * @param array<int, SlotNetTallies> $tallies offset => tallies
     * @param array<int, StandingsRow> $standings
     * @param array<int, StreakRow> $streaks
     * @param array<int, array<int, int>> $cashByTeamAndOffset
     * @param array<int, PickAsset> $picks
     * @param ProjectedDraftOrderResult $draftOrder
     * @param array<int, TeamMode> $modes
     * @param array<int, TeamFactors> $teamFactors
     * @param array<int, float> $resignLikelihood
     * @param array<int, bool> $likelyToWalk
     * @param array<int, string> $teamNames
     */
    public static function assemble(
        int $seasonYear,
        bool $advancesContractYears,
        bool $regularSeason,
        array $teamIds,
        array $players,
        array $teamOfPid,
        array $projections,
        array $tallies,
        array $standings,
        array $streaks,
        array $cashByTeamAndOffset,
        array $picks,
        array $draftOrder,
        array $modes,
        array $teamFactors,
        array $resignLikelihood,
        array $likelyToWalk,
        array $teamNames,
    ): LeagueSnapshot {
        $lineups = self::lineupsFor($teamIds, $players, $teamOfPid, $projections);
        $slotRanks = [];
        $profiles = [];
        $positionStrength = [];
        $fairSalary = [];
        $payrollByOffset = [];
        $payrollRanks = [];
        foreach (Horizon::OFFSETS as $offset) {
            $ratingsByPid = [];
            $per36ByPid = [];
            $salaryByPid = [];
            foreach ($projections as $pid => $seasons) {
                $season = $seasons[$offset] ?? null;
                if ($season === null || !$season->onRoster) {
                    continue;
                }
                $ratingsByPid[$pid] = $season->ratings;
                $per36ByPid[$pid] = $season->per36;
                $salaryByPid[$pid] = $season->salary;
            }
            $state = SlotRankState::fromTallies(
                $tallies[$offset] ?? throw new \UnexpectedValueException("No tallies at offset {$offset}"),
                $lineups[$offset],
            );
            $slotRanks[$offset] = $state;

            $valuesByTid = [];
            foreach ($lineups[$offset] as $tid => $lineup) {
                $valuesByTid[$tid] = TeamProfile::valuesFor($lineup, $ratingsByPid, $per36ByPid);
                $positionStrength[$offset][$tid] = AfterPositionStrength::build($tid, $lineup, $state);
            }
            $profiles[$offset] = LeagueProfiles::build($valuesByTid, $offset);
            $fairSalary[$offset] = FairSalaryTable::build($offset, $state->poolRanks(), $salaryByPid);

            $payroll = [];
            foreach ($lineups[$offset] as $tid => $lineup) {
                $salaries = [];
                foreach (array_keys($lineup->players) as $pid) {
                    $salaries[] = $salaryByPid[$pid] ?? 0;
                }
                $payroll[$tid] = CapRoomAsset::payroll($salaries, $cashByTeamAndOffset[$tid][$offset] ?? 0);
            }
            $payrollByOffset[$offset] = $payroll;
            $payrollRanks[$offset] = CapRoomAsset::payrollRanks($payroll);
        }

        $situations = [];
        foreach ($teamIds as $tid) {
            if (isset($standings[$tid])) {
                $situations[$tid] = TeamSituation::build($tid, $standings, $streaks, $lineups[0][$tid], $regularSeason);
            }
        }
        $injury = [];
        foreach ($players as $pid => $p) {
            $situation = $situations[$teamOfPid[$pid] ?? 0] ?? null;
            $injury[$pid] = $situation !== null
                ? $situation->injuryStatusFor($p)
                : InjuryStatus::fromDays($p->injuredDays, 0, $regularSeason);
        }

        return new LeagueSnapshot(
            seasonYear: $seasonYear,
            advancesContractYears: $advancesContractYears,
            regularSeason: $regularSeason,
            teamIds: $teamIds,
            players: $players,
            teamOfPid: $teamOfPid,
            projections: $projections,
            lineups: $lineups,
            slotRanks: $slotRanks,
            profiles: $profiles,
            positionStrength: $positionStrength,
            situations: $situations,
            standings: $standings,
            streaks: $streaks,
            fairSalary: $fairSalary,
            cashByTeamAndOffset: $cashByTeamAndOffset,
            payrollByOffset: $payrollByOffset,
            payrollRanks: $payrollRanks,
            picks: $picks,
            draftOrder: $draftOrder,
            draftIndex: DraftSlotIndex::fromDraftOrder($draftOrder),
            modes: $modes,
            injury: $injury,
            teamFactors: $teamFactors,
            rivalFactors: TeamFactors::average($teamFactors),
            resignLikelihood: $resignLikelihood,
            likelyToWalk: $likelyToWalk,
            teamNames: $teamNames,
        );
    }

    /**
     * Projected ratings at `$offset` for every player on a roster that season.
     *
     * @param array<int, array<int, ProjectedSeason>> $projections
     * @return array<int, RatingSet>
     */
    public static function ratingsAt(array $projections, int $offset): array
    {
        $out = [];
        foreach ($projections as $pid => $seasons) {
            $season = $seasons[$offset] ?? null;
            if ($season !== null && $season->onRoster) {
                $out[$pid] = $season->ratings;
            }
        }

        return $out;
    }

    /**
     * Every team in `$teamIds` gets a lineup at every offset, even an empty one.
     *
     * @param list<int> $teamIds
     * @param array<int, ProjectionPlayer> $players
     * @param array<int, int> $teamOfPid
     * @param array<int, array<int, ProjectedSeason>> $projections
     * @return array<int, array<int, SeasonLineup>> offset => tid => lineup
     */
    public static function lineupsFor(array $teamIds, array $players, array $teamOfPid, array $projections): array
    {
        $byTid = [];
        foreach ($teamIds as $tid) {
            $byTid[$tid] = [];
        }
        foreach ($teamOfPid as $pid => $tid) {
            if (isset($byTid[$tid]) && isset($players[$pid])) {
                $byTid[$tid][] = $pid;
            }
        }
        $lineups = [];
        foreach (Horizon::OFFSETS as $offset) {
            foreach ($byTid as $tid => $pids) {
                $projected = [];
                foreach ($pids as $pid) {
                    $season = $projections[$pid][$offset] ?? null;
                    if ($season !== null) {
                        $projected[$pid] = ['player' => $players[$pid], 'season' => $season];
                    }
                }
                $lineups[$offset][$tid] = SeasonLineup::build($tid, $offset, $projected);
            }
        }

        return $lineups;
    }

    /**
     * @return array<string, mixed>
     */
    private static function encodePlayer(ProjectionPlayer $p): array
    {
        $production = $p->production === null ? null : [
            'games' => $p->production->games,
            'minutes' => $p->production->minutes,
            'points' => $p->production->points,
            'rebounds' => $p->production->rebounds,
            'assists' => $p->production->assists,
            'steals' => $p->production->steals,
            'blocks' => $p->production->blocks,
            'turnovers' => $p->production->turnovers,
            'fgm' => $p->production->fgm,
            'fga' => $p->production->fga,
            'ftm' => $p->production->ftm,
            'fta' => $p->production->fta,
            'tgm' => $p->production->tgm,
            'tga' => $p->production->tga,
            'source' => $p->production->source->value,
        ];

        return [
            'pid' => $p->pid,
            'name' => $p->name,
            'pos' => $p->pos,
            'tid' => $p->tid,
            'age' => $p->age,
            'injuredDays' => $p->injuredDays,
            'exp' => $p->exp,
            'talent' => $p->talent,
            'bird' => $p->bird,
            'draftRound' => $p->draftRound,
            'draftYear' => $p->draftYear,
            'draftPickNo' => $p->draftPickNo,
            'prefs' => $p->prefs,
            'ratings' => $p->ratings->toArray(),
            'depth' => $p->depth,
            'dcDepth' => $p->dcDepth,
            'dcMinutes' => $p->dcMinutes,
            'effectiveCy' => $p->effectiveCy,
            'salaryByYear' => $p->salaryByYear,
            'production' => $production,
            'tradeable' => $p->tradeable,
            'productionFouls' => $p->productionFouls,
        ];
    }

    /**
     * @param array<mixed> $row
     */
    private static function decodePlayer(array $row): ProjectionPlayer
    {
        $production = null;
        if (($row['production'] ?? null) !== null) {
            $line = self::arr($row, 'production');
            $source = ProductionSource::tryFrom(is_string($line['source'] ?? null) ? $line['source'] : '')
                ?? throw new \UnexpectedValueException('Unknown production source');
            $production = new ProductionLine(
                games: self::int($line, 'games'),
                minutes: self::int($line, 'minutes'),
                points: self::int($line, 'points'),
                rebounds: self::int($line, 'rebounds'),
                assists: self::int($line, 'assists'),
                steals: self::int($line, 'steals'),
                blocks: self::int($line, 'blocks'),
                turnovers: self::int($line, 'turnovers'),
                fgm: self::int($line, 'fgm'),
                fga: self::int($line, 'fga'),
                ftm: self::int($line, 'ftm'),
                fta: self::int($line, 'fta'),
                tgm: self::int($line, 'tgm'),
                tga: self::int($line, 'tga'),
                source: $source,
            );
        }

        return new ProjectionPlayer(
            pid: self::int($row, 'pid'),
            name: self::str($row, 'name'),
            pos: self::str($row, 'pos'),
            tid: self::int($row, 'tid'),
            age: self::int($row, 'age'),
            injuredDays: self::int($row, 'injuredDays'),
            exp: self::int($row, 'exp'),
            talent: self::int($row, 'talent'),
            bird: self::int($row, 'bird'),
            draftRound: self::int($row, 'draftRound'),
            draftYear: self::int($row, 'draftYear'),
            draftPickNo: self::int($row, 'draftPickNo'),
            prefs: self::stringIntMap(self::arr($row, 'prefs')),
            ratings: RatingSet::fromRow(self::stringKeyed(self::arr($row, 'ratings'))),
            depth: self::stringIntMap(self::arr($row, 'depth')),
            dcDepth: self::stringIntMap(self::arr($row, 'dcDepth')),
            dcMinutes: self::int($row, 'dcMinutes'),
            effectiveCy: self::int($row, 'effectiveCy'),
            salaryByYear: self::intIntMap(self::arr($row, 'salaryByYear')),
            production: $production,
            tradeable: self::bool($row, 'tradeable'),
            productionFouls: self::int($row, 'productionFouls'),
        );
    }

    private static function mode(mixed $name): TeamMode
    {
        foreach (TeamMode::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }
        throw new \UnexpectedValueException('Unknown team mode');
    }

    /**
     * @param array<mixed> $raw
     * @return array<int, array{wins: int, losses: int, pct: float, games_unplayed: int, conference: string}>
     */
    private static function standingsFrom(array $raw): array
    {
        $out = [];
        foreach ($raw as $tid => $row) {
            $r = self::asArray($row);
            $out[self::key($tid)] = [
                'wins' => self::int($r, 'wins'),
                'losses' => self::int($r, 'losses'),
                'pct' => self::num($r['pct'] ?? null),
                'games_unplayed' => self::int($r, 'games_unplayed'),
                'conference' => self::str($r, 'conference'),
            ];
        }

        return $out;
    }

    /**
     * @param array<mixed> $raw
     * @return array<int, array<int|string, int>>
     */
    private static function intGrid(array $raw): array
    {
        $out = [];
        foreach ($raw as $k => $row) {
            $inner = [];
            foreach (self::asArray($row) as $k2 => $v) {
                if (!is_int($v)) {
                    throw new \UnexpectedValueException('Grid value is not an int');
                }
                $inner[$k2] = $v;
            }
            $out[self::key($k)] = $inner;
        }

        return $out;
    }

    /**
     * @param array<mixed> $raw
     * @return array<int, array<int, int>>
     */
    private static function cashGrid(array $raw): array
    {
        $out = [];
        foreach ($raw as $tid => $row) {
            $out[self::key($tid)] = self::intIntMap(self::asArray($row));
        }

        return $out;
    }

    /**
     * @param array<mixed> $raw
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $raw): array
    {
        $out = [];
        foreach ($raw as $k => $v) {
            $out[(string) $k] = $v;
        }

        return $out;
    }

    /**
     * @param array<mixed> $raw
     * @return array<string, int>
     */
    private static function stringIntMap(array $raw): array
    {
        $out = [];
        foreach ($raw as $k => $v) {
            $out[(string) $k] = is_int($v) ? $v : throw new \UnexpectedValueException('Map value is not an int');
        }

        return $out;
    }

    /**
     * @param array<mixed> $raw
     * @return array<int, int>
     */
    private static function intIntMap(array $raw): array
    {
        $out = [];
        foreach ($raw as $k => $v) {
            $out[self::key($k)] = is_int($v) ? $v : throw new \UnexpectedValueException('Map value is not an int');
        }

        return $out;
    }

    /**
     * @param array<mixed> $a
     * @return array<mixed>
     */
    private static function arr(array $a, string $key): array
    {
        return self::asArray($a[$key] ?? null);
    }

    /**
     * @return array<mixed>
     */
    private static function asArray(mixed $v): array
    {
        return is_array($v) ? $v : throw new \UnexpectedValueException('Expected an array in the snapshot payload');
    }

    private static function key(mixed $k): int
    {
        return is_int($k) ? $k : throw new \UnexpectedValueException('Expected an int key in the snapshot payload');
    }

    /**
     * @param array<mixed> $a
     */
    private static function int(array $a, string $key): int
    {
        $v = $a[$key] ?? null;

        return is_int($v) ? $v : throw new \UnexpectedValueException("Field {$key} is not an int");
    }

    /**
     * @param array<mixed> $a
     */
    private static function bool(array $a, string $key): bool
    {
        $v = $a[$key] ?? null;

        return is_bool($v) ? $v : throw new \UnexpectedValueException("Field {$key} is not a bool");
    }

    /**
     * @param array<mixed> $a
     */
    private static function str(array $a, string $key): string
    {
        $v = $a[$key] ?? null;

        return is_string($v) ? $v : throw new \UnexpectedValueException("Field {$key} is not a string");
    }

    private static function num(mixed $v): float
    {
        return is_int($v) || is_float($v) ? $v : throw new \UnexpectedValueException('Expected a number');
    }
}
