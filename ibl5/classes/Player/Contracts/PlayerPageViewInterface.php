<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerPageViewInterface - Base contract for player page view rendering
 * 
 * Defines the common interface for all player page views.
 * All render methods return HTML strings using output buffering pattern.
 */
interface PlayerPageViewInterface
{
    /**
     * Render the view content
     * 
     * @return string HTML content for the view
     */
    public function render(): string;
}
