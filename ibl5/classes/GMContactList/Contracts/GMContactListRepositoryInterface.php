<?php

declare(strict_types=1);

namespace GMContactList\Contracts;

/**
 * Repository interface for GM Contact List module.
 *
 * Provides method to retrieve GM contact information from the database.
 */
interface GMContactListRepositoryInterface
{
    /**
     * Get all team contact information ordered by city.
     *
     * @return array<int, array{
     *     teamid: int,
     *     team_city: string,
     *     team_name: string,
     *     color1: string,
     *     color2: string,
     *     owner_name: string,
     *     owner_email: string,
     *     skype: string,
     *     aim: string
     * }> Array of team contact data
     */
    public function getAllTeamContacts(): array;
}
