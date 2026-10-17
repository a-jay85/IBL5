<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * One suggestion as the panel shows it. All strings are raw; the view escapes them.
 */
final readonly class SuggestionItem
{
    /**
     * @param list<string> $giveLabels What the GM's team sends (player names or pick text)
     * @param list<string> $getLabels What the GM's team receives
     */
    public function __construct(
        public string $partnerTeamName,
        public array $giveLabels,
        public array $getLabels,
        public string $userReason,
        public string $partnerReason,
        public string $prefillUrl,
    ) {
    }
}
