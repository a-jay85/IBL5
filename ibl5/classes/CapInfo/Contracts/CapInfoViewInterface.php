<?php

declare(strict_types=1);

namespace CapInfo\Contracts;

/**
 * CapInfoViewInterface - Contract for salary cap view rendering
 *
 * Defines methods for generating HTML output for salary cap information.
 *
 * @see \CapInfo\CapInfoView For the concrete implementation
 */
interface CapInfoViewInterface
{
    /**
     * Render the complete cap info table
     *
     * @param array $teamsData Processed team cap data
     * @param int $beginningYear Starting year for headers
     * @param int $endingYear Ending year for headers
     * @param int|null $userTeamId Current user's team ID (for highlighting)
     * @return string HTML output
     */
    public function render(array $teamsData, int $beginningYear, int $endingYear, ?int $userTeamId): string;
}
