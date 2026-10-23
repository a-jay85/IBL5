<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * A deal both sides gain from, by their own top need. Gains are on a 0 to 100
 * scale. requiredTier and paidTier are the sweetener the receiving side of a
 * salary dump asked for and got (both 0 for other pairings). Payrolls are
 * effective-year payroll after the deal, in thousands.
 */
final readonly class Judgment
{
    public function __construct(
        public int $userGain,
        public int $partnerGain,
        public int $requiredTier,
        public int $paidTier,
        public int $userPayrollAfter,
        public int $partnerPayrollAfter,
    ) {
    }
}
