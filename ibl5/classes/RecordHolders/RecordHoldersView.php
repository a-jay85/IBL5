<?php

declare(strict_types=1);

namespace RecordHolders;

use RecordHolders\Contracts\RecordHoldersViewInterface;
use RecordHolders\Contracts\RecordHoldersServiceInterface;

/**
 * View class for rendering the all-time IBL record holders page.
 *
 * Receives structured data from RecordHoldersService and renders HTML tables.
 * Delegates section rendering to PlayerRecordSectionRenderer, TeamRecordSectionRenderer,
 * and the shared RecordTableRenderer.
 *
 * @phpstan-import-type AllRecordsData from RecordHoldersServiceInterface
 *
 * @see RecordHoldersViewInterface
 */
class RecordHoldersView implements RecordHoldersViewInterface
{
    private RecordTableRenderer $tableRenderer;
    private PlayerRecordSectionRenderer $playerSection;
    private TeamRecordSectionRenderer $teamSection;

    public function __construct()
    {
        $this->tableRenderer = new RecordTableRenderer();
        $this->playerSection = new PlayerRecordSectionRenderer($this->tableRenderer);
        $this->teamSection = new TeamRecordSectionRenderer($this->tableRenderer);
    }

    /**
     * @see RecordHoldersViewInterface::render()
     *
     * @param AllRecordsData $records
     */
    public function render(array $records): string
    {
        $output = '<h1 class="ibl-title">Record Holders</h1>';
        $output .= '<div class="record-section">';
        $output .= $this->playerSection->renderPlayerSingleGameRecords($records);
        $output .= $this->playerSection->renderPlayerFullSeasonRecords($records['playerFullSeason']);
        $output .= $this->playerSection->renderPlayerPlayoffRecords($records);
        $output .= $this->playerSection->renderPlayerHeatRecords($records);
        $output .= $this->teamSection->renderTeamRecords($records);
        $output .= '</div>';

        return $output;
    }
}
