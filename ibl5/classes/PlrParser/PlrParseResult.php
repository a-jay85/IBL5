<?php

declare(strict_types=1);

namespace PlrParser;

/**
 * Value object for PLR file parse operation results.
 */
class PlrParseResult
{
    public int $playersUpserted = 0;

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
        if ($this->playersUpserted > 0) {
            return $this->playersUpserted . ' players upserted';
        }
        return 'No changes';
    }
}
