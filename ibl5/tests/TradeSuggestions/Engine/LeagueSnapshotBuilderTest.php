<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\LeagueSnapshotBuilder;
use TradeSuggestions\Engine\LeagueSnapshotInputs;
use TradeSuggestions\Projection\Horizon;
use TradeSuggestions\Projection\InjuryStatus;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\Projection\Rating;
use TradeSuggestions\SlotMapper;
use TradeSuggestions\Valuation\TeamFactors;

final class LeagueSnapshotBuilderTest extends TestCase
{
    private static ?LeagueSnapshot $league = null;

    private static function league(): LeagueSnapshot
    {
        return self::$league ??= SnapshotFactory::league();
    }

    public function testEveryOffsetHasALineupPerTeam(): void
    {
        $s = self::league();

        self::assertSame(Horizon::OFFSETS, array_keys($s->lineups));
        foreach (Horizon::OFFSETS as $offset) {
            self::assertSame($s->teamIds, array_keys($s->lineups[$offset]));
            foreach ($s->teamIds as $tid) {
                self::assertSame($offset, $s->lineup($offset, $tid)->offset);
            }
        }
    }

    public function testProfilesCarryTheirOwnOffset(): void
    {
        $s = self::league();

        self::assertSame(2, $s->profiles[2][1]->offset);
        self::assertSame(3, $s->profiles[3][5]->offset);
    }

    public function testFairSalaryPoolRanksComeFromRotationPidsOnly(): void
    {
        $base = SnapshotFactory::inputs();
        $baseline = LeagueSnapshotBuilder::build($base);
        $lineup = $baseline->lineup(0, 1);
        $rotation = [];
        foreach ($lineup->rotation as $pids) {
            $rotation = array_merge($rotation, $pids);
        }
        $outside = null;
        foreach (array_keys($lineup->players) as $pid) {
            if (!in_array($pid, $rotation, true)) {
                $outside = $pid;
                break;
            }
        }
        self::assertNotNull($outside, 'team 1 has a player outside the rotation');

        $roster = [];
        foreach ($base->playersByTid[1] as $p) {
            $roster[] = $p->pid === $outside
                ? self::copy($p, salaryByYear: [1 => 9000, 2 => 9000, 3 => 9000, 4 => 0, 5 => 0, 6 => 0])
                : $p;
        }
        $changed = LeagueSnapshotBuilder::build(SnapshotFactory::inputs(overrideRosters: [1 => $roster]));

        foreach (SlotMapper::SLOTS as $slot) {
            self::assertNull($changed->slotRanks[0]->poolRank($slot, $outside));
            self::assertSame($baseline->fairSalary[0]->tiers($slot), $changed->fairSalary[0]->tiers($slot), $slot);
        }
    }

    public function testPayrollRanksUseCashConsiderations(): void
    {
        $base = SnapshotFactory::inputs(overrideRosters: [1 => [], 2 => []]);
        $noCash = LeagueSnapshotBuilder::build($base);
        self::assertSame($noCash->payrollByOffset[0][1], $noCash->payrollByOffset[0][2]);
        self::assertLessThan($noCash->payrollRanks[0][2], $noCash->payrollRanks[0][1]);

        $cash = $base->cashByTeamAndOffset;
        $cash[1][0] = 200;
        $withCash = LeagueSnapshotBuilder::build(self::with($base, cash: $cash));

        self::assertSame($withCash->payrollByOffset[0][2] + 200, $withCash->payrollByOffset[0][1]);
        self::assertGreaterThan($withCash->payrollRanks[0][2], $withCash->payrollRanks[0][1]);
    }

    public function testEmptyRosterTeamStillGetsLineupAndProfile(): void
    {
        $s = LeagueSnapshotBuilder::build(SnapshotFactory::inputs(overrideRosters: [7 => []]));

        $lineup = $s->lineup(0, 7);
        self::assertSame([], $lineup->players);
        foreach (SlotMapper::SLOTS as $slot) {
            self::assertNull($lineup->starterPid($slot));
        }
        self::assertArrayHasKey(7, $s->profiles[0]);
        self::assertNotSame([], $s->profiles[0][7]->ranks);
        self::assertContains(28, $s->profiles[0][7]->ranks);
    }

