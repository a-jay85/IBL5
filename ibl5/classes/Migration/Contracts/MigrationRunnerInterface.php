<?php

declare(strict_types=1);

namespace Migration\Contracts;

/**
 * MigrationRunnerInterface - Contract for executing pending migrations
 *
 * Orchestrates discovering, running, and recording database migrations.
 */
interface MigrationRunnerInterface
{
    /**
     * Run all pending migrations.
     *
     * @return list<string> Filenames of successfully executed migrations
     * @throws \RuntimeException If a migration fails (halts on first failure)
     */
    public function runPending(): array;

    /**
     * Get list of pending (not yet applied) migration filenames.
     *
     * @return list<string> Filenames awaiting execution
     */
    public function getPending(): array;

    /**
     * Plan the undo of migrations whose files vanished from the checkout.
     *
     * A reverted migration leaves an applied row whose file is gone. Returns an
     * empty list when no applied row with stored down SQL has a missing file.
     *
     * @return list<array{id: int, migration: string}> Rows to undo, newest first
     * @throws \RuntimeException When the fail-closed guard refuses to auto-revert
     */
    public function getVanishedUndoPlan(): array;

    /**
     * Undo vanished migrations from the down SQL stored in the database.
     *
     * Reads no down file. Refuses before running anything when any stored text
     * is missing or is not DML-only.
     *
     * @return list<string> Names of the undone migrations, newest first
     * @throws \RuntimeException When the guard or a check refuses, or an undo fails
     */
    public function undoVanished(): array;
}
