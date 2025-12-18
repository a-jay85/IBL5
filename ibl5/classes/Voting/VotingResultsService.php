<?php

declare(strict_types=1);

namespace Voting;

use Voting\Contracts\VotingResultsServiceInterface;

/**
 * @see VotingResultsServiceInterface
 */
class VotingResultsService implements VotingResultsServiceInterface
{
    private const ASG_TABLE = 'ibl_votes_ASG';
    private const EOY_TABLE = 'ibl_votes_EOY';
    public const BLANK_BALLOT_LABEL = '(No Selection Recorded)';

    private const ALL_STAR_CATEGORIES = [
        'Eastern Conference Frontcourt' => ['East_F1', 'East_F2', 'East_F3', 'East_F4'],
        'Eastern Conference Backcourt' => ['East_B1', 'East_B2', 'East_B3', 'East_B4'],
        'Western Conference Frontcourt' => ['West_F1', 'West_F2', 'West_F3', 'West_F4'],
        'Western Conference Backcourt' => ['West_B1', 'West_B2', 'West_B3', 'West_B4'],
    ];

    private const END_OF_YEAR_CATEGORIES = [
        'Most Valuable Player' => ['MVP_1' => 3, 'MVP_2' => 2, 'MVP_3' => 1],
        'Sixth Man of the Year' => ['Six_1' => 3, 'Six_2' => 2, 'Six_3' => 1],
        'Rookie of the Year' => ['ROY_1' => 3, 'ROY_2' => 2, 'ROY_3' => 1],
        'GM of the Year' => ['GM_1' => 3, 'GM_2' => 2, 'GM_3' => 1],
    ];

    private $db;

    public function __construct(object $db)
    {
        $this->db = $db;
    }

    /**
     * @see VotingResultsServiceInterface::getAllStarResults()
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

    private function fetchAllStarTotals(array $ballotColumns): array
    {
        $query = $this->buildAllStarQuery($ballotColumns);

        return $this->executeVoteQuery($query);
    }

    private function fetchEndOfYearTotals(array $ballotColumnsWithWeights): array
    {
        $query = $this->buildEndOfYearQuery($ballotColumnsWithWeights);

        return $this->executeVoteQuery($query);
    }

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

    private function executeVoteQuery(string $query): array
    {
        // Support both legacy and modern database connections
        if (method_exists($this->db, 'sql_query')) {
            // LEGACY: mysql class with sql_* methods
            $result = $this->db->sql_query($query);
            if ($result === false) {
                return [];
            }

            $rows = [];
            while ($record = $result->fetch_assoc()) {
                $name = trim((string) ($record['name'] ?? ''));
                if ($name === '') {
                    $name = self::BLANK_BALLOT_LABEL;
                }

                $votes = (int) ($record['votes'] ?? 0);
                $rows[] = [
                    'name' => $name,
                    'votes' => $votes,
                ];
            }

            return $rows;
        }

        // MODERN: mysqli with prepared statements (no parameters needed for these queries)
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

        $rows = [];
        while ($record = $result->fetch_assoc()) {
            $name = trim((string) ($record['name'] ?? ''));
            if ($name === '') {
                $name = self::BLANK_BALLOT_LABEL;
            }

            $votes = (int) ($record['votes'] ?? 0);
            $rows[] = [
                'name' => $name,
                'votes' => $votes,
            ];
        }

        $stmt->close();
        return $rows;
    }
}
