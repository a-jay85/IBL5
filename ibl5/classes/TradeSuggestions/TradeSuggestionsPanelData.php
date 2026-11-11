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
     */
    public function __construct(
        public ResolvedTeamMode $mode,
        public array $items,
        public ?SuggestionDiagnostics $diagnostics = null,
    ) {
    }
}
