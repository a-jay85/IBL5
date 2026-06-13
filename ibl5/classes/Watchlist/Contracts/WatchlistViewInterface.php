<?php

declare(strict_types=1);

namespace Watchlist\Contracts;

/**
 * WatchlistViewInterface - Contract for watchlist HTML rendering.
 *
 * All dynamic values (player names, free-text notes, stats) are escaped through
 * HtmlSanitizer::e() on output. Notes are stored raw and escaped here.
 *
 * @phpstan-import-type WatchlistRow from WatchlistRepositoryInterface
 */
interface WatchlistViewInterface
{
    /**
     * Render the "My Watchlist" page: a table of watched players with notes,
     * quick stats, and edit-note / remove actions. Renders an info notice for an
     * empty list and a "you must own a team" notice when $hasTeam is false.
     *
     * @param list<WatchlistRow> $rows
     * @param string $rawToken Shared raw CSRF token rendered as a hidden input per form.
     */
    public function renderWatchlistPage(
        array $rows,
        ?string $result,
        ?string $error,
        string $rawToken,
        bool $hasTeam = true
    ): string;

    /**
     * Render the Watch / Unwatch toggle form for the Player page.
     *
     * @param string $rawToken Shared raw CSRF token rendered as a hidden input.
     */
    public function renderToggleButton(int $pid, bool $isWatched, string $rawToken): string;
}
