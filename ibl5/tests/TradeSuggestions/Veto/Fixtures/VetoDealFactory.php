<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Veto\Fixtures;

use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Engine\DealScorer;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\LeagueSnapshotBuilder;
use TradeSuggestions\Engine\LeagueSnapshotInputs;
use TradeSuggestions\Engine\SideGain;
use TradeSuggestions\Engine\TeamState;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\SlotMapper;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Veto\DealView;
use TradeSuggestions\Veto\SideView;

/**
 * Hand-shaped deals for the six laugh-test vetoes. Teams 1 (the user) and 2
 * (the partner) get fixed 14-player rosters; the other 26 teams are the
 * `SnapshotFactory` league. Every method returns the snapshot and the
 * `DealView` of one candidate.
 *
 * A roster is indexed 0..14: 0-4 start at PG, SG, SF, PF, C (32 minutes),
 * 5-9 back them up at the same slots (16 minutes), 10-13 are G, F, GF, C
 * with no minutes, and 14 is a PG with no minutes (only on a 15-man roster).
 * A spec overrides one player by index: `pos`, `age`, `salary` (year => salary),
 * `base` (every rating), `injured` (days), `minutes`.
 *
 * @phpstan-type Spec array{pos?: string, age?: int, salary?: array<int, int>, base?: float, injured?: int, minutes?: int}
 * @phpstan-type Fixture array{snapshot: LeagueSnapshot, deal: DealView}
 */
final class VetoDealFactory
{
    public const int USER_TID = 1;
    public const int PARTNER_TID = 2;
    /** The partner's round-2 pick, owned by team 2. */
    public const int PARTNER_PICK_ID = 22;

