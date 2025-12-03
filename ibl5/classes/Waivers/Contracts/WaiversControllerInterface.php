<?php

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
     * Main workflow method that processes waiver submissions and displays the form.
     * Handles both initial form display and form submission processing.
     * 
     * @param string $username Username of the logged-in user
     * @param string $action Action to perform ('add' or 'drop')
     * @return void Renders waiver form with appropriate data or error messages
     * 
     * **Behaviors:**
     * - Looks up user's team information
     * - Processes POST data if a submission was made
     * - Displays the waiver form with current roster/waiver pool data
     * - Shows success or error messages from previous submission
     */
    public function executeWaiverOperation(string $username, string $action): void;
}
