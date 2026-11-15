<?php

declare(strict_types=1);

namespace TradeSuggestions\Contracts;

use TradeSuggestions\TradeSuggestionsPanelData;

interface TradeSuggestionsViewInterface
{
    /**
     * @param bool $modeError Show the fixed "could not change mode" notice
     * @param string $feedbackError Raw `trade_suggestion_error` code; unknown codes render nothing
     * @param array{deal_key: string, reason_codes: string, free_text: ?string, created_at: string}|null $hiddenNote
     */
    public function render(TradeSuggestionsPanelData $d, bool $modeError = false, string $feedbackError = '', ?array $hiddenNote = null): string;
}
