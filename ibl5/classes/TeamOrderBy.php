<?php

declare(strict_types=1);

/**
 * Whitelisted ORDER BY fragments for fetchAllRealTeams().
 * The enum is the whitelist: the backing string value is the literal SQL
 * `ORDER BY` fragment, so no value reaching the query can be unsafe.
 */
enum TeamOrderBy: string
{
    case TeamName = 'team_name ASC';
    case TeamId = 'teamid ASC';
    case TeamCity = 'team_city ASC';
}
