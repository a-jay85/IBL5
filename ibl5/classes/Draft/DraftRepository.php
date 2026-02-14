<?php

declare(strict_types=1);

namespace Draft;

use Draft\Contracts\DraftRepositoryInterface;

/**
 * @see DraftRepositoryInterface
 *
 * @phpstan-import-type DraftClassPlayerRow from DraftRepositoryInterface
 * @phpstan-import-type DraftPickRow from DraftRepositoryInterface
 */
class DraftRepository extends \BaseMysqliRepository implements DraftRepositoryInterface
{
    private \Services\CommonMysqliRepository $commonRepository;

    // Constants for player name matching
    const IBL_PLR_NAME_MAX_LENGTH = 32;  // Matches varchar(32) in ibl_plr.name
    const PARTIAL_NAME_MATCH_LENGTH = 30;  // For LIKE queries with diacritical differences

    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
        $this->commonRepository = new \Services\CommonMysqliRepository($db);
    }

    /**
     * @see DraftRepositoryInterface::getCurrentDraftSelection()
     */
    public function getCurrentDraftSelection(int $draftRound, int $draftPick): ?string
    {
        /** @var array{player: string}|null $row */
        $row = $this->fetchOne(
            "SELECT `player` FROM ibl_draft WHERE `round` = ? AND `pick` = ?",
            "ii",
            $draftRound,
            $draftPick
        );

        return $row !== null ? $row['player'] : null;
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

        /** @var array{max_pid: int|null}|null $row */
        $row = $this->fetchOne(
            "SELECT MAX(pid) as max_pid FROM ibl_plr WHERE pid >= ?",
            "i",
            $draftPidStart
        );

        if ($row !== null && array_key_exists('max_pid', $row)) {
            $maxPid = $row['max_pid'];
            if ($maxPid !== null && $maxPid >= $draftPidStart) {
                return $maxPid + 1;
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

        /** @var DraftClassPlayerRow|null $draftClassPlayer */
        $draftClassPlayer = $this->fetchOne(
            "SELECT * FROM ibl_draft_class WHERE name = ? LIMIT 1",
            "s",
            $playerName
        );

        if ($draftClassPlayer === null) {
            return false;
        }

        $pid = $this->getNextAvailablePid();
        $name = substr($playerName, 0, self::IBL_PLR_NAME_MAX_LENGTH);
        $pos = $draftClassPlayer['pos'];
        $oo = $draftClassPlayer['oo'];
        $od = $draftClassPlayer['od'];
        $po = $draftClassPlayer['po'];
        $to = $draftClassPlayer['to'];
        $do = $draftClassPlayer['do'];
        $dd = $draftClassPlayer['dd'];
        $pd = $draftClassPlayer['pd'];
        $td = $draftClassPlayer['td'];

        $age = $draftClassPlayer['age'];
        $sta = $draftClassPlayer['sta'] ?? 0;
        $talent = $draftClassPlayer['talent'];
        $skill = $draftClassPlayer['skill'];
        $intangibles = $draftClassPlayer['intangibles'];

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
        
        if ($row !== null) {
            return $row['drafted'] === '1' || $row['drafted'] === 1;
        }

        return false;
    }

    /**
     * @see DraftRepositoryInterface::getNextTeamOnClock()
     */
    public function getNextTeamOnClock(): ?string
    {
        /** @var array{team: string}|null $row */
        $row = $this->fetchOne(
            "SELECT team FROM ibl_draft WHERE player = '' ORDER BY round ASC, pick ASC LIMIT 1"
        );

        return $row !== null ? $row['team'] : null;
    }

    /**
     * @see DraftRepositoryInterface::getAllDraftClassPlayers()
     */
    public function getAllDraftClassPlayers(): array
    {
        /** @var list<DraftClassPlayerRow> */
        return $this->fetchAll("SELECT * FROM ibl_draft_class ORDER BY drafted, name");
    }

    /**
     * @see DraftRepositoryInterface::getCurrentDraftPick()
     */
    public function getCurrentDraftPick(): ?array
    {
        /** @var array{team: string, round: int, pick: int, player: string}|null $row */
        $row = $this->fetchOne(
            "SELECT * FROM ibl_draft WHERE player = '' ORDER BY round ASC, pick ASC LIMIT 1"
        );

        if ($row !== null) {
            return [
                'team' => $row['team'],
                'round' => $row['round'],
                'pick' => $row['pick'],
            ];
        }

        return null;
    }
}
