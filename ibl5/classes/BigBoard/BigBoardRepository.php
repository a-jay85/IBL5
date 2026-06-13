<?php

declare(strict_types=1);

namespace BigBoard;

use BaseMysqliRepository;
use BigBoard\Contracts\BigBoardRepositoryInterface;

/**
 * @see BigBoardRepositoryInterface
 * @phpstan-import-type BigBoardRow from \BigBoard\Contracts\BigBoardRepositoryInterface
 * @phpstan-import-type AddableProspect from \BigBoard\Contracts\BigBoardRepositoryInterface
 */
class BigBoardRepository extends BaseMysqliRepository implements BigBoardRepositoryInterface
{
    /**
     * @param \mysqli $db Active mysqli connection
     */
    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    protected function rewriteTableNames(string $query): string
    {
        // The big board is a per-GM/franchise identity feature scoped to the IBL
        // (gm_username → teamid). It must never route to Olympics tables, so the
        // backtick-quoted ibl_draft_class/ibl_team_info references (required by the
        // bareTableIdentifier rule) are deliberately NOT rewritten. Mirrors
        // TeamIdentityRepository's and WatchlistRepository's identity-table override.
        return $query;
    }

    /**
     * @see BigBoardRepositoryInterface::getBoardForTeam()
     *
     * @return list<BigBoardRow>
     */
    public function getBoardForTeam(int $teamid): array
    {
        /** @var list<BigBoardRow> */
        return $this->fetchAll(
            "SELECT b.id, b.prospect_id, b.`rank`, b.note, dc.name, dc.pos, dc.drafted
             FROM `gm_draft_big_board` b
             JOIN `ibl_draft_class` dc ON b.prospect_id = dc.id
             WHERE b.teamid = ?
             ORDER BY b.`rank` ASC, b.id ASC",
            "i",
            $teamid
        );
    }

    /**
     * @see BigBoardRepositoryInterface::getAvailableProspects()
     *
     * @return list<BigBoardRow>
     */
    public function getAvailableProspects(int $teamid): array
    {
        /** @var list<BigBoardRow> */
        return $this->fetchAll(
            "SELECT b.id, b.prospect_id, b.`rank`, b.note, dc.name, dc.pos, dc.drafted
             FROM `gm_draft_big_board` b
             JOIN `ibl_draft_class` dc ON b.prospect_id = dc.id
             WHERE b.teamid = ? AND dc.drafted = 0
             ORDER BY b.`rank` ASC, b.id ASC",
            "i",
            $teamid
        );
    }

    /**
     * @see BigBoardRepositoryInterface::getAddableProspects()
     *
     * @return list<AddableProspect>
     */
    public function getAddableProspects(int $teamid): array
    {
        /** @var list<AddableProspect> */
        return $this->fetchAll(
            "SELECT dc.id, dc.name, dc.pos
             FROM `ibl_draft_class` dc
             WHERE dc.drafted = 0
               AND dc.id NOT IN (SELECT prospect_id FROM `gm_draft_big_board` WHERE teamid = ?)
             ORDER BY dc.name ASC",
            "i",
            $teamid
        );
    }

    /**
     * @see BigBoardRepositoryInterface::addEntry()
     */
    public function addEntry(int $teamid, int $prospectId, int $rank, string $note): bool
    {
        try {
            // Plain INSERT (not INSERT IGNORE): a duplicate (teamid, prospect_id)
            // trips the UNIQUE key and throws (error 1003), which we map to false
            // so the service can surface a user-visible "already on board" error.
            $affected = $this->execute(
                "INSERT INTO `gm_draft_big_board` (teamid, prospect_id, `rank`, note) VALUES (?, ?, ?, ?)",
                "iiis",
                $teamid,
                $prospectId,
                $rank,
                $note
            );
            return $affected > 0;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * @see BigBoardRepositoryInterface::setRank()
     */
    public function setRank(int $teamid, int $entryId, int $rank): int
    {
        return $this->execute(
            "UPDATE `gm_draft_big_board` SET `rank` = ? WHERE id = ? AND teamid = ?",
            "iii",
            $rank,
            $entryId,
            $teamid
        );
    }

    /**
     * @see BigBoardRepositoryInterface::setNote()
     */
    public function setNote(int $teamid, int $entryId, string $note): int
    {
        return $this->execute(
            "UPDATE `gm_draft_big_board` SET note = ? WHERE id = ? AND teamid = ?",
            "sii",
            $note,
            $entryId,
            $teamid
        );
    }

    /**
     * @see BigBoardRepositoryInterface::removeEntry()
     */
    public function removeEntry(int $teamid, int $entryId): int
    {
        return $this->execute(
            "DELETE FROM `gm_draft_big_board` WHERE id = ? AND teamid = ?",
            "ii",
            $entryId,
            $teamid
        );
    }
}
