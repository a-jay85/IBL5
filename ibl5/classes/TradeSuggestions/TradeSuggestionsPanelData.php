<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Everything the homepage trade-ideas panel renders. An empty $items list is a
 * valid result: the view shows the empty state for it.
 */
final readonly class TradeSuggestionsPanelData
{
    /**
     * @param list<SuggestionItem> $items At most three suggestions
     * @param int $eligibleCount Ideas the refresh button pages through
     * @param int $offset Where the shown window starts in that list
     */
    public function __construct(
        public ResolvedTeamMode $mode,
        public array $items,
        public ?SuggestionDiagnostics $diagnostics = null,
        public int $eligibleCount = 0,
        public int $offset = 0,
    ) {
    }
}
