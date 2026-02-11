<?php

declare(strict_types=1);

namespace ActivityTracker;

use ActivityTracker\Contracts\ActivityTrackerRepositoryInterface;
use ActivityTracker\Contracts\ActivityTrackerViewInterface;
use Utilities\HtmlSanitizer;

/**
 * ActivityTrackerView - HTML rendering for activity tracker
 *
 * @phpstan-import-type ActivityRow from ActivityTrackerRepositoryInterface
 *
 * @see ActivityTrackerViewInterface For the interface contract
 */
class ActivityTrackerView implements ActivityTrackerViewInterface
{
    /**
     * @see ActivityTrackerViewInterface::render()
     *
     * @param list<ActivityRow> $teams
     */
    public function render(array $teams): string
    {
        $tableRows = '';
        foreach ($teams as $row) {
            $teamId = $row['teamid'];
            $teamDisplay = trim($row['team_city'] . ' ' . $row['team_name']);
            $color1 = $row['color1'];
            $color2 = $row['color2'];

            /** @var string $depth */
            $depth = HtmlSanitizer::safeHtmlOutput($row['depth']);
            /** @var string $simDepth */
            $simDepth = HtmlSanitizer::safeHtmlOutput($row['sim_depth']);
            /** @var string $asgVote */
            $asgVote = HtmlSanitizer::safeHtmlOutput($row['asg_vote']);
            /** @var string $eoyVote */
            $eoyVote = HtmlSanitizer::safeHtmlOutput($row['eoy_vote']);

            $teamCell = \UI\TeamCellHelper::renderTeamCell($teamId, $teamDisplay, $color1, $color2);

            $tableRows .= "<tr data-team-id=\"{$teamId}\">"
                . $teamCell
                . "<td>{$simDepth}</td>"
                . "<td>{$depth}</td>"
                . "<td>{$asgVote}</td>"
                . "<td>{$eoyVote}</td>"
                . '</tr>';
        }

        return '<h2 class="ibl-title">Activity Tracker</h2>
<table class="sortable ibl-data-table">
    <thead>
        <tr>
            <th>Team</th>
            <th>Sim Depth Chart</th>
            <th>Last Depth Chart</th>
            <th>ASG Ballot</th>
            <th>EOY Ballot</th>
        </tr>
    </thead>
    <tbody>' . $tableRows . '</tbody>
</table>';
    }
}
