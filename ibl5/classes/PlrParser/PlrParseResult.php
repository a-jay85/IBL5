<?php

declare(strict_types=1);

namespace PlrParser;

/**
 * Value object for PLR file parse operation results.
 */
class PlrParseResult
{
    public int $playersUpserted = 0;
    public int $historyRowsUpserted = 0;
    public int $teamsAssigned = 0;

    /** @var list<string> */
    public array $messages = [];

    public function addMessage(string $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * Get a one-line summary string.
     */
    public function summary(): string
    {
        $parts = [];
        if ($this->playersUpserted > 0) {
            $parts[] = $this->playersUpserted . ' players upserted';
        }
        if ($this->historyRowsUpserted > 0) {
            $parts[] = $this->historyRowsUpserted . ' history rows upserted';
        }
        if ($this->teamsAssigned > 0) {
            $parts[] = $this->teamsAssigned . ' teams assigned';
        }
        if ($parts === []) {
            return 'No changes';
        }
        return implode(', ', $parts);
    }
}
