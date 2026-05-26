<?php

declare(strict_types=1);

namespace League;

use League\Contracts\OlympicsTeamFilterInterface;

class OlympicsTeamFilter implements OlympicsTeamFilterInterface
{
    /** @var array<int, true>|null */
    private static ?array $cache = null;

    /**
     * @see OlympicsTeamFilterInterface::getRealTeamIds()
     */
    public static function getRealTeamIds(\mysqli $db): array
    {
        self::hydrateCache($db);

        /** @var array<int, true> $cache */
        $cache = self::$cache;

        return array_keys($cache);
    }

    /**
     * @see OlympicsTeamFilterInterface::isRealOlympicsTeam()
     */
    public static function isRealOlympicsTeam(\mysqli $db, int $teamId): bool
    {
        self::hydrateCache($db);

        return isset(self::$cache[$teamId]);
    }

    /**
     * @see OlympicsTeamFilterInterface::resetCache()
     */
    public static function resetCache(): void
    {
        self::$cache = null;
    }

    private static function hydrateCache(\mysqli $db): void
    {
        if (self::$cache !== null) {
            return;
        }

        $stmt = $db->prepare('SELECT teamid FROM `ibl_olympics_team_info` WHERE is_real_team = 1');
        if ($stmt === false) {
            self::$cache = [];
            return;
        }

        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            $stmt->close();
            self::$cache = [];
            return;
        }

        self::$cache = [];
        while (is_array($row = $result->fetch_assoc())) {
            $teamId = (int) $row['teamid'];
            self::$cache[$teamId] = true;
        }

        $stmt->close();
    }
}
