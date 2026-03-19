<?php

declare(strict_types=1);

namespace UI\Tables;

use Player\Player;
use Player\PlayerStats;

/**
 * PlayerRowTransformer - Resolves Player (and optionally PlayerStats) objects
 * from an iterable of mixed player rows (Player instances or database arrays).
 *
 * Eliminates the duplicated ~30-line initialization block that was copy-pasted
 * across SeasonTotals, SeasonAverages, Per36Minutes, and Ratings.
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type HistoricalPlayerRow from \Player\Contracts\PlayerRepositoryInterface
 */
class PlayerRowTransformer
{
    /**
     * Resolve an iterable of player rows into Player + PlayerStats pairs.
     * Filters out '|'-prefixed placeholder names.
     *
     * @param \mysqli $db Database connection
     * @param iterable<int, Player|array<string, mixed>> $result Player result set
     * @param string $yr Year filter (empty for current season)
     * @return list<array{player: Player, playerStats: PlayerStats}>
     */
    public static function resolveWithStats(\mysqli $db, iterable $result, string $yr): array
    {
        $rows = [];
        foreach ($result as $plrRow) {
            if ($yr === "") {
                if ($plrRow instanceof Player) {
                    $player = $plrRow;
                    $playerStats = PlayerStats::withPlayerID($db, $player->playerID ?? 0);
                } elseif (is_array($plrRow)) {
                    /** @var PlayerRow $plrRow */
                    $player = Player::withPlrRow($db, $plrRow);
                    /** @var PlayerStats $playerStats */
                    $playerStats = PlayerStats::withPlrRow($db, $plrRow);
                } else {
                    continue;
                }

                if (str_starts_with($player->name ?? '', '|')) {
                    continue;
                }
            } else {
                if (!is_array($plrRow)) {
                    continue;
                }
                /** @var HistoricalPlayerRow $plrRow */
                $player = Player::withHistoricalPlrRow($db, $plrRow);
                /** @var PlayerStats $playerStats */
                $playerStats = PlayerStats::withHistoricalPlrRow($db, $plrRow);
            }

            $rows[] = ['player' => $player, 'playerStats' => $playerStats];
        }

        return $rows;
    }

    /**
     * Resolve an iterable of player rows into Player objects only.
     * Filters out '|'-prefixed placeholder names.
     *
     * Used by Ratings table which doesn't need PlayerStats.
     *
     * @param \mysqli $db Database connection
     * @param iterable<int, Player|array<string, mixed>> $result Player result set
     * @param string $yr Year filter (empty for current season)
     * @return list<Player>
     */
    public static function resolvePlayers(\mysqli $db, iterable $result, string $yr): array
    {
        $players = [];
        foreach ($result as $plrRow) {
            if ($yr === "") {
                if ($plrRow instanceof Player) {
                    $player = $plrRow;
                } elseif (is_array($plrRow)) {
                    /** @var PlayerRow $plrRow */
                    $player = Player::withPlrRow($db, $plrRow);
                } else {
                    continue;
                }

                if (str_starts_with($player->name ?? '', '|')) {
                    continue;
                }
            } else {
                if (!is_array($plrRow)) {
                    continue;
                }
                /** @var HistoricalPlayerRow $plrRow */
                $player = Player::withHistoricalPlrRow($db, $plrRow);
            }

            $players[] = $player;
        }

        return $players;
    }
}
