<?php

declare(strict_types=1);

namespace PlrParser\Contracts;

/**
 * Repository interface for PLR file database operations.
 */
interface PlrParserRepositoryInterface
{
    /**
     * Upsert a player record into ibl_plr.
     *
     * @param array<string, int|string|float> $data Column-value pairs for the player
     * @return int Number of affected rows
     */
    public function upsertPlayer(array $data): int;

    /**
     * Upsert a player rating snapshot into ibl_plr_snapshots.
     *
     * @param array<string, int|string> $data Column-value pairs for the snapshot
     * @return int Number of affected rows
     */
    public function upsertSnapshot(array $data): int;
}
