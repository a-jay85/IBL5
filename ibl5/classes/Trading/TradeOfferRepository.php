<?php

declare(strict_types=1);

namespace Trading;

use BaseMysqliRepository;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradeCashRepositoryInterface;

/**
 * TradeOfferRepository - Trade offer CRUD database operations
 *
 * Handles creation, retrieval, and deletion of trade offers and their items.
 * Extracted from TradingRepository to follow single-responsibility principle.
 *
 * @see TradeOfferRepositoryInterface For method contracts
 * @see BaseMysqliRepository For base class documentation and error codes
 *
 * @phpstan-import-type TradeInfoRow from \Trading\Contracts\TradeOfferRepositoryInterface
 */
class TradeOfferRepository extends BaseMysqliRepository implements TradeOfferRepositoryInterface
{
    private TradeCashRepositoryInterface $cashRepository;

    /**
     * Constructor
     *
     * @param \mysqli $db Active mysqli connection
     * @param TradeCashRepositoryInterface|null $cashRepository Cash repository for deleteTradeOffer coordination
     * @throws \RuntimeException If connection is invalid (error code 1002)
     */
    public function __construct(\mysqli $db, ?TradeCashRepositoryInterface $cashRepository = null)
    {
        parent::__construct($db);
        $this->cashRepository = $cashRepository ?? new TradeCashRepository($db);
    }

    /**
     * @see TradeOfferRepositoryInterface::generateNextTradeOfferId()
     */
    public function generateNextTradeOfferId(): int
    {
        $this->execute("INSERT INTO ibl_trade_offers () VALUES ()");
        /** @var array{id: int}|null $row */
        $row = $this->fetchOne("SELECT LAST_INSERT_ID() AS id");
        if ($row === null || $row['id'] === 0) {
            throw new \RuntimeException('Failed to generate trade offer ID');
        }
        return $row['id'];
    }

    /**
     * @see TradeOfferRepositoryInterface::insertTradeItem()
     */
    public function insertTradeItem(int $tradeOfferId, int $itemId, TradeItemType $itemType, string $fromTeam, string $toTeam, string $approvalTeam): int
    {
        if ($_SERVER['SERVER_NAME'] === "localhost") {
            $approvalTeam = 'test';
        }

        return $this->execute(
            "INSERT INTO ibl_trade_info (tradeofferid, itemid, itemtype, trade_from, trade_to, approval) VALUES (?, ?, ?, ?, ?, ?)",
            "iissss",
            $tradeOfferId,
            $itemId,
            $itemType->value,
            $fromTeam,
            $toTeam,
            $approvalTeam
        );
    }

    /**
     * @see TradeOfferRepositoryInterface::getTradesByOfferId()
     */
    public function getTradesByOfferId(int $offerId): array
    {
        /** @var list<TradeInfoRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_trade_info WHERE tradeofferid = ?",
            "i",
            $offerId
        );
    }

    /**
     * @see TradeOfferRepositoryInterface::getTradesByOfferIdForUpdate()
     */
    public function getTradesByOfferIdForUpdate(int $offerId): array
    {
        /** @var list<TradeInfoRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_trade_info WHERE tradeofferid = ? FOR UPDATE",
            "i",
            $offerId
        );
    }

    /**
     * @see TradeOfferRepositoryInterface::getAllTradeOffers()
     */
    public function getAllTradeOffers(): array
    {
        /** @var list<TradeInfoRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_trade_info WHERE approval != 'completed' ORDER BY tradeofferid ASC"
        );
    }

    /**
     * @see TradeOfferRepositoryInterface::markTradeInfoCompleted()
     */
    public function markTradeInfoCompleted(int $offerId): int
    {
        return $this->execute(
            "UPDATE ibl_trade_info SET approval = 'completed' WHERE tradeofferid = ?",
            "i",
            $offerId
        );
    }

    /**
     * @see TradeOfferRepositoryInterface::deleteTradeInfoByOfferId()
     */
    public function deleteTradeInfoByOfferId(int $offerId): int
    {
        return $this->execute(
            "DELETE FROM ibl_trade_info WHERE tradeofferid = ?",
            "i",
            $offerId
        );
    }

    /**
     * @see TradeOfferRepositoryInterface::deleteTradeOfferById()
     */
    public function deleteTradeOfferById(int $offerId): int
    {
        return $this->execute(
            "DELETE FROM ibl_trade_offers WHERE id = ?",
            "i",
            $offerId
        );
    }

    /**
     * @see TradeOfferRepositoryInterface::deleteTradeOffer()
     */
    public function deleteTradeOffer(int $offerId): void
    {
        $this->transactional(function () use ($offerId): void {
            $this->deleteTradeInfoByOfferId($offerId);
            $this->cashRepository->deleteTradeCashByOfferId($offerId);
            $this->deleteTradeOfferById($offerId);
        });
    }
}
