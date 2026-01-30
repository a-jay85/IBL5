<?php

declare(strict_types=1);

namespace ContactList;

use ContactList\Contracts\ContactListViewInterface;
use Utilities\HtmlSanitizer;

/**
 * View class for rendering GM contact list table.
 *
 * @see ContactListViewInterface
 */
class ContactListView implements ContactListViewInterface
{
    /**
     * @see ContactListViewInterface::render()
     */
    public function render(array $contacts): string
    {
        $output = $this->getStyleBlock();
        $output .= $this->renderTitle();
        $output .= $this->renderDescription();
        $output .= $this->renderTableStart();
        $output .= $this->renderTableRows($contacts);
        $output .= $this->renderTableEnd();

        return $output;
    }

    /**
     * Get the CSS styles for the contact list table.
     *
     * Styles are now in the design system (existing-components.css).
     *
     * @return string Empty string - styles are centralized
     */
    private function getStyleBlock(): string
    {
        return '';
    }

    /**
     * Render the page title.
     *
     * @return string HTML title
     */
    private function renderTitle(): string
    {
        return '<h2 class="ibl-table-title">IBL GM Contact List</h2>';
    }

    /**
     * Render the description text.
     *
     * @return string HTML description
     */
    private function renderDescription(): string
    {
        return '<p class="contact-description">Click on a team name to access that team\'s page; click on a GM\'s name to e-mail the GM.</p>';
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
                    <th>AIM</th>
                    <th>Skype</th>
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
     *     owner_email: string,
     *     skype: string,
     *     aim: string
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
     *     owner_email: string,
     *     skype: string,
     *     aim: string
     * } $contact Contact data array
     * @return string HTML for one contact row
     */
    private function renderContactRow(array $contact): string
    {
        $teamId = (int) ($contact['teamid'] ?? 0);
        $teamName = HtmlSanitizer::safeHtmlOutput($contact['team_name'] ?? '');
        $color1 = HtmlSanitizer::safeHtmlOutput($contact['color1'] ?? '');
        $color2 = HtmlSanitizer::safeHtmlOutput($contact['color2'] ?? '');
        $ownerName = HtmlSanitizer::safeHtmlOutput($contact['owner_name'] ?? '');
        $ownerEmail = HtmlSanitizer::safeHtmlOutput($contact['owner_email'] ?? '');
        $skype = HtmlSanitizer::safeHtmlOutput($contact['skype'] ?? '');
        $aim = HtmlSanitizer::safeHtmlOutput($contact['aim'] ?? '');

        return "<tr>
    <td class=\"ibl-team-cell--colored\" style=\"background-color: #{$color1};\">
        <a href=\"./modules.php?name=Team&amp;op=team&amp;teamID={$teamId}\" class=\"ibl-team-cell__name\" style=\"color: #{$color2};\">
            <img src=\"images/logo/new{$teamId}.png\" alt=\"\" class=\"ibl-team-cell__logo\" width=\"24\" height=\"24\" loading=\"lazy\">
            <span class=\"ibl-team-cell__text\">{$teamName}</span>
        </a>
    </td>
    <td class=\"gm-cell\">
        <a href=\"mailto:{$ownerEmail}\">{$ownerName}</a>
    </td>
    <td>{$aim}</td>
    <td>{$skype}</td>
</tr>";
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
