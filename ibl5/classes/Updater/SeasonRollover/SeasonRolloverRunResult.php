<?php

declare(strict_types=1);

namespace Updater\SeasonRollover;

final class SeasonRolloverRunResult
{
    public function __construct(
        public readonly SeasonRolloverDecisionResult $decision,
        public readonly ?int $cashRowsAdvanced,
    ) {
    }

    /** True when the season settings were written this run. */
    public function rolledOver(): bool
    {
        return $this->cashRowsAdvanced !== null;
    }
}
