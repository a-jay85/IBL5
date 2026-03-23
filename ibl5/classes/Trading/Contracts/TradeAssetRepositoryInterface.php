<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * TradeAssetRepositoryInterface - Contract for player and draft pick asset operations
 *
 * Handles lookups and updates for players and draft picks involved in trades.
 * Extracted from TradingRepositoryInterface to follow single-responsibility principle.
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 *
 * @phpstan-type TradeValidationRow array{ordinal: ?int, cy: ?int}
 * @phpstan-type DraftPickRow array{pickid: int, ownerofpick: string, teampick: string, year: string, round: string, notes: ?string, created_at: string, updated_at: string}
 */
interface TradeAssetRepositoryInterface
{
    /**
     * Get player by player ID
     *
     * @param int $playerId Player ID
     * @return PlayerRow|null Full player data, or null if not found
     */
    public function getPlayerById(int $playerId): ?array;

    /**
     * Get multiple players by their IDs in a single query
     *
     * @param list<int> $playerIds Player IDs to look up
     * @return array<int, PlayerRow> Player rows keyed by pid
     */
    public function getPlayersByIds(array $playerIds): array;

    /**
     * Get draft pick by pick ID
     *
     * @param int $pickId Pick ID
     * @return DraftPickRow|null Pick data with year, teampick, round, notes fields, or null if not found
     */
    public function getDraftPickById(int $pickId): ?array;

    /**
     * Get multiple draft picks by their IDs in a single query
     *
     * @param list<int> $pickIds Pick IDs to look up
     * @return array<int, DraftPickRow> Pick rows keyed by pickid
     */
    public function getDraftPicksByIds(array $pickIds): array;

    /**
     * Update player's team after trade
     *
     * @param int $playerId Player ID
     * @param int $newTeamId New team ID
     * @return int Number of rows affected
     */
    public function updatePlayerTeam(int $playerId, int $newTeamId): int;

    /**
     * Update draft pick owner
     *
     * @param int $pickId Pick ID
     * @param string $newOwner New owner team name
     * @param int $newOwnerId New owner team ID
     * @return int Number of rows affected
     */
    public function updateDraftPickOwnerById(int $pickId, string $newOwner, int $newOwnerId): int;

    /**
     * Check if a player ID exists in the database
     *
     * @param int $playerId Player ID to check
     * @return bool True if player exists, false otherwise
     */
    public function playerIdExists(int $playerId): bool;

    /**
     * Get player data for trade validation
     *
     * @param int $playerId Player ID
     * @return TradeValidationRow|null Player data with 'ordinal' and 'cy' fields, or null if not found
     */
    public function getPlayerForTradeValidation(int $playerId): ?array;
}
