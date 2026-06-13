<?php

declare(strict_types=1);

namespace TradeBlock\Contracts;

/**
 * TradeBlockControllerInterface - Entry point + op routing for the trade block.
 */
interface TradeBlockControllerInterface
{
    /**
     * Route a request to the browse board or the owner's edit form.
     *
     * @param mixed $user Current user object (from PHP-Nuke authentication)
     * @param string $op 'browse' (default, public board) or 'edit' (owner form)
     *
     * **Behaviors:**
     * - Renders login form if user is not authenticated
     * - On POST (Action=save): validates CSRF, resolves the owner team server-side,
     *   reconciles the submitted set against the roster, then PRG-redirects
     * - On GET op=edit: renders the owner's bulk edit form
     * - On GET op=browse (default): renders the league-wide read-only board
     */
    public function handleRequest($user, string $op): void;
}
