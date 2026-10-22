<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Enumerates candidate deals with each partner. Pure; yields lazily.
 *
 * Per partner (ascending tid), shapes come in a fixed order:
 * 1-for-1 players; 2 players for 1 and 1 for 2 (the single player must
 * out-rate both of the pair, so it is a consolidation); player for one or two
 * picks; one or two picks for a player; player + pick for player, both ways.
 * Untradeable players never enter a pool. Each partner yields at most
 * $maxPerPartner deals, so every partner gets a fair share.
 */
final class CandidateGenerator
{
    public const int MAX_PER_PARTNER = 3000;
    public const int PAIR_POOL = 10;
    public const int POOL_PICKS = 4;
    public const int SWEETENER_PICKS = 2;

    public function __construct(private readonly int $maxPerPartner = self::MAX_PER_PARTNER)
    {
    }

    /**
     * @param list<TeamAssets> $partners
     * @return \Generator<int, TradeCandidate>
     */
    public function generate(TeamAssets $user, array $partners): \Generator
    {
        $userPlayers = self::playerPool($user->players);
        $userPairs = self::pairs(array_slice($userPlayers, 0, self::PAIR_POOL));
        $userPicks = self::pickPool($user->picks);
        $userPickPairs = self::pairs($userPicks);

        usort($partners, static fn (TeamAssets $a, TeamAssets $b): int => $a->tid <=> $b->tid);

        foreach ($partners as $partner) {
            if ($partner->tid === $user->tid) {
                continue;
            }
            $count = 0;
            foreach ($this->forPartner($user->tid, $partner, $userPlayers, $userPairs, $userPicks, $userPickPairs) as $candidate) {
                yield $candidate;
                if (++$count >= $this->maxPerPartner) {
                    break;
                }
            }
        }
    }

    /**
     * @param list<SuggestionPlayer> $userPlayers
     * @param list<array{SuggestionPlayer, SuggestionPlayer}> $userPairs
     * @param list<SuggestionPick> $userPicks
     * @param list<array{SuggestionPick, SuggestionPick}> $userPickPairs
     * @return \Generator<int, TradeCandidate>
     */
    private function forPartner(int $userTid, TeamAssets $partner, array $userPlayers, array $userPairs, array $userPicks, array $userPickPairs): \Generator
    {
        $tid = $partner->tid;
        $partnerPlayers = self::playerPool($partner->players);
        $partnerPairs = self::pairs(array_slice($partnerPlayers, 0, self::PAIR_POOL));
        $partnerPicks = self::pickPool($partner->picks);
        $partnerPickPairs = self::pairs($partnerPicks);

        foreach ($userPlayers as $mine) {
            foreach ($partnerPlayers as $theirs) {
                yield new TradeCandidate($userTid, $tid, [$mine->pid], [], [$theirs->pid], []);
            }
        }

        foreach ($userPairs as [$a, $b]) {
            foreach ($partnerPlayers as $theirs) {
                if ($theirs->ovr > max($a->ovr, $b->ovr)) {
                    yield new TradeCandidate($userTid, $tid, [$a->pid, $b->pid], [], [$theirs->pid], []);
                }
            }
        }
        foreach ($userPlayers as $mine) {
            foreach ($partnerPairs as [$a, $b]) {
                if ($mine->ovr > max($a->ovr, $b->ovr)) {
                    yield new TradeCandidate($userTid, $tid, [$mine->pid], [], [$a->pid, $b->pid], []);
                }
            }
        }

        foreach ($userPlayers as $mine) {
            foreach ($partnerPicks as $pick) {
                yield new TradeCandidate($userTid, $tid, [$mine->pid], [], [], [$pick->pickId]);
            }
            foreach ($partnerPickPairs as [$p1, $p2]) {
                yield new TradeCandidate($userTid, $tid, [$mine->pid], [], [], [$p1->pickId, $p2->pickId]);
            }
        }
        foreach ($partnerPlayers as $theirs) {
            foreach ($userPicks as $pick) {
                yield new TradeCandidate($userTid, $tid, [], [$pick->pickId], [$theirs->pid], []);
            }
            foreach ($userPickPairs as [$p1, $p2]) {
                yield new TradeCandidate($userTid, $tid, [], [$p1->pickId, $p2->pickId], [$theirs->pid], []);
            }
        }

        $userSweeteners = array_slice($userPicks, 0, self::SWEETENER_PICKS);
        $partnerSweeteners = array_slice($partnerPicks, 0, self::SWEETENER_PICKS);
        foreach ($userPlayers as $mine) {
            foreach ($partnerPlayers as $theirs) {
                foreach ($userSweeteners as $pick) {
                    yield new TradeCandidate($userTid, $tid, [$mine->pid], [$pick->pickId], [$theirs->pid], []);
                }
                foreach ($partnerSweeteners as $pick) {
                    yield new TradeCandidate($userTid, $tid, [$mine->pid], [], [$theirs->pid], [$pick->pickId]);
                }
            }
        }
    }

    /**
     * Tradeable players by ovr, ties by lower pid.
     *
     * @param list<SuggestionPlayer> $players
     * @return list<SuggestionPlayer>
     */
    private static function playerPool(array $players): array
    {
        $pool = array_values(array_filter($players, static fn (SuggestionPlayer $p): bool => $p->tradeable));
        usort($pool, static fn (SuggestionPlayer $a, SuggestionPlayer $b): int => [$b->ovr, $a->pid] <=> [$a->ovr, $b->pid]);

        return $pool;
    }

    /**
     * Top POOL_PICKS picks by value, ties by lower pickId.
     *
     * @param list<SuggestionPick> $picks
     * @return list<SuggestionPick>
     */
    private static function pickPool(array $picks): array
    {
        usort($picks, static fn (SuggestionPick $a, SuggestionPick $b): int => [$b->value, $a->pickId] <=> [$a->value, $b->pickId]);

        return array_slice($picks, 0, self::POOL_PICKS);
    }

    /**
     * Every unordered pair, in list order.
     *
     * @template T
     * @param list<T> $items
     * @return list<array{T, T}>
     */
    private static function pairs(array $items): array
    {
        $pairs = [];
        $n = count($items);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $pairs[] = [$items[$i], $items[$j]];
            }
        }

        return $pairs;
    }
}
