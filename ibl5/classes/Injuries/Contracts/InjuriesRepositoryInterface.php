<?php

declare(strict_types=1);

namespace Injuries\Contracts;

/**
 * Repository interface for retrieving injured-player rows.
 *
 * Wraps the league-wide injured-players query so the Injuries service can be
 * unit-tested with an injected double instead of a live database connection.
 *
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 */
interface InjuriesRepositoryInterface
{
    /**
     * Get all currently injured, non-retired players ordered by ordinal.
     *
     * @return list<PlayerRow> Injured player rows
     */
    public function getInjuredPlayers(): array;
}
