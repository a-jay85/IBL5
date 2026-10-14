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
class VotingRepository extends \Database\BaseMysqliRepository implements VotingRepositoryInterface
{
    public const BLANK_BALLOT_LABEL = '(No Selection Recorded)';

    /** @var list<string> Allowlisted ASG ballot columns (ibl_votes_ASG) */
    private const ASG_COLUMNS = [
        'east_f1', 'east_f2', 'east_f3', 'east_f4',
        'east_b1', 'east_b2', 'east_b3', 'east_b4',
        'west_f1', 'west_f2', 'west_f3', 'west_f4',
        'west_b1', 'west_b2', 'west_b3', 'west_b4',
    ];

    /** @var list<string> Allowlisted EOY ballot columns (ibl_votes_EOY) */
    private const EOY_COLUMNS = [
        'mvp_1', 'mvp_2', 'mvp_3',
        'six_1', 'six_2', 'six_3',
        'roy_1', 'roy_2', 'roy_3',
        'gm_1', 'gm_2', 'gm_3',
    ];

    /** One piece per ASG_COLUMNS entry, same order; each `?` is a 1/0 selected flag (unselected pieces yield zero rows). */
    private const ASG_BALLOT_UNION =
        'SELECT east_f1 AS name FROM `ibl_votes_ASG` WHERE ? = 1 UNION ALL '
        . 'SELECT east_f2 AS name FROM `ibl_votes_ASG` WHERE ? = 1 UNION ALL '
        . 'SELECT east_f3 AS name FROM `ibl_votes_ASG` WHERE ? = 1 UNION ALL '
        . 'SELECT east_f4 AS name FROM `ibl_votes_ASG` WHERE ? = 1 UNION ALL '
        . 'SELECT east_b1 AS name FROM `ibl_votes_ASG` WHERE ? = 1 UNION ALL '
        . 'SELECT east_b2 AS name FROM `ibl_votes_ASG` WHERE ? = 1 UNION ALL '
        . 'SELECT east_b3 AS name FROM `ibl_votes_ASG` WHERE ? = 1 UNION ALL '
        . 'SELECT east_b4 AS name FROM `ibl_votes_ASG` WHERE ? = 1 UNION ALL '
        . 'SELECT west_f1 AS name FROM `ibl_votes_ASG` WHERE ? = 1 UNION ALL '
        . 'SELECT west_f2 AS name FROM `ibl_votes_ASG` WHERE ? = 1 UNION ALL '
        . 'SELECT west_f3 AS name FROM `ibl_votes_ASG` WHERE ? = 1 UNION ALL '
        . 'SELECT west_f4 AS name FROM `ibl_votes_ASG` WHERE ? = 1 UNION ALL '
        . 'SELECT west_b1 AS name FROM `ibl_votes_ASG` WHERE ? = 1 UNION ALL '
        . 'SELECT west_b2 AS name FROM `ibl_votes_ASG` WHERE ? = 1 UNION ALL '
        . 'SELECT west_b3 AS name FROM `ibl_votes_ASG` WHERE ? = 1 UNION ALL '
        . 'SELECT west_b4 AS name FROM `ibl_votes_ASG` WHERE ? = 1';

