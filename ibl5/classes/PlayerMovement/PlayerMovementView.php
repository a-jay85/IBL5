<?php

declare(strict_types=1);

namespace PlayerMovement;

use Player\PlayerImageHelper;
use PlayerMovement\Contracts\PlayerMovementRepositoryInterface;
use PlayerMovement\Contracts\PlayerMovementViewInterface;
use UI\TeamCellHelper;

/**
 * PlayerMovementView - HTML rendering for player movement
 *
 * @phpstan-import-type MovementRow from PlayerMovementRepositoryInterface
 *
 * @see PlayerMovementViewInterface For the interface contract
 */
class PlayerMovementView implements PlayerMovementViewInterface
{
    /**
     * @see PlayerMovementViewInterface::render()
     *
     * @param list<MovementRow> $movements
     */
    public function render(array $movements): string
    {
        $html = '<h2 class="ibl-title">Player Movement</h2>
<table class="sortable ibl-data-table">
    <thead>
        <tr>
            <th>Player</th>
            <th>Old</th>
            <th>New</th>
        </tr>
    </thead>
    <tbody>';

        foreach ($movements as $row) {
            $pid = $row['pid'];

            $oldTeamId = $row['old_teamid'];
            $oldTeamDisplay = trim(($row['old_city'] ?? '') . ' ' . ($row['old_team'] ?? ''));
            $oldColor1 = $row['old_color1'] ?? '333333';
            $oldColor2 = $row['old_color2'] ?? 'FFFFFF';

            $newTeamId = $row['new_teamid'];
            $newTeamDisplay = trim(($row['new_city'] ?? '') . ' ' . ($row['new_team'] ?? ''));
            $newColor1 = $row['new_color1'] ?? '333333';
            $newColor2 = $row['new_color2'] ?? 'FFFFFF';

            $oldTeamCell = TeamCellHelper::renderTeamCellOrFreeAgent($oldTeamId, $oldTeamDisplay, $oldColor1, $oldColor2);
            $newTeamCell = TeamCellHelper::renderTeamCellOrFreeAgent($newTeamId, $newTeamDisplay, $newColor1, $newColor2);
            $playerCell = PlayerImageHelper::renderFlexiblePlayerCell($pid, $row['name']);

            $teamIds = implode(',', array_unique(array_filter([$oldTeamId, $newTeamId], static fn (int $id): bool => $id > 0)));
            $html .= "<tr data-team-ids=\"{$teamIds}\">"
                . $playerCell
                . $oldTeamCell
                . $newTeamCell
                . '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }
}
