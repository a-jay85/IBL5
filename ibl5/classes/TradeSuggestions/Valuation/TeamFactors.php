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
     * @param list<TeamFactors> $teams
     */
    public static function average(array $teams): self
    {
        if ($teams === []) {
            return new self(0, 0, 0, 0, 0);
        }

        $mean = static fn (callable $field): int => (int) round(array_sum(array_map($field, $teams)) / count($teams));

        return new self(
            0,
            $mean(static fn (self $t): int => $t->wins),
            $mean(static fn (self $t): int => $t->losses),
            $mean(static fn (self $t): int => $t->tradWins),
            $mean(static fn (self $t): int => $t->tradLosses),
        );
    }
}
