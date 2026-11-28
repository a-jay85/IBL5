<?php

declare(strict_types=1);

namespace TradeSuggestions\Veto;

use Team\Team;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\PairRecordMemo;
use TradeSuggestions\Engine\PickAsset;
use TradeSuggestions\Engine\ProfileDelta;
use TradeSuggestions\Engine\ScoredDeal;
use TradeSuggestions\Engine\SideGain;
use TradeSuggestions\Engine\TeamState;
use TradeSuggestions\Engine\TeamStateRebuilder;
use TradeSuggestions\Projection\Horizon;
use TradeSuggestions\Projection\InjuryStatus;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\TradeCandidate;

/**
 * One immutable view of a scored deal. Built once per candidate; every veto
 * reads this and nothing else.
 */
final readonly class DealView
{
    public function __construct(
        public SideView $user,
        public SideView $partner,
        public TradeCandidate $candidate,
        public string $shapeId,
    ) {
    }

    /**
     * @param PairRecordMemo|null $memo a null memo makes a fresh one; the ranker passes one per call
     */
    public static function fromScoredDeal(LeagueSnapshot $s, ScoredDeal $d, ?PairRecordMemo $memo = null): self
    {
        $memo ??= new PairRecordMemo();
        $c = $d->candidate;

        $afterUser = [];
        $afterPartner = [];
        foreach (Horizon::OFFSETS as $offset) {
            $pair = TeamStateRebuilder::after($s, $c, $offset, $memo);
            $afterUser[$offset] = $pair['user'];
            $afterPartner[$offset] = $pair['partner'];
        }

        $availability = self::availability($s, $c);

        return new self(
            self::side($s, $c->userTid, $c->userPids, $c->partnerPids, $c->userPickIds, $c->partnerPickIds, $afterUser, $d->user, $d->userDelta, $availability),
            self::side($s, $c->partnerTid, $c->partnerPids, $c->userPids, $c->partnerPickIds, $c->userPickIds, $afterPartner, $d->partner, $d->partnerDelta, $availability),
            $c,
            $d->shapeId,
        );
    }

    /**
     * @return array{SideView, SideView}
     */
    public function sides(): array
    {
        return [$this->user, $this->partner];
    }

    public function other(SideView $side): SideView
    {
        return $side === $this->user ? $this->partner : $this->user;
    }

    /**
     * Every pair of one player the giver sends and one it receives.
     *
     * @return iterable<array{ProjectionPlayer, ProjectionPlayer}>
     */
    public function pairs(SideView $giver): iterable
    {
        foreach ($giver->sent as $given) {
            foreach ($giver->received as $got) {
                yield [$given, $got];
            }
        }
    }

    /**
     * @param list<int> $sentPids
     * @param list<int> $receivedPids
     * @param list<int> $sentPickIds
     * @param list<int> $receivedPickIds
     * @param array<int, TeamState> $after
     * @param array<int, ProfileDelta> $delta
     * @param array<int, float> $availability
     */
    private static function side(
        LeagueSnapshot $s,
        int $tid,
        array $sentPids,
        array $receivedPids,
        array $sentPickIds,
        array $receivedPickIds,
        array $after,
        SideGain $gain,
        array $delta,
        array $availability,
    ): SideView {
        $mode = $s->mode($tid);
        $before = [];
        foreach (Horizon::OFFSETS as $offset) {
            $before[$offset] = TeamStateRebuilder::before($s, $tid, $offset);
        }

        $sent = array_map(static fn (int $pid): ProjectionPlayer => $s->players[$pid], $sentPids);
        $received = array_map(static fn (int $pid): ProjectionPlayer => $s->players[$pid], $receivedPids);
        $sentPicks = array_map(static fn (int $id): PickAsset => $s->picks[$id], $sentPickIds);
        $receivedPicks = array_map(static fn (int $id): PickAsset => $s->picks[$id], $receivedPickIds);

        $surplus = [];
        foreach ([...$sentPids, ...$receivedPids] as $pid) {
            $surplus[$pid] = $s->surplusBySeason($pid);
        }

        $injury = [];
        foreach ($receivedPids as $pid) {
            $injury[$pid] = $s->injury[$pid] ?? InjuryStatus::Healthy;
        }

        $open = Team::ROSTER_SPOTS_MAX - $after[0]->rosterCount;
        $worth = [];
        foreach ($receivedPicks as $i => $pick) {
            $worth[$pick->pickId] = SideGain::pickWorth($s, $pick, $mode, $open, $i + 1);
        }
        foreach ($sentPicks as $pick) {
            $worth[$pick->pickId] = SideGain::pickWorth($s, $pick, $mode, $open, 1);
        }

        return new SideView(
            $tid,
            $mode,
            $sent,
            $received,
            $sentPicks,
            $receivedPicks,
            $before,
            $after,
            $gain,
            $s->situations[$tid] ?? null,
            $surplus,
            $delta,
            $worth,
            $availability,
            $injury,
        );
    }

    /**
     * Offset-0 availability for every player on either team before or after
     * the deal. Every after-deal player starts on one of the two rosters, so
     * the before lineups cover both. A player with no injury entry counts in full.
     *
     * @return array<int, float>
     */
    private static function availability(LeagueSnapshot $s, TradeCandidate $c): array
    {
        $pids = [
            ...array_keys($s->lineup(0, $c->userTid)->players),
            ...array_keys($s->lineup(0, $c->partnerTid)->players),
        ];
        $out = [];
        foreach ($pids as $pid) {
            $out[$pid] = ($s->injury[$pid] ?? null)?->availabilityWeight() ?? 1.0;
        }

        return $out;
    }
}
