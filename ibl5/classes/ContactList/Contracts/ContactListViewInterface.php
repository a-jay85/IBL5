<?php

declare(strict_types=1);

namespace ContactList\Contracts;

/**
 * View interface for Contact List module rendering.
 *
 * Provides method to render the GM contact list table.
 */
interface ContactListViewInterface
{
    /**
     * Render the GM contact list table.
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
     * }> $contacts Array of team contact data
     * @return string HTML output for the contact list table
     */
    public function render(array $contacts): string;
}