    /** One piece per EOY_COLUMNS entry, same order; bound as (weight, selected flag). CAST keeps score integer-typed at prepare time so SUM(score) stays DECIMAL. */
    private const EOY_BALLOT_UNION =
        'SELECT mvp_1 AS name, CAST(? AS SIGNED) AS score FROM `ibl_votes_EOY` WHERE ? = 1 UNION ALL '
        . 'SELECT mvp_2 AS name, CAST(? AS SIGNED) AS score FROM `ibl_votes_EOY` WHERE ? = 1 UNION ALL '
        . 'SELECT mvp_3 AS name, CAST(? AS SIGNED) AS score FROM `ibl_votes_EOY` WHERE ? = 1 UNION ALL '
        . 'SELECT six_1 AS name, CAST(? AS SIGNED) AS score FROM `ibl_votes_EOY` WHERE ? = 1 UNION ALL '
        . 'SELECT six_2 AS name, CAST(? AS SIGNED) AS score FROM `ibl_votes_EOY` WHERE ? = 1 UNION ALL '
        . 'SELECT six_3 AS name, CAST(? AS SIGNED) AS score FROM `ibl_votes_EOY` WHERE ? = 1 UNION ALL '
        . 'SELECT roy_1 AS name, CAST(? AS SIGNED) AS score FROM `ibl_votes_EOY` WHERE ? = 1 UNION ALL '
        . 'SELECT roy_2 AS name, CAST(? AS SIGNED) AS score FROM `ibl_votes_EOY` WHERE ? = 1 UNION ALL '
        . 'SELECT roy_3 AS name, CAST(? AS SIGNED) AS score FROM `ibl_votes_EOY` WHERE ? = 1 UNION ALL '
        . 'SELECT gm_1 AS name, CAST(? AS SIGNED) AS score FROM `ibl_votes_EOY` WHERE ? = 1 UNION ALL '
        . 'SELECT gm_2 AS name, CAST(? AS SIGNED) AS score FROM `ibl_votes_EOY` WHERE ? = 1 UNION ALL '
        . 'SELECT gm_3 AS name, CAST(? AS SIGNED) AS score FROM `ibl_votes_EOY` WHERE ? = 1';

    /**
     * @see VotingRepositoryInterface::saveEoyVote()
     *
     * @param EoyBallot $ballot
     */
    public function saveEoyVote(string $teamName, array $ballot): void
    {
        $this->execute(
            "UPDATE `ibl_votes_EOY`
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
            "UPDATE `ibl_votes_ASG`
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
            "UPDATE `ibl_team_info` SET eoy_vote = NOW() + INTERVAL 2 HOUR WHERE team_name = ?",
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
            "UPDATE `ibl_team_info` SET asg_vote = NOW() + INTERVAL 2 HOUR WHERE team_name = ?",
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
        $this->assertValidColumns($columns, self::ASG_COLUMNS);

        $params = [];
        foreach (self::ASG_COLUMNS as $column) {
            $params[] = in_array($column, $columns, true) ? 1 : 0;
        }

        $query = "SELECT COUNT(name) AS votes, name FROM (" . self::ASG_BALLOT_UNION . ") AS ballot GROUP BY name HAVING COUNT(name) > 0 ORDER BY votes DESC, name ASC";

        $rows = $this->executeVoteQuery($query, str_repeat('i', 16), ...$params);

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
        $this->assertValidColumns(array_keys($columnsWithWeights), self::EOY_COLUMNS);

        $params = [];
        foreach (self::EOY_COLUMNS as $column) {
            $params[] = (int) ($columnsWithWeights[$column] ?? 0);
            $params[] = array_key_exists($column, $columnsWithWeights) ? 1 : 0;
        }

        $query = "SELECT SUM(score) AS votes, name FROM (" . self::EOY_BALLOT_UNION . ") AS ballot GROUP BY name HAVING SUM(score) > 0 ORDER BY votes DESC, name ASC";

        $rows = $this->executeVoteQuery($query, str_repeat('ii', 12), ...$params);

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
        $rows = $this->fetchAllInList(
            "SELECT pid, name FROM `ibl_plr` WHERE name IN ({IN})",
            's',
            $names
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
     * Reject any column outside the table's allowlist, and duplicates
     *
     * @param list<int|string> $columns
     * @param list<string> $allowed
     */
    private function assertValidColumns(array $columns, array $allowed): void
    {
        foreach ($columns as $column) {
            if (!in_array($column, $allowed, true)) {
                throw new \InvalidArgumentException("Invalid vote column: {$column}");
            }
        }

        if (count($columns) !== count(array_unique($columns))) {
            throw new \InvalidArgumentException('Duplicate vote column');
        }
    }

    /**
     * Execute a vote aggregation query and normalize results
     *
     * @return list<VoteRow>
     */
    private function executeVoteQuery(string $query, string $types = '', int|string ...$params): array
    {
        $stmt = $this->executeQuery($query, $types, ...$params);

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
                $playerName = VotingResultsService::extractPlayerName($row['name']);
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
}
