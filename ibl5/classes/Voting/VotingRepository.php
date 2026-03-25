<?php

declare(strict_types=1);

namespace Voting;

use Voting\Contracts\VotingRepositoryInterface;
use Voting\Contracts\VotingResultsServiceInterface;

/**
 * VotingRepository — All database access for the Voting module
 *
 * @phpstan-import-type VoteRow from VotingResultsServiceInterface
 * @phpstan-import-type EoyBallot from VotingRepositoryInterface
 * @phpstan-import-type AsgBallot from VotingRepositoryInterface
 */
class VotingRepository extends \BaseMysqliRepository implements VotingRepositoryInterface
{
    private const ASG_TABLE = 'ibl_votes_ASG';
    private const EOY_TABLE = 'ibl_votes_EOY';
    public const BLANK_BALLOT_LABEL = '(No Selection Recorded)';

    /** @var list<string> Allowlisted column names for dynamic SQL in vote queries */
    private const ALLOWED_COLUMNS = [
        'East_F1', 'East_F2', 'East_F3', 'East_F4',
        'East_B1', 'East_B2', 'East_B3', 'East_B4',
        'West_F1', 'West_F2', 'West_F3', 'West_F4',
        'West_B1', 'West_B2', 'West_B3', 'West_B4',
        'MVP_1', 'MVP_2', 'MVP_3',
        'Six_1', 'Six_2', 'Six_3',
        'ROY_1', 'ROY_2', 'ROY_3',
        'GM_1', 'GM_2', 'GM_3',
    ];

    /**
     * @see VotingRepositoryInterface::saveEoyVote()
     *
     * @param EoyBallot $ballot
     */
    public function saveEoyVote(string $teamName, array $ballot): void
    {
        $this->execute(
            "UPDATE ibl_votes_EOY
             SET MVP_1 = ?, MVP_2 = ?, MVP_3 = ?,
                 Six_1 = ?, Six_2 = ?, Six_3 = ?,
                 ROY_1 = ?, ROY_2 = ?, ROY_3 = ?,
                 GM_1 = ?, GM_2 = ?, GM_3 = ?
             WHERE team_name = ?",
            'sssssssssssss',
            $ballot['MVP_1'], $ballot['MVP_2'], $ballot['MVP_3'],
            $ballot['Six_1'], $ballot['Six_2'], $ballot['Six_3'],
            $ballot['ROY_1'], $ballot['ROY_2'], $ballot['ROY_3'],
            $ballot['GM_1'], $ballot['GM_2'], $ballot['GM_3'],
            $teamName
        );
    }

    /**
     * @see VotingRepositoryInterface::saveAsgVote()
     *
     * @param AsgBallot $ballot
     */
    public function saveAsgVote(string $teamName, array $ballot): void
    {
        $this->execute(
            "UPDATE ibl_votes_ASG
             SET East_F1 = ?, East_F2 = ?, East_F3 = ?, East_F4 = ?,
                 East_B1 = ?, East_B2 = ?, East_B3 = ?, East_B4 = ?,
                 West_F1 = ?, West_F2 = ?, West_F3 = ?, West_F4 = ?,
                 West_B1 = ?, West_B2 = ?, West_B3 = ?, West_B4 = ?
             WHERE team_name = ?",
            'sssssssssssssssss',
            $ballot['East_F1'], $ballot['East_F2'], $ballot['East_F3'], $ballot['East_F4'],
            $ballot['East_B1'], $ballot['East_B2'], $ballot['East_B3'], $ballot['East_B4'],
            $ballot['West_F1'], $ballot['West_F2'], $ballot['West_F3'], $ballot['West_F4'],
            $ballot['West_B1'], $ballot['West_B2'], $ballot['West_B3'], $ballot['West_B4'],
            $teamName
        );
    }

    /**
     * @see VotingRepositoryInterface::markEoyVoteCast()
     */
    public function markEoyVoteCast(string $teamName): void
    {
        $this->execute(
            "UPDATE ibl_team_info SET eoy_vote = NOW() + INTERVAL 2 HOUR WHERE team_name = ?",
            's',
            $teamName
        );
    }

    /**
     * @see VotingRepositoryInterface::markAsgVoteCast()
     */
    public function markAsgVoteCast(string $teamName): void
    {
        $this->execute(
            "UPDATE ibl_team_info SET asg_vote = NOW() + INTERVAL 2 HOUR WHERE team_name = ?",
            's',
            $teamName
        );
    }

    // ==================== Read Methods ====================

    /**
     * @see VotingRepositoryInterface::fetchAllStarTotals()
     *
     * @param list<string> $columns
     * @return list<VoteRow>
     */
    public function fetchAllStarTotals(array $columns): array
    {
        $this->validateColumns($columns);

        $selectStatements = [];
        foreach ($columns as $column) {
            $selectStatements[] = "SELECT {$column} AS name FROM " . self::ASG_TABLE;
        }

        $unionQuery = implode(' UNION ALL ', $selectStatements);
        $query = "SELECT COUNT(name) AS votes, name FROM ({$unionQuery}) AS ballot GROUP BY name HAVING COUNT(name) > 0 ORDER BY votes DESC, name ASC";

        $rows = $this->executeVoteQuery($query);

        return $this->resolvePlayerIds($rows);
    }

