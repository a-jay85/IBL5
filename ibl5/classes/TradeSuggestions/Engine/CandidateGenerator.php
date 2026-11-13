<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\Engine\Shape\Contracts\DealShapeInterface;
use TradeSuggestions\TradeCandidate;

/**
 * Builds the raw candidate deals for one user team.
 *
 * 1. Partners come from the exposure filter. None is an empty result.
 * 2. A partner with no need pair is skipped. Otherwise each side's asset pool
 *    takes the matcher's assets, pair by pair, up to six distinct players,
 *    plus the side's owned round 1 and round 2 picks.
 * 3. Every shape runs over the pool. A deal seen before is dropped, so the
 *    first shape to yield it keeps its id. A side with more than two assets is
 *    dropped. The raw count stops at 4,000 before the legality pre-filter, so
 *    the cap bounds the shape work too.
 */
final class CandidateGenerator
{
    /**
     * @param list<DealShapeInterface> $shapes
     */
    public function __construct(private readonly array $shapes)
    {
    }

    public function generate(LeagueSnapshot $s, int $userTid, string $simKey): GenerationResult
    {
        $partners = [];
        $candidates = [];
        $seen = [];
        $shapeIdByKey = [];
        $rejectedByReason = [];
        $raw = 0;
        $rejected = 0;
        $capped = false;

        foreach (ExposureFilter::eligiblePartners($simKey, $userTid, $s->teamIds) as $partnerTid) {
            $pairs = SurplusDeficitMatcher::needPairs($s, $userTid, $partnerTid);
            if ($pairs === []) {
                continue;
            }
            $partners[] = $partnerTid;
            $pool = new AssetPool(
                userPids: self::playerPool($s, $userTid, $pairs),
                partnerPids: self::playerPool($s, $partnerTid, $pairs),
                userPicks: self::pickPool($s, $userTid),
                partnerPicks: self::pickPool($s, $partnerTid),
                needPairs: $pairs,
            );

            foreach ($this->shapes as $shape) {
                foreach ($shape->generate($s, $userTid, $partnerTid, $pool) as $candidate) {
                    $key = CandidateKey::of($candidate);
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = $key;
                    if (self::tooManyAssets($candidate)) {
                        continue;
                    }
                    $raw++;
                    $reason = LegalityPreFilter::reason($s, $candidate);
                    if ($reason === null) {
                        $candidates[] = $candidate;
                        $shapeIdByKey[$key] = $shape->id();
                    } else {
                        $rejected++;
                        $rejectedByReason[$reason] = ($rejectedByReason[$reason] ?? 0) + 1;
                    }
                    if ($raw === CandidateBudget::MAX_RAW_CANDIDATES) {
                        $capped = true;
                        break 3;
                    }
                }
            }
        }

        return new GenerationResult($candidates, $raw, $rejected, $rejectedByReason, $partners, $capped, $shapeIdByKey);
    }

    /**
     * @param list<NeedPair> $pairs
     * @return list<int>
     */
    private static function playerPool(LeagueSnapshot $s, int $tid, array $pairs): array
    {
        $pool = [];
        foreach ($pairs as $pair) {
            if ($pair->giverTid !== $tid) {
                continue;
            }
            foreach (SurplusDeficitMatcher::assetsFor($s, $tid, $pair, CandidateBudget::PLAYERS_PER_PARTNER) as $pid) {
                $pool[$pid] = $pid;
                if (count($pool) === CandidateBudget::PLAYERS_PER_PARTNER) {
                    return array_keys($pool);
                }
            }
        }

        return array_keys($pool);
    }

    /**
     * @return list<PickAsset>
     */
    private static function pickPool(LeagueSnapshot $s, int $tid): array
    {
        $picks = [];
        foreach ($s->picks as $pick) {
            if ($pick->ownerTid === $tid && $pick->round <= CandidateBudget::PICK_ROUNDS_MAX) {
                $picks[] = $pick;
            }
        }

        return $picks;
    }

    private static function tooManyAssets(TradeCandidate $c): bool
    {
        return count($c->userPids) + count($c->userPickIds) > CandidateBudget::MAX_ASSETS_PER_SIDE
            || count($c->partnerPids) + count($c->partnerPickIds) > CandidateBudget::MAX_ASSETS_PER_SIDE;
    }
}
