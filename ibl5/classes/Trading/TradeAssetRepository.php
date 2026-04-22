<?php

declare(strict_types=1);

namespace Trading;

use BaseMysqliRepository;
use Trading\Contracts\TradeAssetRepositoryInterface;

/**
 * TradeAssetRepository - Player and draft pick asset database operations
 *
 * Handles lookups and updates for players and draft picks involved in trades.
 * Extracted from TradingRepository to follow single-responsibility principle.
 *
 * @see TradeAssetRepositoryInterface For method contracts
 * @see BaseMysqliRepository For base class documentation and error codes
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type TradeValidationRow from \Trading\Contracts\TradeAssetRepositoryInterface
 * @phpstan-import-type DraftPickRow from \Trading\Contracts\TradeAssetRepositoryInterface
 */
class TradeAssetRepository extends BaseMysqliRepository implements TradeAssetRepositoryInterface
{
    /**
     * Constructor
     *
     * @param \mysqli $db Active mysqli connection
     * @throws \RuntimeException If connection is invalid (error code 1002)
     */
    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    /**
     * @see TradeAssetRepositoryInterface::getPlayerById()
     */
    public function getPlayerById(int $playerId): ?array
    {
        /** @var PlayerRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_plr WHERE pid = ?",
            "i",
            $playerId
        );
    }

    /**
     * @see TradeAssetRepositoryInterface::getPlayersByIds()
     *
     * @return array<int, PlayerRow>
     */
    public function getPlayersByIds(array $playerIds): array
    {
        if ($playerIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($playerIds), '?'));
        $types = str_repeat('i', count($playerIds));

        /** @var list<PlayerRow> $rows */
        $rows = $this->fetchAll(
            "SELECT * FROM ibl_plr WHERE pid IN ({$placeholders})",
            $types,
            ...$playerIds
        );

        $result = [];
        foreach ($rows as $row) {
            $result[$row['pid']] = $row;
        }

        return $result;
    }

    /**
     * @see TradeAssetRepositoryInterface::getDraftPickById()
     */
    public function getDraftPickById(int $pickId): ?array
    {
        /** @var DraftPickRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_draft_picks WHERE pickid = ?",
            "i",
            $pickId
        );
    }

    /**
     * @see TradeAssetRepositoryInterface::getDraftPicksByIds()
     *
     * @return array<int, DraftPickRow>
     */
    public function getDraftPicksByIds(array $pickIds): array
    {
        if ($pickIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($pickIds), '?'));
        $types = str_repeat('i', count($pickIds));

        /** @var list<DraftPickRow> $rows */
        $rows = $this->fetchAll(
            "SELECT * FROM ibl_draft_picks WHERE pickid IN ({$placeholders})",
            $types,
            ...$pickIds
        );

        $result = [];
        foreach ($rows as $row) {
            $result[$row['pickid']] = $row;
        }

        return $result;
    }

    /**
     * @see TradeAssetRepositoryInterface::updatePlayerTeam()
     */
    public function updatePlayerTeam(int $playerId, int $newTeamId): int
    {
        return $this->execute(
            "UPDATE ibl_plr SET teamid = ? WHERE pid = ?",
            "ii",
            $newTeamId,
            $playerId
        );
    }

    /**
     * @see TradeAssetRepositoryInterface::updateDraftPickOwnerById()
     */
    public function updateDraftPickOwnerById(int $pickId, string $newOwner, int $newOwnerId): int
    {
        return $this->execute(
            "UPDATE ibl_draft_picks SET ownerofpick = ?, owner_teamid = ? WHERE pickid = ?",
            "sii",
            $newOwner,
            $newOwnerId,
            $pickId
        );
    }

    /**
     * @see TradeAssetRepositoryInterface::playerIdExists()
     */
    public function playerIdExists(int $playerId): bool
    {
        $result = $this->fetchOne(
            "SELECT 1 FROM ibl_plr WHERE pid = ? LIMIT 1",
            "i",
            $playerId
        );
        return $result !== null;
    }

    /**
     * @see TradeAssetRepositoryInterface::getPlayerForTradeValidation()
     */
    public function getPlayerForTradeValidation(int $playerId): ?array
    {
        /** @var TradeValidationRow|null */
        return $this->fetchOne(
            "SELECT ordinal, cy FROM ibl_plr WHERE pid = ?",
            "i",
            $playerId
        );
    }
}
