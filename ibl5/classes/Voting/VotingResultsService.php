<?php

declare(strict_types=1);

namespace Voting;

use Voting\Contracts\VotingResultsServiceInterface;

/**
 * @phpstan-import-type VoteRow from VotingResultsServiceInterface
 * @phpstan-import-type VoteTable from VotingResultsServiceInterface
 *
 * @see VotingResultsServiceInterface
 */
class VotingResultsService implements VotingResultsServiceInterface
{
    private const ASG_TABLE = 'ibl_votes_ASG';
    private const EOY_TABLE = 'ibl_votes_EOY';
    public const BLANK_BALLOT_LABEL = '(No Selection Recorded)';

    /** @var array<string, list<string>> */
    private const ALL_STAR_CATEGORIES = [
        'Eastern Conference Frontcourt' => ['East_F1', 'East_F2', 'East_F3', 'East_F4'],
        'Eastern Conference Backcourt' => ['East_B1', 'East_B2', 'East_B3', 'East_B4'],
        'Western Conference Frontcourt' => ['West_F1', 'West_F2', 'West_F3', 'West_F4'],
        'Western Conference Backcourt' => ['West_B1', 'West_B2', 'West_B3', 'West_B4'],
    ];

    /** @var array<string, array<string, int>> */
    private const END_OF_YEAR_CATEGORIES = [
        'Most Valuable Player' => ['MVP_1' => 3, 'MVP_2' => 2, 'MVP_3' => 1],
        'Sixth Man of the Year' => ['Six_1' => 3, 'Six_2' => 2, 'Six_3' => 1],
        'Rookie of the Year' => ['ROY_1' => 3, 'ROY_2' => 2, 'ROY_3' => 1],
        'GM of the Year' => ['GM_1' => 3, 'GM_2' => 2, 'GM_3' => 1],
    ];

    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * @see VotingResultsServiceInterface::getAllStarResults()
     *
     * @return list<VoteTable>
     */
    public function getAllStarResults(): array
    {
        $results = [];
        foreach (self::ALL_STAR_CATEGORIES as $title => $columns) {
            $results[] = [
                'title' => $title,
                'rows' => $this->fetchAllStarTotals($columns),
            ];
        }
        return $results;
    }

    /**
     * @see VotingResultsServiceInterface::getEndOfYearResults()
     *
     * @return list<VoteTable>
     */
    public function getEndOfYearResults(): array
    {
        $results = [];
        foreach (self::END_OF_YEAR_CATEGORIES as $title => $ballots) {
            $results[] = [
                'title' => $title,
                'rows' => $this->fetchEndOfYearTotals($ballots),
            ];
        }

        return $results;
    }

    /**
     * @param list<string> $ballotColumns
     * @return list<VoteRow>
     */
    private function fetchAllStarTotals(array $ballotColumns): array
    {
        $query = $this->buildAllStarQuery($ballotColumns);

        return $this->executeVoteQuery($query);
    }

    /**
     * @param array<string, int> $ballotColumnsWithWeights
     * @return list<VoteRow>
     */
    private function fetchEndOfYearTotals(array $ballotColumnsWithWeights): array
    {
        $query = $this->buildEndOfYearQuery($ballotColumnsWithWeights);

        return $this->executeVoteQuery($query);
    }

    /**
     * @param list<string> $ballotColumns
     */
    private function buildAllStarQuery(array $ballotColumns): string
    {
        $selectStatements = [];
        foreach ($ballotColumns as $column) {
            $selectStatements[] = "SELECT {$column} AS name FROM " . self::ASG_TABLE;
        }

        $unionQuery = implode(' UNION ALL ', $selectStatements);

        $query = "SELECT COUNT(name) AS votes, name FROM ({$unionQuery}) AS ballot GROUP BY name HAVING COUNT(name) > 0 ORDER BY votes DESC, name ASC;";

        return $query;
    }

    /**
     * @param array<string, int> $ballotColumnsWithWeights
     */
    private function buildEndOfYearQuery(array $ballotColumnsWithWeights): string
    {
        $selectStatements = [];
        foreach ($ballotColumnsWithWeights as $column => $score) {
            $selectStatements[] = "SELECT {$column} AS name, {$score} AS score FROM " . self::EOY_TABLE;
        }

        $unionQuery = implode(' UNION ALL ', $selectStatements);

        $query = "SELECT SUM(score) AS votes, name FROM ({$unionQuery}) AS ballot GROUP BY name HAVING SUM(score) > 0 ORDER BY votes DESC, name ASC;";

        return $query;
    }

    /**
     * @return list<VoteRow>
     */
    private function executeVoteQuery(string $query): array
    {
        // Use mysqli with prepared statements (no parameters needed for these queries)
        $stmt = $this->db->prepare($query);
        if ($stmt === false) {
            error_log("VotingResultsService: Failed to prepare query: " . $this->db->error);
            return [];
        }

        if (!$stmt->execute()) {
            error_log("VotingResultsService: Failed to execute query: " . $stmt->error);
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        if ($result === false) {
            error_log("VotingResultsService: Failed to get result: " . $stmt->error);
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

        return $this->resolvePlayerIds($rows);
    }

    /**
     * Batch-resolve player IDs from names via ibl_plr table.
     *
     * @param list<VoteRow> $rows Rows with 'name' and 'votes' keys
     * @return list<VoteRow> Same rows with 'pid' resolved
     */
    private function resolvePlayerIds(array $rows): array
    {
        // Vote names are stored as "Player Name, Team" -- extract player name for lookup
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

        $uniqueNames = array_keys($playerNames);
        $placeholders = implode(',', array_fill(0, count($uniqueNames), '?'));
        $stmt = $this->db->prepare("SELECT pid, name FROM ibl_plr WHERE name IN ({$placeholders})");
        if ($stmt === false) {
            return $rows;
        }

        $stmt->execute($uniqueNames);
        $result = $stmt->get_result();

        if ($result === false) {
            $stmt->close();
            return $rows;
        }

        /** @var array<string, int> $pidMap */
        $pidMap = [];
        while (true) {
            $record = $result->fetch_assoc();
            if (!is_array($record)) {
                break;
            }
            $pidMap[(string) $record['name']] = (int) $record['pid'];
        }
        $stmt->close();

        foreach ($rows as &$row) {
            $playerName = $voteToPlayer[$row['name']] ?? '';
            $row['pid'] = $pidMap[$playerName] ?? 0;
        }

        return $rows;
    }

    /**
     * Extract the player name from a vote entry like "LeBron James, Sting".
     *
     * Strips the trailing ", TeamName" portion. If there is no comma, returns
     * the full string (handles GM names and other non-player entries).
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
