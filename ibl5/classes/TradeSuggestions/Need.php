<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * One need a team has. severity is in the unit NeedKind documents (thousands
 * of salary, rank places, or players). slot is set for slot needs; pids names
 * the players the need is about.
 */
final readonly class Need
{
    /**
     * @param list<int> $pids
     */
    public function __construct(
        public NeedKind $kind,
        public int $severity,
        public ?string $slot = null,
        public array $pids = [],
    ) {
    }
}