    /**
     * @see VotingRepositoryInterface::fetchEndOfYearTotals()
     *
     * @param array<string, int> $columnsWithWeights
     * @return list<VoteRow>
     */
    public function fetchEndOfYearTotals(array $columnsWithWeights): array
    {
        $this->validateColumns(array_keys($columnsWithWeights));

        $selectStatements = [];
        foreach ($columnsWithWeights as $column => $score) {
            $selectStatements[] = "SELECT {$column} AS name, {$score} AS score FROM " . self::EOY_TABLE;
        }

        $unionQuery = implode(' UNION ALL ', $selectStatements);
        $query = "SELECT SUM(score) AS votes, name FROM ({$unionQuery}) AS ballot GROUP BY name HAVING SUM(score) > 0 ORDER BY votes DESC, name ASC";

        $rows = $this->executeVoteQuery($query);

        return $this->resolvePlayerIds($rows);
    }

    /**
     * @see VotingRepositoryInterface::fetchPlayerIdsByNames()
     *
     * @param list<string> $names
     * @return array<string, int>
     */
    public function fetchPlayerIdsByNames(array $names): array
    {
        if ($names === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $types = str_repeat('s', count($names));
        $rows = $this->fetchAll(
            "SELECT pid, name FROM ibl_plr WHERE name IN ({$placeholders})",
            $types,
            ...$names
        );

        /** @var array<string, int> $pidMap */
        $pidMap = [];
        foreach ($rows as $row) {
            $name = is_string($row['name']) ? $row['name'] : '';
            $pid = is_int($row['pid']) ? $row['pid'] : 0;
            $pidMap[$name] = $pid;
        }

        return $pidMap;
    }

    // ==================== Private Helpers ====================

    /**
     * Validate that all column names are in the allowlist (defense-in-depth against SQL injection)
     *
     * @param list<string> $columns
     */
    private function validateColumns(array $columns): void
    {
        foreach ($columns as $column) {
            if (!in_array($column, self::ALLOWED_COLUMNS, true)) {
                throw new \InvalidArgumentException("Invalid vote column: {$column}");
            }
        }
    }

    /**
     * Execute a parameterless vote aggregation query and normalize results
     *
     * @return list<VoteRow>
     */
    private function executeVoteQuery(string $query): array
    {
        $stmt = $this->executeQuery($query, '');

        $result = $stmt->get_result();
        if ($result === false) {
            $stmt->close();
            return [];
        }

        /** @var list<VoteRow> $rows */
        $rows = [];
        while (true) {
            $record = $result->fetch_assoc();
            if (!is_array($record)) {
                break;
            }
            $name = trim((string) ($record['name'] ?? ''));
            if ($name === '') {
                $name = self::BLANK_BALLOT_LABEL;
            }

            $votes = (int) ($record['votes'] ?? 0);
            $rows[] = [
                'name' => $name,
                'votes' => $votes,
                'pid' => 0,
            ];
        }

        $stmt->close();

        return $rows;
    }

    /**
     * Batch-resolve player IDs from vote row names
     *
     * Vote names are stored as "Player Name, Team" — extracts the player name
     * for lookup, then assigns the pid back to each row.
     *
     * @param list<VoteRow> $rows
     * @return list<VoteRow>
     */
    private function resolvePlayerIds(array $rows): array
    {
        /** @var array<string, string> $voteToPlayer */
        $voteToPlayer = [];
        /** @var array<string, true> $playerNames */
        $playerNames = [];
        foreach ($rows as $row) {
            if ($row['name'] !== self::BLANK_BALLOT_LABEL) {
                $playerName = self::extractPlayerName($row['name']);
                $voteToPlayer[$row['name']] = $playerName;
                $playerNames[$playerName] = true;
            }
        }

        if ($playerNames === []) {
            return $rows;
        }

        $pidMap = $this->fetchPlayerIdsByNames(array_keys($playerNames));

        foreach ($rows as &$row) {
            $playerName = $voteToPlayer[$row['name']] ?? '';
            $row['pid'] = $pidMap[$playerName] ?? 0;
        }

        return $rows;
    }

    /**
     * Extract player name from "LeBron James, Sting" format
     *
     * Strips the trailing ", TeamName" portion. If there is no comma,
     * returns the full string (handles GM names).
     */
    private static function extractPlayerName(string $voteName): string
    {
        $lastComma = strrpos($voteName, ',');
        if ($lastComma === false) {
            return trim($voteName);
        }

        return trim(substr($voteName, 0, $lastComma));
    }
}
