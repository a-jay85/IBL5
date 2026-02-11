<?php

declare(strict_types=1);

namespace LeagueSchedule\Contracts;

/**
 * LeagueScheduleViewInterface - Contract for league schedule HTML rendering
 *
 * @phpstan-import-type SchedulePageData from LeagueScheduleServiceInterface
 *
 * @see \LeagueSchedule\LeagueScheduleView For the concrete implementation
 */
interface LeagueScheduleViewInterface
{
    /**
     * Render the complete league schedule
     *
     * @param SchedulePageData $pageData Organized schedule data from the service
     * @return string HTML output
     */
    public function render(array $pageData): string;
}
