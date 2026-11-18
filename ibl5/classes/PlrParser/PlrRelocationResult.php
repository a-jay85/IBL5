<?php

declare(strict_types=1);

namespace PlrParser;

/**
 * Outcome of moving traded .plr records into their new team's slot block.
 */
final readonly class PlrRelocationResult
{
    /**
     * @param array<int, string> $lines The .plr lines after relocation
     * @param list<array{pid: int, name: string, oldOrdinal: int, newOrdinal: int, oldTid: int, newTid: int}> $moves
     * @param list<string> $warnings
     */
    public function __construct(
        public array $lines,
        public array $moves,
        public array $warnings,
    ) {
    }
}
