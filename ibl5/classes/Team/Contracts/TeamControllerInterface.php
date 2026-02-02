<?php

declare(strict_types=1);

namespace Team\Contracts;

/**
 * TeamControllerInterface - Main controller contract for Team module
 *
 * Thin orchestrator that delegates data assembly to TeamService
 * and HTML rendering to TeamView, following the Controller -> Service -> View pattern.
 */
interface TeamControllerInterface
{
    /**
     * Display team page with roster and statistics
     *
     * Parses request parameters, calls Header, delegates to TeamService
     * for data assembly and TeamView for rendering, then calls Footer.
     *
     * **Context Modes:**
     * - teamID > 0: Specific team roster (current, free agency, or historical year)
     * - teamID = 0: Free agents available for signing
     * - teamID = -1: Entire league roster
     *
     * @return void Outputs complete HTML page directly
     */
    public function displayTeamPage(int $teamID): void;
}
