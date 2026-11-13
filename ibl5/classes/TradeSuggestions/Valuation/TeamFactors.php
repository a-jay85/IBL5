<?php

declare(strict_types=1);

namespace TradeSuggestions\Valuation;

/**
 * One team's `ibl_team_info.contract_*` win/loss factors.
 */
final readonly class TeamFactors
{
    public function __construct(
        public int $tid,
        public int $wins,
        public int $losses,
        public int $tradWins,
        public int $tradLosses,
    ) {
    }

    /**
     * The league-average rival: tid 0, each field the rounded mean. An empty
     * list gives all zeros.
     *
     * @param array<TeamFactors> $teams
     */
    public static function average(array $teams): self
    {
        if ($teams === []) {
            return new self(0, 0, 0, 0, 0);
        }

        return new self(
            0,
            self::mean($teams, static fn (self $t): int => $t->wins),
            self::mean($teams, static fn (self $t): int => $t->losses),
            self::mean($teams, static fn (self $t): int => $t->tradWins),
            self::mean($teams, static fn (self $t): int => $t->tradLosses),
        );
    }

    /**
     * @param non-empty-array<TeamFactors> $teams
     * @param \Closure(TeamFactors): int $field
     */
    private static function mean(array $teams, \Closure $field): int
    {
        return (int) round(array_sum(array_map($field, $teams)) / count($teams));
    }
}
