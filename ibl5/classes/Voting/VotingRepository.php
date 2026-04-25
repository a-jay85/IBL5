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
        'east_f1', 'east_f2', 'east_f3', 'east_f4',
        'east_b1', 'east_b2', 'east_b3', 'east_b4',
        'west_f1', 'west_f2', 'west_f3', 'west_f4',
        'west_b1', 'west_b2', 'west_b3', 'west_b4',
        'mvp_1', 'mvp_2', 'mvp_3',
        'six_1', 'six_2', 'six_3',
        'roy_1', 'roy_2', 'roy_3',
        'gm_1', 'gm_2', 'gm_3',
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
             SET mvp_1 = ?, mvp_2 = ?, mvp_3 = ?,
                 six_1 = ?, six_2 = ?, six_3 = ?,
                 roy_1 = ?, roy_2 = ?, roy_3 = ?,
                 gm_1 = ?, gm_2 = ?, gm_3 = ?
             WHERE team_name = ?",
            'sssssssssssss',
            $ballot['mvp_1'], $ballot['mvp_2'], $ballot['mvp_3'],
            $ballot['six_1'], $ballot['six_2'], $ballot['six_3'],
            $ballot['roy_1'], $ballot['roy_2'], $ballot['roy_3'],
            $ballot['gm_1'], $ballot['gm_2'], $ballot['gm_3'],
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
             SET east_f1 = ?, east_f2 = ?, east_f3 = ?, east_f4 = ?,
                 east_b1 = ?, east_b2 = ?, east_b3 = ?, east_b4 = ?,
                 west_f1 = ?, west_f2 = ?, west_f3 = ?, west_f4 = ?,
                 west_b1 = ?, west_b2 = ?, west_b3 = ?, west_b4 = ?
             WHERE team_name = ?",
            'sssssssssssssssss',
            $ballot['east_f1'], $ballot['east_f2'], $ballot['east_f3'], $ballot['east_f4'],
            $ballot['east_b1'], $ballot['east_b2'], $ballot['east_b3'], $ballot['east_b4'],
            $ballot['west_f1'], $ballot['west_f2'], $ballot['west_f3'], $ballot['west_f4'],
            $ballot['west_b1'], $ballot['west_b2'], $ballot['west_b3'], $ballot['west_b4'],
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
