<?php

declare(strict_types=1);

namespace TradeBlock;

use BaseMysqliRepository;
use TradeBlock\Contracts\TradeBlockRepositoryInterface;

/**
 * @see TradeBlockRepositoryInterface
 *
 * @phpstan-import-type AvailablePlayerRow from TradeBlockRepositoryInterface
 */
class TradeBlockRepository extends BaseMysqliRepository implements TradeBlockRepositoryInterface
{
    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    /**
     * @see TradeBlockRepositoryInterface::getAllAvailable()
     *
     * @return list<AvailablePlayerRow>
     */
    public function getAllAvailable(): array
    {
        /** @var list<AvailablePlayerRow> */
        return $this->fetchAll(
            "SELECT b.pid, b.note, p.name, p.teamid, t.team_name, t.team_city, t.color1, t.color2
             FROM `gm_trade_block` b
             JOIN `ibl_plr` p ON b.pid = p.pid
             JOIN `ibl_team_info` t ON p.teamid = t.teamid
             WHERE p.retired = 0
             ORDER BY t.team_name ASC, p.name ASC"
        );
    }

    /**
     * @see TradeBlockRepositoryInterface::getBlockPidsForTeam()
     *
     * @return array<int, string>
     */
    public function getBlockPidsForTeam(int $teamId): array
    {
        $rows = $this->fetchAll(
            "SELECT b.pid, b.note
             FROM `gm_trade_block` b
             JOIN `ibl_plr` p ON b.pid = p.pid
             WHERE p.teamid = ?
               AND p.retired = 0",
            "i",
            $teamId
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['pid']] = (string) $row['note'];
        }

        return $map;
    }

    /**
     * @see TradeBlockRepositoryInterface::getSeekingNotesByTeam()
     *
     * @return array<int, string>
     */
    public function getSeekingNotesByTeam(): array
    {
        $rows = $this->fetchAll(
            "SELECT teamid, seeking_note FROM `gm_trade_seeking`"
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['teamid']] = (string) $row['seeking_note'];
        }

        return $map;
    }

    /**
     * @see TradeBlockRepositoryInterface::getSeekingNoteForTeam()
     */
    public function getSeekingNoteForTeam(int $teamId): string
    {
        $row = $this->fetchOne(
            "SELECT seeking_note FROM `gm_trade_seeking` WHERE teamid = ? LIMIT 1",
            "i",
            $teamId
        );

        return $row !== null ? (string) $row['seeking_note'] : '';
    }

    /**
     * @see TradeBlockRepositoryInterface::setOnBlock()
     */
    public function setOnBlock(int $pid, string $note): bool
    {
        try {
            $this->execute(
                "INSERT INTO `gm_trade_block` (pid, note) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE note = VALUES(note)",
                "is",
                $pid,
                $note
            );
            return true;
        } catch (\RuntimeException $e) {
            \Logging\LoggerFactory::getChannel('db')->error('Failed to set player on trade block', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * @see TradeBlockRepositoryInterface::removeFromBlock()
     */
    public function removeFromBlock(int $pid): bool
    {
        try {
            $this->execute(
                "DELETE FROM `gm_trade_block` WHERE pid = ?",
                "i",
                $pid
            );
            return true;
        } catch (\RuntimeException $e) {
            \Logging\LoggerFactory::getChannel('db')->error('Failed to remove player from trade block', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * @see TradeBlockRepositoryInterface::upsertSeekingNote()
     */
    public function upsertSeekingNote(int $teamId, string $note): bool
    {
        try {
            $this->execute(
                "INSERT INTO `gm_trade_seeking` (teamid, seeking_note) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE seeking_note = VALUES(seeking_note)",
                "is",
                $teamId,
                $note
            );
            return true;
        } catch (\RuntimeException $e) {
            \Logging\LoggerFactory::getChannel('db')->error('Failed to upsert seeking note', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
