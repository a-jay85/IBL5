<?php

declare(strict_types=1);

namespace Draft;

use Draft\Contracts\DraftRepositoryInterface;
use Services\DatabaseService;

/**
 * @see DraftRepositoryInterface
 */
class DraftRepository implements DraftRepositoryInterface
{
    private $db;
    private $commonRepository;

    // Constants for player name matching
    const IBL_PLR_NAME_MAX_LENGTH = 32;  // Matches varchar(32) in ibl_plr.name
    const PARTIAL_NAME_MATCH_LENGTH = 30;  // For LIKE queries with diacritical differences

    public function __construct($db)
    {
        $this->db = $db;
        $this->commonRepository = new \Services\CommonRepository($db);
    }

    /**
     * @see DraftRepositoryInterface::getCurrentDraftSelection()
     */
    public function getCurrentDraftSelection(int $draftRound, int $draftPick): ?string
    {
        $draftRound = DatabaseService::escapeString($this->db, (string)$draftRound);
        $draftPick = DatabaseService::escapeString($this->db, (string)$draftPick);

        $query = "SELECT `player`
            FROM ibl_draft
            WHERE `round` = '$draftRound' 
               AND `pick` = '$draftPick'";
        
        $result = $this->db->sql_query($query);
        
        if ($result && $this->db->sql_numrows($result) > 0) {
            return $this->db->sql_result($result, 0, 'player');
        }
        
        return null;
    }

    /**
     * @see DraftRepositoryInterface::updateDraftTable()
     */
    public function updateDraftTable(string $playerName, string $date, int $draftRound, int $draftPick): bool
    {
        $playerName = DatabaseService::escapeString($this->db, $playerName);
        $date = DatabaseService::escapeString($this->db, $date);
        $draftRound = DatabaseService::escapeString($this->db, (string)$draftRound);
        $draftPick = DatabaseService::escapeString($this->db, (string)$draftPick);

        $query = "UPDATE ibl_draft 
             SET `player` = '$playerName', 
                   `date` = '$date' 
            WHERE `round` = '$draftRound' 
               AND `pick` = '$draftPick'";
        
        $result = $this->db->sql_query($query);
        return (bool)$result;
    }

    /**
     * @see DraftRepositoryInterface::updateRookieTable()
     */
    public function updateRookieTable(string $playerName, string $teamName): bool
    {
        $playerName = DatabaseService::escapeString($this->db, $playerName);
        $teamName = DatabaseService::escapeString($this->db, $teamName);

        $query = "UPDATE `ibl_draft_class`
              SET `team` = '$teamName', 
               `drafted` = '1'
            WHERE `name` = '$playerName'";
        
        $result = $this->db->sql_query($query);
        return (bool)$result;
    }

    private function getNextAvailablePid(): int
    {
        $draftPidStart = 90000;
        
        $query = "SELECT MAX(pid) as max_pid FROM ibl_plr WHERE pid >= $draftPidStart";
        $result = $this->db->sql_query($query);
        
        if ($result && $this->db->sql_numrows($result) > 0) {
            $maxPid = $this->db->sql_result($result, 0, 'max_pid');
            if ($maxPid !== null && $maxPid !== '' && $maxPid >= $draftPidStart) {
                return (int) $maxPid + 1;
            }
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
        
        $playerNameEscaped = DatabaseService::escapeString($this->db, $playerName);
        $query = "SELECT * FROM ibl_draft_class WHERE name = '$playerNameEscaped' LIMIT 1";
        $result = $this->db->sql_query($query);
        if (!$result || $this->db->sql_numrows($result) === 0) {
            return false;
        }
        
        $draftClassPlayer = $this->db->sql_fetchrow($result);
        $pid = $this->getNextAvailablePid();
        $name = substr($playerName, 0, self::IBL_PLR_NAME_MAX_LENGTH);
        $nameEscaped = DatabaseService::escapeString($this->db, $name);
        $teamNameEscaped = DatabaseService::escapeString($this->db, $teamName);
        $pos = DatabaseService::escapeString($this->db, $draftClassPlayer['pos']);
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
        $query = "INSERT INTO ibl_plr (
            pid, name, age, tid, teamname, pos,
            sta, oo, od, po, `to`, `do`, dd, pd, td,
            talent, skill, intangibles,
            active, bird, exp, cy, cyt
        ) VALUES (
            $pid, '$nameEscaped', $age, $teamId, '$teamNameEscaped', '$pos',
            $sta, $oo, $od, $po, $to, $do, $dd, $pd, $td,
            $talent, $skill, $intangibles,
            1, 0, 0, 0, 0
        )";
        
        $result = $this->db->sql_query($query);
        return (bool)$result;
    }

    /**
     * @see DraftRepositoryInterface::isPlayerAlreadyDrafted()
     */
    public function isPlayerAlreadyDrafted(string $playerName): bool
    {
        $playerName = DatabaseService::escapeString($this->db, $playerName);

        $query = "SELECT drafted 
            FROM ibl_draft_class 
            WHERE name = '$playerName' 
            LIMIT 1";
        
        $result = $this->db->sql_query($query);
        
        if ($result && $this->db->sql_numrows($result) > 0) {
            $drafted = $this->db->sql_result($result, 0, 'drafted');
            return $drafted == '1' || $drafted === 1;
        }
        
        return false;
    }

    /**
     * @see DraftRepositoryInterface::getNextTeamOnClock()
     */
    public function getNextTeamOnClock(): ?string
    {
        $query = "SELECT team 
            FROM ibl_draft 
            WHERE player = '' 
            ORDER BY round ASC, pick ASC 
            LIMIT 1";
        
        $result = $this->db->sql_query($query);
        
        if ($result && $this->db->sql_numrows($result) > 0) {
            return $this->db->sql_result($result, 0, 'team');
        }
        
        return null;
    }

    /**
     * @see DraftRepositoryInterface::getAllDraftClassPlayers()
     */
    public function getAllDraftClassPlayers(): array
    {
        $query = "SELECT * FROM ibl_draft_class ORDER BY drafted, name";
        
        $result = $this->db->sql_query($query);
        $players = [];
        
        if ($result) {
            while ($row = $this->db->sql_fetchrow($result)) {
                $players[] = $row;
            }
        }
        
        return $players;
    }

    /**
     * @see DraftRepositoryInterface::getCurrentDraftPick()
     */
    public function getCurrentDraftPick(): ?array
    {
        $query = "SELECT * FROM ibl_draft WHERE player = '' ORDER BY round ASC, pick ASC LIMIT 1";
        
        $result = $this->db->sql_query($query);
        
        if ($result && $this->db->sql_numrows($result) > 0) {
            return [
                'team' => $this->db->sql_result($result, 0, 'team'),
                'round' => $this->db->sql_result($result, 0, 'round'),
                'pick' => $this->db->sql_result($result, 0, 'pick')
            ];
        }
        
        return null;
    }
}
