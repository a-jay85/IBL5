<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

/**
 * Contract for free agency module controller operations
 *
 * Defines the main entry point for free agency request handling.
 * Coordinates authentication, CSRF validation, and routing to
 * display, negotiate, offer, and delete operations.
 */
interface FreeAgencyControllerInterface
{
    /**
     * Main entry point for free agency operations
     *
     * Handles authentication and routes to the appropriate action:
     * - display: Main free agency page with roster, offers, and free agents
     * - negotiate: Offer negotiation form for a specific player
     * - processoffer: POST handler for submitting an offer (CSRF-protected)
     * - deleteoffer: POST handler for deleting an offer (CSRF-protected)
     *
     * @param mixed $user Current user object (from PhpNuke authentication)
     * @param string $action Action to perform
     * @param int $pid Player ID (used for negotiate action)
     * @return void Renders appropriate view or redirects on POST
     */
    public function handleRequest(mixed $user, string $action, int $pid): void;
}
