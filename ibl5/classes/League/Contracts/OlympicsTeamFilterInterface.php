<?php

declare(strict_types=1);

namespace League\Contracts;

interface OlympicsTeamFilterInterface
{
    /**
     * @return list<int> Team IDs where is_real_team = 1
     */
    public static function getRealTeamIds(\mysqli $db): array;

    public static function isRealOlympicsTeam(\mysqli $db, int $teamId): bool;

    public static function resetCache(): void;
}
