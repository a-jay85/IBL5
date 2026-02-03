<?php

declare(strict_types=1);

namespace Waivers\Contracts;

/**
 * WaiversControllerInterface - Contract for waiver wire controller operations
 * 
 * Defines the main entry points and workflow orchestration for waiver wire
 * transactions. This controller coordinates validation, processing, and
 * view rendering for adding and dropping players from the waiver wire.
 * 
 * @package Waivers\Contracts
 */
interface WaiversControllerInterface
{
    /**
     * Main entry point for waiver operations
     * 
     * Handles authentication, season phase validation, and routes to appropriate
     * waiver operation. Should check if waivers are currently open before proceeding.
     * 
     * @param mixed $user Current user object (from PhpNuke authentication)
     * @param string $action Action to perform ('add' or 'drop')
     * @return void Renders appropriate view based on user state and action
     * 
     * **Behaviors:**
     * - Renders login form if user is not authenticated
     * - Renders "waivers closed" message if season phase doesn't allow waivers
     * - Delegates to executeWaiverOperation() for authenticated users
     */
    public function handleWaiverRequest($user, string $action): void;

    /**
     * Executes waiver wire operations (add or drop)
     *
     * Uses PRG (Post-Redirect-Get) pattern: POST submissions are processed
     * and redirected with result/error query parameters. GET requests display
     * the form with optional result banners.
     *
     * @param string $username Username of the logged-in user
     * @param string $action Action to perform ('add' or 'drop')
     * @return void On POST: redirects with result/error params. On GET: renders waiver form.
     *
     * **Behaviors:**
     * - Looks up user's team information
     * - On POST: processes submission, redirects to GET with result/error query param
     * - On GET: displays the waiver form with current roster/waiver pool data
     * - Shows success or error banners from redirected result/error params
     */
    public function executeWaiverOperation(string $username, string $action): void;
}
