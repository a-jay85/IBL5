<?php

declare(strict_types=1);

namespace TradeSuggestions\Contracts;

use TradeSuggestions\TradeSuggestionsPanelData;

interface TradeSuggestionsViewInterface
{
    /**
     * @param bool $modeError Show the fixed "could not change mode" notice
     */
    public function render(TradeSuggestionsPanelData $d, bool $modeError = false): string;
}
