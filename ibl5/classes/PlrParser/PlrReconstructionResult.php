<?php

declare(strict_types=1);

namespace PlrParser;

/**
 * Result of a .plr reconstruction run.
 *
 * Tracks counts, per-player change summaries, and any warnings/errors that did not
 * abort the run (e.g., a player in the base .plr without a corresponding .car row).
 */
class PlrReconstructionResult
{
    public int $playersUpdated = 0;
    public int $playersUnchanged = 0;
    public int $playersMissingFromCar = 0;
    public int $bytesWritten = 0;

    /** @var list<string> */
    public array $messages = [];

    /** @var list<string> */
    public array $warnings = [];

    /** @var list<string> */
    public array $errors = [];

    public function addMessage(string $message): void
    {
        $this->messages[] = $message;
    }

    public function addWarning(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
