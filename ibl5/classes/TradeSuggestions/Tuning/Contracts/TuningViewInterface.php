<?php

declare(strict_types=1);

namespace TradeSuggestions\Tuning\Contracts;

use TradeSuggestions\Tuning\TuningReport;

/**
 * Renders the read-only tuning page body.
 */
interface TuningViewInterface
{
    /**
     * Every dynamic string in the output is escaped.
     */
    public function render(TuningReport $report): string;
}
