<?php

declare(strict_types=1);

namespace ActivityTracker\Contracts;

/**
 * ActivityTrackerViewInterface - Contract for activity tracker HTML rendering
 *
 * @phpstan-import-type ActivityRow from ActivityTrackerRepositoryInterface
 *
 * @see \ActivityTracker\ActivityTrackerView For the concrete implementation
 */
interface ActivityTrackerViewInterface
{
    /**
     * Render the activity tracker table
     *
     * @param list<ActivityRow> $teams Team activity data
     * @return string HTML output
     */
    public function render(array $teams): string;
}
