<?php

declare(strict_types=1);

namespace HistArchiver;

/**
 * Value object representing the outcome of a season archive operation.
 */
final class HistArchiveResult
{
    /**
     * @param list<string> $messages
     */
    public function __construct(
        public readonly int $rowsUpserted,
        public readonly int $playersArchived,
        public readonly array $messages,
        public readonly bool $skippedNoChampion,
    ) {
    }

    public static function skipped(): self
    {
        return new self(
            rowsUpserted: 0,
            playersArchived: 0,
            messages: [],
            skippedNoChampion: true,
        );
    }

    /**
     * @param list<string> $messages
     */
    public static function completed(int $rowsUpserted, int $playersArchived, array $messages): self
    {
        return new self(
            rowsUpserted: $rowsUpserted,
            playersArchived: $playersArchived,
            messages: $messages,
            skippedNoChampion: false,
        );
    }
}
