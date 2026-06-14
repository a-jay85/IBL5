<?php

declare(strict_types=1);

namespace Watchlist\Contracts;

/**
 * WatchlistServiceInterface - Contract for watchlist orchestration.
 *
 * The service is the single server-side owner-resolution point: every public
 * method takes the logged-in username and resolves it to an owning teamid via
 * the team-identity chain. No request parameter ever supplies the owner.
 *
 * @phpstan-import-type WatchlistRow from WatchlistRepositoryInterface
 */
interface WatchlistServiceInterface
{
    /**
     * Resolve the owning teamid for a username, or null when the account owns no
     * team (free agent / commissioner). This is the single owner-resolution point.
     */
    public function resolveOwnerTeamid(string $username): ?int;

    /**
     * The watched players for the user's team (empty when the user owns no team).
     *
     * @return list<WatchlistRow>
     */
    public function getWatchlistView(string $username): array;

    /**
     * Toggle watch state for a player: add when unwatched, remove when watched.
     *
     * @return array{success: bool, result?: string, error?: string}
     */
    public function toggleWatch(string $username, int $pid): array;

    /**
     * Save a note against an already-watched player for the user's team.
     *
     * @return array{success: bool, result?: string, error?: string}
     */
    public function saveNote(string $username, int $pid, string $note): array;

    /**
     * Remove a player from the user's watchlist.
     *
     * @return array{success: bool, result?: string, error?: string}
     */
    public function removeWatch(string $username, int $pid): array;

    /**
     * Whether the user's team is watching the given player. Used to render the
     * Player-page toggle. False when the user owns no team.
     */
    public function isWatchedByUser(string $username, int $pid): bool;
}
