<?php

declare(strict_types=1);

namespace TradeSuggestions\Valuation;

use League\ContractRules;

/**
 * F10 re-sign likelihood: the modifier edge of a player's own team over a
 * league-average rival, turned into a probability. Pure.
 *
 * - `SECURITY_BASE_FACTOR` and `SECURITY_YEAR_FACTOR` copy the private constants
 *   in `FreeAgencyMarketDemandCalculator`. A reflection test pins them equal.
 * - `BIRD_EXTRA_YEARS`: the team holding Bird rights is modelled as able to offer
 *   one more season than the rival. One year is the smallest length advantage,
 *   and without Bird rights both offers match and the security term cancels.
 * - `BIRD_RAISE_EDGE`: the 12.5% Bird raise over the 10% standard raise.
 * - `SCALE`: the loyalty edge of a preference-4 player (own +0.075, rival -0.075).
 *   At that edge alone the likelihood is `0.5 + 0.5 * tanh(1)` = 0.88. One strong
 *   preference decides most of the answer and three weak ones together do the
 *   same. It is not fitted to any fixture.
 * - `MIN` and `MAX` keep every answer short of certain.
 * - `WALK_LIKELIHOOD`, `WALK_MAX_SEASONS_LEFT` and `TOP_TIER` define the F10
 *   "likely to walk" star.
 */
final class ResignLikelihood
{
    public const float SECURITY_BASE_FACTOR = -0.025;
    public const float SECURITY_YEAR_FACTOR = 0.01;
    public const int STANDARD_YEARS = 4;
    public const int BIRD_EXTRA_YEARS = 1;
    public const float BIRD_RAISE_EDGE = ContractRules::BIRD_RIGHTS_RAISE_PERCENTAGE - ContractRules::STANDARD_RAISE_PERCENTAGE;
    public const float SCALE = 0.15;
    public const float MIN = 0.05;
    public const float MAX = 0.95;
    public const float WALK_LIKELIHOOD = 0.4;
    public const int WALK_MAX_SEASONS_LEFT = 2;
    public const int TOP_TIER = 1;

    /**
     * `$ownPositionMoney` is the offset-0 salary committed at the player's slot
     * on his own team, excluding him. `$rivalPositionMoney` is the league mean
     * of that figure.
     *
     * @param array<string, int> $prefs loyalty, winner, tradition, security, playing_time (1-5)
     */
    public static function edge(
        array $prefs,
        int $bird,
        TeamFactors $own,
        TeamFactors $rival,
        int $ownPositionMoney,
        int $rivalPositionMoney,
    ): float {
        $winner = $prefs['winner'];
        $tradition = $prefs['tradition'];
        $loyalty = $prefs['loyalty'];
        $playingTime = $prefs['playing_time'];

        $edge = ContractRules::calculateWinnerModifier($own->wins, $own->losses, $winner)
            - ContractRules::calculateWinnerModifier($rival->wins, $rival->losses, $winner)
            + ContractRules::calculateTraditionModifier($own->tradWins, $own->tradLosses, $tradition)
            - ContractRules::calculateTraditionModifier($rival->tradWins, $rival->tradLosses, $tradition)
            + ContractRules::calculateLoyaltyModifier($loyalty, true)
            - ContractRules::calculateLoyaltyModifier($loyalty, false)
            + ContractRules::calculatePlayingTimeModifier($ownPositionMoney, $playingTime)
            - ContractRules::calculatePlayingTimeModifier($rivalPositionMoney, $playingTime);

        if (!ContractRules::hasBirdRights($bird)) {
            return $edge;
        }

        return $edge
            + self::securityTerm(self::STANDARD_YEARS + self::BIRD_EXTRA_YEARS, $prefs['security'])
            - self::securityTerm(self::STANDARD_YEARS, $prefs['security'])
            + self::BIRD_RAISE_EDGE;
    }

    /**
     * The arithmetic of `FreeAgencyMarketDemandCalculator::calculatePerceivedValue`.
     */
    public static function securityTerm(int $years, int $security): float
    {
        return (self::SECURITY_YEAR_FACTOR * ($years - 1) + self::SECURITY_BASE_FACTOR) * ($security - 1);
    }

    public static function likelihood(float $edge): float
    {
        return max(self::MIN, min(self::MAX, 0.5 + 0.5 * tanh($edge / self::SCALE)));
    }

    /**
     * @param int $slotTier `FairSalaryTable::tierFor` of the player's offset-0 pool rank
     * @param int $seasonsLeft years from `effectiveCy` with salary above 0
     */
    public static function isLikelyToWalk(float $likelihood, int $slotTier, int $seasonsLeft): bool
    {
        return $slotTier === self::TOP_TIER
            && $likelihood < self::WALK_LIKELIHOOD
            && $seasonsLeft >= 1
            && $seasonsLeft <= self::WALK_MAX_SEASONS_LEFT;
    }

    /**
     * Multiplies the player's future-season lineup and profile contribution in
     * unit 3. It is a probability on a contribution, never a player score.
     */
    public static function futureValueFactor(int $offset, bool $onRosterAtOffset, bool $likelyToWalk, float $likelihood): float
    {
        if ($offset < 1) {
            return 1.0;
        }
        if (!$onRosterAtOffset) {
            return 0.0;
        }

        return $likelyToWalk ? $likelihood : 1.0;
    }
}
