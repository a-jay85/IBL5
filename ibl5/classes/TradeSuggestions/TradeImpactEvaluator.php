<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Both-sides-benefit filter. Pure.
 *
 * Scores each team before and after the deal by its own mode and keeps the deal
 * only when both weighted gains reach MIN_GAIN, which rejects fair-value-only swaps.
 */
final class TradeImpactEvaluator
{
    public const float MIN_GAIN = 1.0;

    public function __construct(
        private readonly TeamNeedsScorer $scorer = new TeamNeedsScorer(),
        private readonly ReasonBuilder $reasons = new ReasonBuilder(),
    ) {
    }

    public function evaluate(
        TradeCandidate $c,
        TeamAssets $user,
        TeamAssets $partner,
        TeamMode $userMode,
        TeamMode $partnerMode,
    ): ?ScoredCandidate {
        $userAfter = $user->afterTrade(
            $c->userPids,
            $c->userPickIds,
            self::players($partner, $c->partnerPids),
            self::picks($partner, $c->partnerPickIds),
        );
        $partnerAfter = $partner->afterTrade(
            $c->partnerPids,
            $c->partnerPickIds,
            self::players($user, $c->userPids),
            self::picks($user, $c->userPickIds),
        );

        $userBeforeScores = $this->scorer->score($user);
        $userAfterScores = $this->scorer->score($userAfter);
        $partnerBeforeScores = $this->scorer->score($partner);
        $partnerAfterScores = $this->scorer->score($partnerAfter);

        $userGain = $userAfterScores->weighted($userMode) - $userBeforeScores->weighted($userMode);
        $partnerGain = $partnerAfterScores->weighted($partnerMode) - $partnerBeforeScores->weighted($partnerMode);

        if (!($userGain >= self::MIN_GAIN && $partnerGain >= self::MIN_GAIN)) {
            return null;
        }

        return new ScoredCandidate(
            $c,
            $userGain,
            $partnerGain,
            $this->reasons->build($user, $userAfter, $userBeforeScores, $userAfterScores, $userMode),
            $this->reasons->build($partner, $partnerAfter, $partnerBeforeScores, $partnerAfterScores, $partnerMode),
        );
    }

    /**
     * @param list<int> $pids
     * @return list<SuggestionPlayer>
     */
    private static function players(TeamAssets $assets, array $pids): array
    {
        return array_values(array_filter(
            $assets->players,
            static fn (SuggestionPlayer $p): bool => in_array($p->pid, $pids, true)
        ));
    }

    /**
     * @param list<int> $pickIds
     * @return list<SuggestionPick>
     */
    private static function picks(TeamAssets $assets, array $pickIds): array
    {
        return array_values(array_filter(
            $assets->picks,
            static fn (SuggestionPick $p): bool => in_array($p->pickId, $pickIds, true)
        ));
    }
}
