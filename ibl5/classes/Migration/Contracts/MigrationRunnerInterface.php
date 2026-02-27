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
}
