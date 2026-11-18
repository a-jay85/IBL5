<?php

declare(strict_types=1);

namespace TradeSuggestions\Tuning;

/**
 * Everything the tuning page shows for one request.
 */
final readonly class TuningReport
{
    /**
     * @param array<int, string> $teamNames every real franchise, for the team dropdown
     * @param array<string, mixed> $config the live engine constants
     * @param list<TuningTeamSection> $sections
     * @param list<int> $teamsNotRun tids skipped once the time budget ran out
     */
    public function __construct(
        public string $simKey,
        public TuningFilter $filter,
        public array $teamNames,
        public array $config,
        public array $sections,
        public bool $truncated,
        public array $teamsNotRun,
        public float $elapsedSeconds,
    ) {
    }
}
