<?php

declare(strict_types=1);

namespace JsbParser;

/**
 * Value object for JSB import operation results.
 */
class JsbImportResult
{
    public int $inserted = 0;
    public int $updated = 0;
    public int $skipped = 0;
    public int $errors = 0;

    /** @var list<string> */
    public array $messages = [];

    public function addInserted(int $count = 1): void
    {
        $this->inserted += $count;
    }

    public function addUpdated(int $count = 1): void
    {
        $this->updated += $count;
    }

    public function addSkipped(int $count = 1): void
    {
        $this->skipped += $count;
    }

    public function addError(string $message): void
    {
        $this->errors++;
        $this->messages[] = 'ERROR: ' . $message;
    }

    public function addMessage(string $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * Merge another result into this one.
     */
    public function merge(self $other): void
    {
        $this->inserted += $other->inserted;
        $this->updated += $other->updated;
        $this->skipped += $other->skipped;
        $this->errors += $other->errors;
        $this->messages = array_merge($this->messages, $other->messages);
    }

    /**
     * Get a one-line summary string.
     */
    public function summary(): string
    {
        $parts = [];
        if ($this->inserted > 0) {
            $parts[] = $this->inserted . ' inserted';
        }
        if ($this->updated > 0) {
            $parts[] = $this->updated . ' updated';
        }
        if ($this->skipped > 0) {
            $parts[] = $this->skipped . ' skipped';
        }
        if ($this->errors > 0) {
            $parts[] = $this->errors . ' errors';
        }
        if ($parts === []) {
            return 'No changes';
        }
        return implode(', ', $parts);
    }
}