    public function testPastDraftPicksAreDropped(): void
    {
        $base = SnapshotFactory::inputs();
        $rows = $base->pickRows;
        $rows[3][] = ['pickid' => 999, 'owner_teamid' => 3, 'teampick_teamid' => 3, 'year' => SnapshotFactory::SEASON_YEAR - 1, 'round' => 1];
        $s = LeagueSnapshotBuilder::build(self::with($base, pickRows: $rows));

        self::assertArrayNotHasKey(999, $s->picks);
        self::assertSame(0, $s->picks[31]->yearsAway);
        self::assertSame(1, $s->picks[32]->yearsAway);
    }

    public function testPastPickAheadOfLivePicksDoesNotDropThem(): void
    {
        $base = SnapshotFactory::inputs();
        $rows = $base->pickRows;
        array_unshift($rows[3], ['pickid' => 999, 'owner_teamid' => 3, 'teampick_teamid' => 3, 'year' => SnapshotFactory::SEASON_YEAR - 1, 'round' => 1]);
        $s = LeagueSnapshotBuilder::build(self::with($base, pickRows: $rows));

        self::assertArrayNotHasKey(999, $s->picks);
        self::assertArrayHasKey(31, $s->picks);
        self::assertArrayHasKey(32, $s->picks);
    }

    public function testNonFranchiseTidsExcludedFromTeamIds(): void
    {
        $s = self::league();

        self::assertNotContains(SnapshotFactory::NON_FRANCHISE_TID, $s->teamIds);
        self::assertArrayNotHasKey(SnapshotFactory::NON_FRANCHISE_PID, $s->players);
    }

    public function testInjuryStatusUsesTeamGamesLeft(): void
    {
        $base = SnapshotFactory::inputs();
        $rosters = [];
        foreach ([1, 2] as $tid) {
            $roster = $base->playersByTid[$tid];
            $roster[0] = self::copy($roster[0], injuredDays: 30);
            $rosters[$tid] = $roster;
        }
        $standings = $base->standings;
        $standings[1]['games_unplayed'] = 20;
        $standings[2]['games_unplayed'] = 60;
        $s = LeagueSnapshotBuilder::build(self::with(
            SnapshotFactory::inputs(overrideRosters: $rosters),
            standings: $standings,
        ));

        self::assertSame(InjuryStatus::OutForYear, $s->injury[101]);
        self::assertSame(InjuryStatus::BackForPlayoffs, $s->injury[201]);
    }

    public function testResignLikelihoodUsesOwnTeamAgainstLeagueMean(): void
    {
        $base = SnapshotFactory::inputs();
        $star = self::walkerCandidate(1);
        $roster = $base->playersByTid[1];
        $roster[0] = $star;

        $factors = $base->teamFactors;
        $factors[1] = new TeamFactors(1, 5, 55, 100, 1900);
        $losing = LeagueSnapshotBuilder::build(self::with(
            SnapshotFactory::inputs(overrideRosters: [1 => $roster]),
            teamFactors: $factors,
        ));
        $factors[1] = new TeamFactors(1, 55, 5, 1900, 100);
        $winning = LeagueSnapshotBuilder::build(self::with(
            SnapshotFactory::inputs(overrideRosters: [1 => $roster]),
            teamFactors: $factors,
        ));

        self::assertSame(1, $losing->slotRanks[0]->poolRank('PG', $star->pid));
        self::assertTrue($losing->likelyToWalk[$star->pid]);
        self::assertFalse($winning->likelyToWalk[$star->pid]);
        self::assertGreaterThan($losing->resignLikelihood[$star->pid], $winning->resignLikelihood[$star->pid]);
    }

    public function testMissingTeamFactorsDefaultToZeroRecord(): void
    {
        $base = SnapshotFactory::inputs();
        $factors = $base->teamFactors;
        unset($factors[9]);
        $s = LeagueSnapshotBuilder::build(self::with($base, teamFactors: $factors));

        self::assertArrayHasKey(901, $s->resignLikelihood);
        self::assertGreaterThan(0.0, $s->resignLikelihood[901]);
    }

