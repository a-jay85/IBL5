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
     * Create a JsbImportResult from a BoxscoreProcessor::processScoFile() result array.
     *
     * @param array{success: bool, gamesInserted: int, gamesUpdated: int, gamesSkipped: int, linesProcessed: int, messages: list<string>, error?: string} $scoResult
     */
    public static function fromScoResult(array $scoResult): self
    {
        $result = new self();
        $result->inserted = $scoResult['gamesInserted'];
        $result->updated = $scoResult['gamesUpdated'];
        $result->skipped = $scoResult['gamesSkipped'];
        if (isset($scoResult['error'])) {
            $result->addError($scoResult['error']);
        }
        foreach ($scoResult['messages'] as $msg) {
            $result->addMessage($msg);
        }
        return $result;
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
