<?php

declare(strict_types=1);

namespace CapSpace\Contracts;

/**
 * CapSpaceViewInterface - Contract for salary cap view rendering
 *
 * Defines methods for generating HTML output for salary cap information.
 *
 * @phpstan-import-type CapSpaceTeamData from \CapSpace\CapSpaceService
 *
 * @see \CapSpace\CapSpaceView For the concrete implementation
 */
interface CapSpaceViewInterface
{
    /**
     * Render the complete cap info table
     *
     * @param list<CapSpaceTeamData> $teamsData Processed team cap data
     * @param int $beginningYear Starting year for headers
     * @param int $endingYear Ending year for headers
     * @return string HTML output
     */
    public function render(array $teamsData, int $beginningYear, int $endingYear): string;
}
