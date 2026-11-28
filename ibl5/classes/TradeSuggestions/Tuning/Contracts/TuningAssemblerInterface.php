<?php

declare(strict_types=1);

namespace TradeSuggestions\Tuning\Contracts;

use TradeSuggestions\Tuning\TuningFilter;
use TradeSuggestions\Tuning\TuningReport;

/**
 * Runs the trade-suggestion engine in diagnostics mode for the tuning page.
 */
interface TuningAssemblerInterface
{
    /**
     * Reads the cached snapshot once and runs the engine for each team the
     * filter names, within `TuningBudget`.
     */
    public function build(TuningFilter $f): TuningReport;
}
