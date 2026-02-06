<?php

declare(strict_types=1);

namespace GMContactList;

use GMContactList\Contracts\GMContactListViewInterface;
use UI\TeamCellHelper;
use Utilities\HtmlSanitizer;

/**
 * View class for rendering GM contact list table.
 *
 * @see GMContactListViewInterface
 */
class GMContactListView implements GMContactListViewInterface
{
    /**
     * @see GMContactListViewInterface::render()
     */
    public function render(array $contacts): string
    {
        $output = '';
        $output .= $this->renderTitle();
        $output .= $this->renderTableStart();
        $output .= $this->renderTableRows($contacts);
        $output .= $this->renderTableEnd();

        return $output;
    }

    /**
     * Render the page title.
     *
     * @return string HTML title
     */
    private function renderTitle(): string
    {
        return '<h2 class="ibl-title">IBL GM Contact List</h2>';
    }

    /**
     * Render the start of the table.
     *
     * @return string HTML table start
     */
    private function renderTableStart(): string
    {
        return '<table class="sortable ibl-data-table contact-table">
            <thead>
                <tr>
                    <th>Team</th>
                    <th>Name</th>
                </tr>
            </thead>
            <tbody>';
    }

    /**
     * Render all table rows.
     *
     * @param array<int, array{
     *     teamid: int,
     *     team_city: string,
     *     team_name: string,
     *     color1: string,
     *     color2: string,
     *     owner_name: string,
     *     discordID: int|null
     * }> $contacts Array of contact data
     * @return string HTML table rows
     */
    private function renderTableRows(array $contacts): string
    {
        $output = '';

        foreach ($contacts as $row) {
            $output .= $this->renderContactRow($row);
        }

        return $output;
    }

    /**
     * Render a single contact row.
     *
     * @param array{
     *     teamid: int,
     *     team_city: string,
     *     team_name: string,
     *     color1: string,
     *     color2: string,
     *     owner_name: string,
     *     discordID: int|null
     * } $contact Contact data array
     * @return string HTML for one contact row
     */
    private function renderContactRow(array $contact): string
    {
        $teamId = (int) $contact['teamid'];
        /** @var string $ownerName */
        $ownerName = HtmlSanitizer::safeHtmlOutput($contact['owner_name']);
        $discordID = $contact['discordID'];

        if ($discordID !== null) {
            $gmCell = "<a href=\"https://discord.com/users/{$discordID}\">{$ownerName}</a>";
        } else {
            $gmCell = $ownerName;
        }

        $teamCell = TeamCellHelper::renderTeamCell($teamId, $contact['team_name'], $contact['color1'], $contact['color2']);

        return "<tr data-team-id=\"{$teamId}\">"
            . $teamCell
            . "<td class=\"gm-cell\">{$gmCell}</td>"
            . '</tr>';
    }

    /**
     * Render the end of the table.
     *
     * @return string HTML table end
     */
    private function renderTableEnd(): string
    {
        return '</tbody></table>';
    }
}
