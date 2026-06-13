<?php

declare(strict_types=1);

namespace Watchlist;

use League\League;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Watchlist\Contracts\WatchlistRepositoryInterface;
use Watchlist\Contracts\WatchlistServiceInterface;

/**
 * @see WatchlistServiceInterface
 *
 * @phpstan-import-type WatchlistRow from \Watchlist\Contracts\WatchlistRepositoryInterface
 */
class WatchlistService implements WatchlistServiceInterface
{
    /**
     * Defensive cap on stored note length.
     */
    private const MAX_NOTE_LENGTH = 2000;

    private TeamIdentityRepositoryInterface $teamIdentityRepo;
    private WatchlistRepositoryInterface $repo;

    public function __construct(
        TeamIdentityRepositoryInterface $teamIdentityRepo,
        WatchlistRepositoryInterface $repo
    ) {
        $this->teamIdentityRepo = $teamIdentityRepo;
        $this->repo = $repo;
    }

    /**
     * @see WatchlistServiceInterface::resolveOwnerTeamid()
     */
    public function resolveOwnerTeamid(string $username): ?int
    {
        $teamName = $this->teamIdentityRepo->getTeamnameFromUsername($username);

        // getTeamnameFromUsername returns FREE_AGENTS_TEAM_NAME for an empty/unknown
        // username, and null when the lookup finds no team. Either way the account
        // owns no franchise — guard on the NAME (not the resulting tid=0).
        if ($teamName === null || $teamName === League::FREE_AGENTS_TEAM_NAME) {
            return null;
        }

        return $this->teamIdentityRepo->getTidFromTeamname($teamName);
    }

    /**
     * @see WatchlistServiceInterface::getWatchlistView()
     *
     * @return list<WatchlistRow>
     */
    public function getWatchlistView(string $username): array
    {
        $teamid = $this->resolveOwnerTeamid($username);

        if ($teamid === null) {
            return [];
        }

        return $this->repo->getWatchlistForTeam($teamid);
    }

    /**
     * @see WatchlistServiceInterface::toggleWatch()
     *
     * @return array{success: bool, result?: string, error?: string}
     */
    public function toggleWatch(string $username, int $pid): array
    {
        $teamid = $this->resolveOwnerTeamid($username);

        if ($teamid === null) {
            return ['success' => false, 'error' => 'no_team'];
        }

        if ($this->repo->isWatched($teamid, $pid)) {
            $this->repo->removeWatch($teamid, $pid);
            return ['success' => true, 'result' => 'unwatched'];
        }

        $this->repo->addWatch($teamid, $pid);
        return ['success' => true, 'result' => 'watched'];
    }

    /**
     * @see WatchlistServiceInterface::saveNote()
     *
     * @return array{success: bool, result?: string, error?: string}
     */
    public function saveNote(string $username, int $pid, string $note): array
    {
        $teamid = $this->resolveOwnerTeamid($username);

        if ($teamid === null) {
            return ['success' => false, 'error' => 'no_team'];
        }

        $note = mb_substr($note, 0, self::MAX_NOTE_LENGTH);
        // The UPDATE's WHERE teamid = ? scoping means a note write for a row this
        // team does not own affects 0 rows — no foreign-row mutation is possible.
        $this->repo->saveNote($teamid, $pid, $note);

        return ['success' => true, 'result' => 'note_saved'];
    }

    /**
     * @see WatchlistServiceInterface::removeWatch()
     *
     * @return array{success: bool, result?: string, error?: string}
     */
    public function removeWatch(string $username, int $pid): array
    {
        $teamid = $this->resolveOwnerTeamid($username);

        if ($teamid === null) {
            return ['success' => false, 'error' => 'no_team'];
        }

        $this->repo->removeWatch($teamid, $pid);

        return ['success' => true, 'result' => 'unwatched'];
    }

    /**
     * @see WatchlistServiceInterface::isWatchedByUser()
     */
    public function isWatchedByUser(string $username, int $pid): bool
    {
        $teamid = $this->resolveOwnerTeamid($username);

        if ($teamid === null) {
            return false;
        }

        return $this->repo->isWatched($teamid, $pid);
    }
}
