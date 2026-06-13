<?php

declare(strict_types=1);

namespace Watchlist\Contracts;

/**
 * WatchlistControllerInterface - Contract for the watchlist module controller.
 *
 * Coordinates authentication, CSRF validation, PRG dispatch, and view rendering
 * for the "My Watchlist" page and the watch/unwatch/save-note POST operations.
 */
interface WatchlistControllerInterface
{
    /**
     * Main entry point for the Watchlist module.
     *
     * - Renders the login box when the user is not authenticated.
     * - On POST (op = toggle | savenote | remove): validates the CSRF token,
     *   dispatches to the service using the resolved username, and PRG-redirects.
     * - On GET: renders the My Watchlist page with optional result/error banners.
     *
     * @param mixed $user Current user object (from PhpNuke authentication).
     * @param string $op Requested operation ('toggle', 'savenote', 'remove', or '').
     */
    public function handleRequest($user, string $op): void;
}
