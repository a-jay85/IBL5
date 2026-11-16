<?php

declare(strict_types=1);

namespace TradeSuggestions\Contracts;

use TradeSuggestions\TradeSuggestionsPanelData;

interface TradeSuggestionsServiceInterface
{
    /**
     * Build the trade-ideas panel for one franchise.
     *
     * Returns null while trading is closed (the panel does not render).
     */
    public function buildForTeam(int $tid): ?TradeSuggestionsPanelData;
}
