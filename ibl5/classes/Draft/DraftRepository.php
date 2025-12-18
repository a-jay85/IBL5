<?php

declare(strict_types=1);

namespace Draft;

use Draft\Contracts\DraftRepositoryInterface;

/**
 * @see DraftRepositoryInterface
 * @extends \BaseMysqliRepository
 */
class DraftRepository extends \BaseMysqliRepository implements DraftRepositoryInterface
{
    private $commonRepository;

    // Constants for player name matching
    const IBL_PLR_NAME_MAX_LENGTH = 32;  // Matches varchar(32) in ibl_plr.name
    const PARTIAL_NAME_MATCH_LENGTH = 30;  // For LIKE queries with diacritical differences

    public function __construct(object $db)
    {
        parent::__construct($db);
        $this->commonRepository = new \Services\CommonMysqliRepository($db);
    }

    /**
     * @see DraftRepositoryInterface::getCurrentDraftSelection()
     */
    public function getCurrentDraftSelection(int $draftRound, int $draftPick): ?string
    {
        $row = $this->fetchOne(
            "SELECT `player` FROM ibl_draft WHERE `round` = ? AND `pick` = ?",
            "ii",
            $draftRound,
            $draftPick
        );
        
        return $row ? $row['player'] : null;
    }

    /**
     * @see DraftRepositoryInterface::updateDraftTable()
     */
    public function updateDraftTable(string $playerName, string $date, int $draftRound, int $draftPick): bool
    {
        $affected = $this->execute(
            "UPDATE ibl_draft SET `player` = ?, `date` = ? WHERE `round` = ? AND `pick` = ?",
            "ssii",
            $playerName,
            $date,
            $draftRound,
            $draftPick
        );
        
        return $affected > 0;
    }

    /**
     * @see DraftRepositoryInterface::updateRookieTable()
     */
    public function updateRookieTable(string $playerName, string $teamName): bool
    {
        $affected = $this->execute(
            "UPDATE `ibl_draft_class` SET `team` = ?, `drafted` = '1' WHERE `name` = ?",
            "ss",
            $teamName,
            $playerName
        );
        
        return $affected > 0;
    }

    private function getNextAvailablePid(): int
    {
        $draftPidStart = 90000;
        
        $row = $this->fetchOne(
            "SELECT MAX(pid) as max_pid FROM ibl_plr WHERE pid >= ?",
            "i",
            $draftPidStart
        );
        
        $maxPid = $row['max_pid'] ?? null;
        if ($row && $maxPid !== null && $maxPid !== '' && $maxPid >= $draftPidStart) {
            return (int) $maxPid + 1;
        }
        
        return $draftPidStart; // Start at 90000 if no draft PIDs exist yet
    }

    /**
     * @see DraftRepositoryInterface::createPlayerFromDraftClass()
     */
    public function createPlayerFromDraftClass(string $playerName, string $teamName): bool
    {
        $teamId = $this->commonRepository->getTidFromTeamname($teamName);
        if ($teamId === null) {
            return false;
        }
        $teamId = (int) $teamId;
        
        $draftClassPlayer = $this->fetchOne(
            "SELECT * FROM ibl_draft_class WHERE name = ? LIMIT 1",
            "s",
            $playerName
        );
        
        if (!$draftClassPlayer) {
            return false;
        }
        
        $pid = $this->getNextAvailablePid();
        $name = substr($playerName, 0, self::IBL_PLR_NAME_MAX_LENGTH);
        $pos = $draftClassPlayer['pos'];
        $oo = (int) $draftClassPlayer['offo'];
        $od = (int) $draftClassPlayer['offd'];
        $po = (int) $draftClassPlayer['offp'];
        $to = (int) $draftClassPlayer['offt'];
        $do = (int) $draftClassPlayer['defo'];
        $dd = (int) $draftClassPlayer['defd'];
        $pd = (int) $draftClassPlayer['defp'];
        $td = (int) $draftClassPlayer['deft'];
        
        $age = (int) $draftClassPlayer['age'];
        $sta = (int) $draftClassPlayer['sta'];
        $talent = (int) $draftClassPlayer['tal'];
        $skill = (int) $draftClassPlayer['skl'];
        $intangibles = (int) $draftClassPlayer['int'];
        
        // Insert new player into ibl_plr
        $affected = $this->execute(
            "INSERT INTO ibl_plr (
                pid, name, age, tid, teamname, pos,
                sta, oo, od, po, `to`, `do`, dd, pd, td,
                talent, skill, intangibles,
                active, bird, exp, cy, cyt
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?,
                1, 0, 0, 0, 0
            )",
            "isiissiiiiiiiiiiii",
            $pid, $name, $age, $teamId, $teamName, $pos,
            $sta, $oo, $od, $po, $to, $do, $dd, $pd, $td,
            $talent, $skill, $intangibles
        );
        
        return $affected > 0;
    }

    /**
     * @see DraftRepositoryInterface::isPlayerAlreadyDrafted()
     */
    public function isPlayerAlreadyDrafted(string $playerName): bool
    {
        $row = $this->fetchOne(
            "SELECT drafted FROM ibl_draft_class WHERE name = ? LIMIT 1",
            "s",
            $playerName
        );
        
        if ($row) {
            return $row['drafted'] == '1' || $row['drafted'] === 1;
        }
        
        return false;
    }

    /**
     * @see DraftRepositoryInterface::getNextTeamOnClock()
     */
    public function getNextTeamOnClock(): ?string
    {
        $row = $this->fetchOne(
            "SELECT team FROM ibl_draft WHERE player = '' ORDER BY round ASC, pick ASC LIMIT 1"
        );
        
        return $row ? $row['team'] : null;
    }

    /**
     * @see DraftRepositoryInterface::getAllDraftClassPlayers()
     */
    public function getAllDraftClassPlayers(): array
    {
        return $this->fetchAll("SELECT * FROM ibl_draft_class ORDER BY drafted, name");
    }

    /**
     * @see DraftRepositoryInterface::getCurrentDraftPick()
     */
    public function getCurrentDraftPick(): ?array
    {
        $row = $this->fetchOne(
            "SELECT * FROM ibl_draft WHERE player = '' ORDER BY round ASC, pick ASC LIMIT 1"
        );
        
        if ($row) {
            return [
                'team' => $row['team'],
                'round' => $row['round'],
                'pick' => $row['pick']
            ];
        }
        
        return null;
    }
}
