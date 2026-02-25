<?php

declare(strict_types=1);

namespace JsbParser;

/**
 * Value object for PLR/TRN export operation results.
 *
 * Tracks which players were modified, what fields changed, and any errors.
 * Provides a change audit log with old/new values for verification.
 */
class PlrWriteResult
{
    public int $playersModified = 0;
    public int $fieldsChanged = 0;
    public int $errors = 0;

    /**
     * Per-player change details for audit.
     *
     * @var list<array{pid: int, name: string, changes: list<array{field: string, old: int, new: int}>}>
     */
    public array $changeLog = [];

    /** @var list<string> */
    public array $messages = [];

    /**
     * Record a set of field changes for one player.
     *
     * @param int $pid Player ID
     * @param string $name Player name
     * @param list<array{field: string, old: int, new: int}> $changes Field changes
     */
    public function addPlayerChanges(int $pid, string $name, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $this->playersModified++;
        $this->fieldsChanged += count($changes);
        $this->changeLog[] = [
            'pid' => $pid,
            'name' => $name,
            'changes' => $changes,
        ];
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
     * Get a one-line summary string.
     */
    public function summary(): string
    {
        $parts = [];
        if ($this->playersModified > 0) {
            $parts[] = $this->playersModified . ' players modified';
        }
        if ($this->fieldsChanged > 0) {
            $parts[] = $this->fieldsChanged . ' fields changed';
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
