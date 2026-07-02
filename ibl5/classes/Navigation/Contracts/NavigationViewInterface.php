<?php

declare(strict_types=1);

namespace Navigation\Contracts;

/**
 * Thin orchestrator that composes the full navigation bar from sub-views.
 *
 * @see \Navigation\NavigationView
 */
interface NavigationViewInterface
{
    /**
     * Render the complete navigation bar (desktop + mobile).
     */
    public function render(): string;
}
