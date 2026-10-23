<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Every need one team has, sorted by priority then severity descending.
 *
 * futurePayroll is keyed by season offset (1 = next season, 2 = the one after).
 */
final readonly class NeedsCard
{
    /**
     * @param list<Need> $needs
     * @param array<int, int> $futurePayroll
     */
    public function __construct(
        public int $tid,
        public array $needs,
        public int $payrollNow,
        public array $futurePayroll,
        public TeamMode $mode,
        public float $recordPct,
    ) {
    }

    /**
     * The most pressing need. FutureCapStrain only shapes deals, so it is
     * never the top need.
     */
    public function topNeed(): ?Need
    {
        foreach ($this->needs as $need) {
            if ($need->kind !== NeedKind::FutureCapStrain) {
                return $need;
            }
        }

        return null;
    }

    public function has(NeedKind $kind): bool
    {
        foreach ($this->needs as $need) {
            if ($need->kind === $kind) {
                return true;
            }
        }

        return false;
    }
}