    public function testNonPoolPlayerIsNeverAWalker(): void
    {
        $s = self::league();
        $checked = 0;
        foreach ($s->likelyToWalk as $pid => $walks) {
            $inPool = false;
            foreach (SlotMapper::SLOTS as $slot) {
                $inPool = $inPool || $s->slotRanks[0]->poolRank($slot, $pid) !== null;
            }
            if (!$inPool) {
                self::assertFalse($walks, "pid {$pid} has no pool rank");
                $checked++;
            }
        }
        self::assertGreaterThan(0, $checked);
    }

    /**
     * A top-of-pool PG with two seasons left who cares about winning and tradition.
     */
    private static function walkerCandidate(int $tid): ProjectionPlayer
    {
        $ratings = [];
        foreach (Rating::ALL as $key) {
            $ratings[$key] = 99;
        }
        $p = ProjectionFactory::player(
            pid: $tid * 100 + 1,
            tid: $tid,
            pos: 'PG',
            age: 27,
            talent: 90,
            salaryByYear: [1 => 2000, 2 => 2000, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
            ratings: ProjectionFactory::ratingSet($ratings),
            dcDepth: ['PG' => 1],
            dcMinutes: 36,
        );

        return self::copy($p, prefs: ['loyalty' => 1, 'winner' => 5, 'tradition' => 5, 'security' => 1, 'playing_time' => 1]);
    }

    /**
     * @param array<int, int>|null $salaryByYear
     * @param array<string, int>|null $prefs
     */
    private static function copy(
        ProjectionPlayer $p,
        ?array $salaryByYear = null,
        ?int $injuredDays = null,
        ?array $prefs = null,
    ): ProjectionPlayer {
        return new ProjectionPlayer(
            pid: $p->pid,
            name: $p->name,
            pos: $p->pos,
            tid: $p->tid,
            age: $p->age,
            injuredDays: $injuredDays ?? $p->injuredDays,
            exp: $p->exp,
            talent: $p->talent,
            bird: $p->bird,
            draftRound: $p->draftRound,
            draftYear: $p->draftYear,
            draftPickNo: $p->draftPickNo,
            prefs: $prefs ?? $p->prefs,
            ratings: $p->ratings,
            depth: $p->depth,
            dcDepth: $p->dcDepth,
            dcMinutes: $p->dcMinutes,
            effectiveCy: $p->effectiveCy,
            salaryByYear: $salaryByYear ?? $p->salaryByYear,
            production: $p->production,
            tradeable: $p->tradeable,
            productionFouls: $p->productionFouls,
        );
    }

    /**
     * @param array<int, array<int, int>>|null $cash
     * @param array<int, list<array{pickid: int, owner_teamid: int, teampick_teamid: int, year: int, round: int}>>|null $pickRows
     * @param array<int, array{wins: int, losses: int, pct: float, games_unplayed: int, conference: string}>|null $standings
     * @param array<int, TeamFactors>|null $teamFactors
     */
    private static function with(
        LeagueSnapshotInputs $in,
        ?array $cash = null,
        ?array $pickRows = null,
        ?array $standings = null,
        ?array $teamFactors = null,
    ): LeagueSnapshotInputs {
        return new LeagueSnapshotInputs(
            seasonYear: $in->seasonYear,
            advancesContractYears: $in->advancesContractYears,
            regularSeason: $in->regularSeason,
            playersByTid: $in->playersByTid,
            scale: $in->scale,
            standings: $standings ?? $in->standings,
            streaks: $in->streaks,
            teamFactors: $teamFactors ?? $in->teamFactors,
            cashByTeamAndOffset: $cash ?? $in->cashByTeamAndOffset,
            pickRows: $pickRows ?? $in->pickRows,
            draftOrder: $in->draftOrder,
            modes: $in->modes,
            teamNames: $in->teamNames,
        );
    }
}
