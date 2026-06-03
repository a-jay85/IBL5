<?php

declare(strict_types=1);

namespace Injuries;

use Injuries\Contracts\InjuriesRepositoryInterface;
use League\League;

/**
 * League-backed implementation of {@see InjuriesRepositoryInterface}.
 *
 * Delegates to {@see League::getInjuredPlayersResult()} so the underlying query
 * stays in one place while {@see InjuriesService} depends only on the interface.
 *
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 */
class InjuriesRepository implements InjuriesRepositoryInterface
{
    private League $league;

    public function __construct(\mysqli $db, ?League $league = null)
    {
        $this->league = $league ?? new League($db);
    }

    /**
     * @see InjuriesRepositoryInterface::getInjuredPlayers()
     *
     * @return list<PlayerRow>
     */
    public function getInjuredPlayers(): array
    {
        return $this->league->getInjuredPlayersResult();
    }
}
