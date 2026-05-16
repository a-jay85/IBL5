<?php

declare(strict_types=1);

namespace UI\Contracts;

/**
 * PlayerRowTransformerInterface - Contract for resolving Player and PlayerStats objects from player rows
 */
interface PlayerRowTransformerInterface
{
    /**
     * Resolve an iterable of player rows into Player + PlayerStats pairs.
     * Filters out '|'-prefixed placeholder names.
     *
     * @param \mysqli $db Database connection
     * @param iterable<int, \Player\Player|array<string, mixed>> $result Player result set
     * @param string $yr Year filter (empty for current season)
     * @return list<array{player: \Player\Player, playerStats: \Player\Stats\PlayerStats}>
     */
    public static function resolveWithStats(\mysqli $db, iterable $result, string $yr): array;

    /**
     * Resolve an iterable of player rows into Player objects only.
     * Filters out '|'-prefixed placeholder names.
     *
     * @param \mysqli $db Database connection
     * @param iterable<int, \Player\Player|array<string, mixed>> $result Player result set
     * @param string $yr Year filter (empty for current season)
     * @return list<\Player\Player>
     */
    public static function resolvePlayers(\mysqli $db, iterable $result, string $yr): array;
}