    private const array POSITIONS = ['PG', 'SG', 'SF', 'PF', 'C', 'PG', 'SG', 'SF', 'PF', 'C', 'G', 'F', 'GF', 'C', 'PG'];
    private const array DEFAULT_SALARY = [1 => 300, 2 => 300, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

    /**
     * Clause 1: the user (Middle) gives its young, better, cheaper bench SF
     * for an older, worse, dearer one.
     *
     * @return Fixture
     */
    public static function youngerBetterCheaper(): array
    {
        return self::deal(
            [TeamMode::Middle, TeamMode::Middle],
            [7 => ['age' => 22, 'base' => 70.0, 'salary' => [1 => 300, 2 => 300]]],
            [7 => ['age' => 33, 'base' => 45.0, 'salary' => [1 => 400, 2 => 400]]],
            [self::uid(7)],
            [],
            [self::pid(2, 7)],
            [],
        );
    }

    /**
     * Clause 2: a win-now user sends its SF starter for a clearly worse SF.
     * The received player is younger and cheaper-to-keep, so clause 1 stays quiet.
     *
     * @return Fixture
     */
    public static function winNowWorse(): array
    {
        return self::deal(
            [TeamMode::WinNow, TeamMode::Middle],
            [2 => ['age' => 31, 'base' => 85.0]],
            [2 => ['age' => 25, 'base' => 40.0]],
            [self::uid(2)],
            [],
            [self::pid(2, 2)],
            [],
        );
    }

    /**
     * Clause 3: a rebuilding user takes a 31-year-old on four seasons of dear
     * money and, by default, no pick. Its own sent player is older, so clause 1
     * stays quiet.
     *
     * @param array<int, int> $salary year => salary of the received player
     * @return Fixture
     */
    public static function rebuilderOldLongMoney(
        int $age = 31,
        array $salary = [1 => 1500, 2 => 1500, 3 => 1500, 4 => 1500],
        bool $partnerPick = false,
        TeamMode $userMode = TeamMode::Rebuild,
    ): array {
        return self::deal(
            [$userMode, TeamMode::Middle],
            [7 => ['age' => 32]],
            [7 => ['age' => $age, 'salary' => $salary]],
            [self::uid(7)],
            [],
            [self::pid(2, 7)],
            $partnerPick ? [self::PARTNER_PICK_ID] : [],
        );
    }

    /**
     * Clause 4: the user takes the partner's pick in a swap of two identical
     * bench players, so neither side absorbs any burden.
     *
     * @return Fixture
     */
    public static function pickToNonBurdenTaker(): array
    {
        return self::deal(
            [TeamMode::Middle, TeamMode::Middle],
            [],
            [],
            [self::uid(7)],
            [],
            [self::pid(2, 7)],
            [self::PARTNER_PICK_ID],
        );
    }

    /**
     * Clause 5: the user sends its two no-minute extras for the partner's
     * one, so the partner ends at 16 players.
     *
     * @return Fixture
     */
    public static function rosterOverMax(): array
    {
        return self::deal(
            [TeamMode::Middle, TeamMode::Middle],
            [],
            [],
            [self::uid(10), self::uid(11)],
            [],
            [self::pid(2, 12)],
            [],
            partnerRoster: 15,
        );
    }

    /**
     * The user sends its only backup SG for a backup PG, leaving one playable
     * SG. No veto fires: the GM can play anyone at SG.
     *
     * @return Fixture
     */
    public static function thinSlot(): array
    {
        return self::deal(
            [TeamMode::Middle, TeamMode::Middle],
            [],
            [],
            [self::uid(6)],
            [],
            [self::pid(2, 5)],
            [],
        );
    }

    /**
     * Clause 6: the user sends its strong SF starter and its weak C starter
     * for the partner's two starters at the same slots: SF falls, C rises,
     * and the deal is level.
     *
     * @return Fixture
     */
    public static function strongForWeak(): array
    {
        return self::deal(
            [TeamMode::Middle, TeamMode::Middle],
            [2 => ['base' => 90.0], 4 => ['base' => 20.0]],
            [2 => ['base' => 50.0], 4 => ['base' => 25.0]],
            [self::uid(2), self::uid(4)],
            [],
            [self::pid(2, 2), self::pid(2, 4)],
            [],
        );
    }

    /**
     * A balanced swap of two identical backup SGs, between two Middle teams
     * unless a mode says otherwise.
     *
     * @return Fixture
     */
    public static function quiet(TeamMode $userMode = TeamMode::Middle, TeamMode $partnerMode = TeamMode::Middle): array
    {
        return self::deal(
            [$userMode, $partnerMode],
            [],
            [],
            [self::uid(6)],
            [],
            [self::pid(2, 6)],
            [],
        );
    }

    /**
     * Clauses 1 and 2: a win-now user sends its young, better, cheaper SF
     * starter for an older, worse, dearer one.
     *
     * @return Fixture
     */
    public static function twoVetoes(): array
    {
        return self::deal(
            [TeamMode::WinNow, TeamMode::Middle],
            [2 => ['age' => 22, 'base' => 85.0, 'salary' => [1 => 300, 2 => 300]]],
            [2 => ['age' => 33, 'base' => 40.0, 'salary' => [1 => 400, 2 => 400]]],
            [self::uid(2)],
            [],
            [self::pid(2, 2)],
            [],
        );
    }

    /**
     * A custom deal: both rosters from specs, one candidate. The roster sizes
     * are 14 unless a count says otherwise.
     *
     * @param array{TeamMode, TeamMode} $modes user, partner
     * @param array<int, Spec> $userSpecs index => override
     * @param array<int, Spec> $partnerSpecs index => override
     * @param list<int> $userPids
     * @param list<int> $userPickIds
     * @param list<int> $partnerPids
     * @param list<int> $partnerPickIds
     * @return Fixture
     */
    public static function deal(
        array $modes,
        array $userSpecs,
        array $partnerSpecs,
        array $userPids,
        array $userPickIds,
        array $partnerPids,
        array $partnerPickIds,
        int $userRoster = 14,
        int $partnerRoster = 14,
    ): array {
        $in = SnapshotFactory::inputs(28, 14, [
            self::USER_TID => self::roster(self::USER_TID, $userSpecs, $userRoster),
            self::PARTNER_TID => self::roster(self::PARTNER_TID, $partnerSpecs, $partnerRoster),
        ]);
        $snapshot = LeagueSnapshotBuilder::build(new LeagueSnapshotInputs(
            seasonYear: $in->seasonYear,
            advancesContractYears: $in->advancesContractYears,
            regularSeason: $in->regularSeason,
            playersByTid: $in->playersByTid,
            scale: $in->scale,
            standings: $in->standings,
            streaks: $in->streaks,
            teamFactors: $in->teamFactors,
            cashByTeamAndOffset: $in->cashByTeamAndOffset,
            pickRows: $in->pickRows,
            draftOrder: $in->draftOrder,
            modes: array_replace($in->modes, [self::USER_TID => $modes[0], self::PARTNER_TID => $modes[1]]),
            teamNames: $in->teamNames,
        ));
        $candidate = new TradeCandidate(self::USER_TID, self::PARTNER_TID, $userPids, $userPickIds, $partnerPids, $partnerPickIds);

        return ['snapshot' => $snapshot, 'deal' => self::view($snapshot, $candidate)];
    }

    public static function view(LeagueSnapshot $snapshot, TradeCandidate $candidate): DealView
    {
        return DealView::fromScoredDeal($snapshot, DealScorer::score($snapshot, $candidate, 'fixture'));
    }

    /** Pid of roster index `$i` on the user's team. */
    public static function uid(int $i): int
    {
        return self::pid(self::USER_TID, $i);
    }

    public static function pid(int $tid, int $i): int
    {
        return 50000 + $tid * 100 + $i;
    }

    /**
     * The deal with one side's offset-0 after-state starter ranks replaced.
     *
     * @param array<string, ?int> $starterRank slot => rank
     */
    public static function withAfterStarterRanks(DealView $deal, SideView $side, array $starterRank): DealView
    {
        return self::withStarterRanks($deal, $side, $side->before[0]->starterRank, $starterRank);
    }

    /**
     * The deal with one side's offset-0 before- and after-state starter ranks replaced.
     *
     * @param array<string, ?int> $before slot => rank
     * @param array<string, ?int> $after slot => rank
     */
    public static function withStarterRanks(DealView $deal, SideView $side, array $before, array $after): DealView
    {
        $beforeStates = $side->before;
        $beforeStates[0] = self::withRanks($beforeStates[0], $before);
        $afterStates = $side->after;
        $afterStates[0] = self::withRanks($afterStates[0], $after);

        return self::replace($deal, $side, new SideView(
            $side->tid,
            $side->mode,
            $side->sent,
            $side->received,
            $side->sentPicks,
            $side->receivedPicks,
            $beforeStates,
            $afterStates,
            $side->gain,
            $side->situation,
            $side->surplusByPid,
            $side->delta,
            $side->pickWorthById,
            $side->availabilityByPid,
            $side->injuryByPid,
        ));
    }

    /**
     * @param array<string, ?int> $starterRank slot => rank
     */
    private static function withRanks(TeamState $state, array $starterRank): TeamState
    {
        return new TeamState(
            $state->tid,
            $state->offset,
            $state->lineup,
            $starterRank,
            $state->bestBenchRank,
            $state->profile,
            $state->positionStrength,
            $state->payroll,
            $state->payrollRank,
            $state->rosterCount,
        );
    }

    /**
     * The deal with one side's gain replaced by one whose total is `$total`.
     */
    public static function withGainTotal(DealView $deal, SideView $side, float $total): DealView
    {
        $gain = new SideGain($side->tid, $side->mode, [0 => ['starter_slots' => $total]], [1.0, 0.0, 0.0, 0.0], 1.0);

        return self::replace($deal, $side, new SideView(
            $side->tid,
            $side->mode,
            $side->sent,
            $side->received,
            $side->sentPicks,
            $side->receivedPicks,
            $side->before,
            $side->after,
            $gain,
            $side->situation,
            $side->surplusByPid,
            $side->delta,
            $side->pickWorthById,
            $side->availabilityByPid,
            $side->injuryByPid,
        ));
    }

    /**
     * The deal with the worth of one pick on one side replaced.
     */
    public static function withPickWorth(DealView $deal, SideView $side, int $pickId, float $worth): DealView
    {
        $worths = $side->pickWorthById;
        $worths[$pickId] = $worth;

        return self::replace($deal, $side, new SideView(
            $side->tid,
            $side->mode,
            $side->sent,
            $side->received,
            $side->sentPicks,
            $side->receivedPicks,
            $side->before,
            $side->after,
            $side->gain,
            $side->situation,
            $side->surplusByPid,
            $side->delta,
            $worths,
            $side->availabilityByPid,
            $side->injuryByPid,
        ));
    }

    /**
     * The deal with one player's surplus by offset replaced on one side.
     *
     * @param array<int, int> $surplus offset => surplus
     */
    public static function withSurplus(DealView $deal, SideView $side, int $pid, array $surplus): DealView
    {
        $surplusByPid = $side->surplusByPid;
        $surplusByPid[$pid] = $surplus;

        return self::replace($deal, $side, new SideView(
            $side->tid,
            $side->mode,
            $side->sent,
            $side->received,
            $side->sentPicks,
            $side->receivedPicks,
            $side->before,
            $side->after,
            $side->gain,
            $side->situation,
            $surplusByPid,
            $side->delta,
            $side->pickWorthById,
            $side->availabilityByPid,
            $side->injuryByPid,
        ));
    }

    /**
     * Every slot's rank in `$before` moved `$places` worse (positive) or better (negative).
     *
     * @param array<string, ?int> $before
     * @param array<string, int> $shift slot => places worse
     * @return array<string, ?int>
     */
    public static function shifted(array $before, array $shift): array
    {
        $out = [];
        foreach (SlotMapper::SLOTS as $slot) {
            $rank = $before[$slot] ?? SideGain::EMPTY_SLOT_RANK;
            $out[$slot] = $rank + ($shift[$slot] ?? 0);
        }

        return $out;
    }

    private static function replace(DealView $deal, SideView $old, SideView $new): DealView
    {
        return $old === $deal->user
            ? new DealView($new, $deal->partner, $deal->candidate, $deal->shapeId)
            : new DealView($deal->user, $new, $deal->candidate, $deal->shapeId);
    }

    /**
     * @param array<int, Spec> $specs
     * @return list<ProjectionPlayer>
     */
    private static function roster(int $tid, array $specs, int $size): array
    {
        $players = [];
        for ($i = 0; $i < $size; $i++) {
            $players[] = self::player($tid, $i, $specs[$i] ?? []);
        }

        return $players;
    }

    /**
     * @param Spec $spec
     */
    private static function player(int $tid, int $i, array $spec): ProjectionPlayer
    {
        $pos = $spec['pos'] ?? self::POSITIONS[$i];
        $depth = $i < 5 ? 1 : 2;
        $minutes = $spec['minutes'] ?? ($i < 5 ? 32 : ($i < 10 ? 16 : 0));
        $base = $spec['base'] ?? ($i < 5 ? 60.0 : ($i < 10 ? 50.0 : 35.0));
        $salary = $spec['salary'] ?? self::DEFAULT_SALARY;
        $salaryByYear = [];
        for ($y = 1; $y <= 6; $y++) {
            $salaryByYear[$y] = $salary[$y] ?? 0;
        }
        $depthSlot = SlotMapper::slotsForPos($pos)[0] ?? 'PG';

        return ProjectionFactory::player(
            pid: self::pid($tid, $i),
            tid: $tid,
            pos: $pos,
            age: $spec['age'] ?? 27,
            salaryByYear: $salaryByYear,
            ratings: ProjectionFactory::ratingSet([], $base),
            injuredDays: $spec['injured'] ?? 0,
            dcDepth: [$depthSlot => $depth],
            dcMinutes: $minutes,
        );
    }
}
