<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Enumerates a hard-capped set of one-for-one deals. Pure.
 *
 * Per partner (ascending tid), shapes are emitted in a fixed order: player for
 * player, user player for partner pick, user pick for partner player. Pick for
 * pick is skipped because it never changes a roster need.
 */
final class CandidateGenerator
{
    public const int MAX_CANDIDATES = 4000;
    public const int POOL_PLAYERS = 8;
    public const int POOL_PICKS = 4;

    public function __construct(private readonly int $maxCandidates = self::MAX_CANDIDATES)
    {
    }

    /**
     * @param list<TeamAssets> $partners
     * @return list<TradeCandidate>
     */
    public function generate(TeamAssets $user, array $partners): array
    {
        $userPlayers = self::playerPool($user->players);
        $userPicks = self::pickPool($user->picks);

        usort($partners, static fn (TeamAssets $a, TeamAssets $b): int => $a->tid <=> $b->tid);

        $candidates = [];
        foreach ($partners as $partner) {
            if ($partner->tid === $user->tid) {
                continue;
            }
            $partnerPlayers = self::playerPool($partner->players);
            $partnerPicks = self::pickPool($partner->picks);

            foreach ($userPlayers as $mine) {
                foreach ($partnerPlayers as $theirs) {
                    $candidates[] = new TradeCandidate($user->tid, $partner->tid, [$mine->pid], [], [$theirs->pid], []);
                    if (count($candidates) >= $this->maxCandidates) {
                        return $candidates;
                    }
                }
            }

            foreach ($userPlayers as $mine) {
                foreach ($partnerPicks as $theirs) {
                    $candidates[] = new TradeCandidate($user->tid, $partner->tid, [$mine->pid], [], [], [$theirs->pickId]);
                    if (count($candidates) >= $this->maxCandidates) {
                        return $candidates;
                    }
                }
            }

            foreach ($userPicks as $mine) {
                foreach ($partnerPlayers as $theirs) {
                    $candidates[] = new TradeCandidate($user->tid, $partner->tid, [], [$mine->pickId], [$theirs->pid], []);
                    if (count($candidates) >= $this->maxCandidates) {
                        return $candidates;
                    }
                }
            }
        }

        return $candidates;
    }

    /**
     * Top POOL_PLAYERS players by quality, ties by lower pid.
     *
     * @param list<SuggestionPlayer> $players
     * @return list<SuggestionPlayer>
     */
    private static function playerPool(array $players): array
    {
        usort($players, static fn (SuggestionPlayer $a, SuggestionPlayer $b): int => [$b->quality, $a->pid] <=> [$a->quality, $b->pid]);

        return array_slice($players, 0, self::POOL_PLAYERS);
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
}
