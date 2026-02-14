<?php

declare(strict_types=1);

namespace LeagueConfig;

use LeagueConfig\Contracts\LeagueConfigRepositoryInterface;
use Utilities\LgeFileParser;

/**
 * Orchestrates .lge file parsing and storage.
 */
class LeagueConfigService
{
    private LeagueConfigRepositoryInterface $repository;

    public function __construct(LeagueConfigRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Parse a .lge file and store the league configuration in the database.
     *
     * @return array{success: bool, season_ending_year: int, teams_stored: int, messages: list<string>, error?: string}
     */
    public function processLgeFile(string $filePath): array
    {
        $messages = [];

        try {
            $parsed = LgeFileParser::parseFile($filePath);
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'season_ending_year' => 0,
                'teams_stored' => 0,
                'messages' => [],
                'error' => $e->getMessage(),
            ];
        }

        $header = $parsed['header'];
        $teams = $parsed['teams'];
        $season = $parsed['season'];
        $seasonEndingYear = $season['season_ending_year'];

        $messages[] = 'Season: ' . $season['season_beginning_year'] . '-' . substr((string) $seasonEndingYear, 2);
        $messages[] = 'Teams found: ' . count($teams);
        $messages[] = 'Phase: ' . $season['phase'];

        $playoffFormats = $header['playoff_formats'];
        $qualifierCount = $header['qualifier_count'];

        $rows = [];
        foreach ($teams as $team) {
            $rows[] = [
                'team_slot' => $team['slot'],
                'team_name' => $team['name'],
                'conference' => $team['conference'],
                'division' => $team['division'],
                'playoff_qualifiers_per_conf' => $qualifierCount,
                'playoff_round1_format' => $playoffFormats[0] ?? '',
                'playoff_round2_format' => $playoffFormats[1] ?? '',
                'playoff_round3_format' => $playoffFormats[2] ?? '',
                'playoff_round4_format' => $playoffFormats[3] ?? '',
                'team_count' => $season['team_count'],
            ];
        }

        $affected = $this->repository->upsertSeasonConfig($seasonEndingYear, $rows);
        $messages[] = 'Database rows affected: ' . $affected;

        return [
            'success' => true,
            'season_ending_year' => $seasonEndingYear,
            'teams_stored' => count($teams),
            'messages' => $messages,
        ];
    }

    /**
     * Check if league config exists for the current season.
     */
    public function hasConfigForCurrentSeason(int $seasonEndingYear): bool
    {
        return $this->repository->hasConfigForSeason($seasonEndingYear);
    }

    /**
     * Cross-check ibl_league_config against ibl_franchise_seasons for a given season.
     *
     * @return list<string> List of discrepancy messages (empty = all consistent)
     */
    public function crossCheckWithFranchiseSeasons(int $seasonEndingYear, \mysqli $db): array
    {
        $discrepancies = [];

        $configRows = $this->repository->getConfigForSeason($seasonEndingYear);
        if ($configRows === []) {
            $discrepancies[] = 'No league config found for season ending ' . $seasonEndingYear;
            return $discrepancies;
        }

        $stmt = $db->prepare(
            'SELECT franchise_id, team_name FROM ibl_franchise_seasons WHERE season_ending_year = ? ORDER BY franchise_id ASC',
        );
        if ($stmt === false) {
            $discrepancies[] = 'Failed to query ibl_franchise_seasons: ' . $db->error;
            return $discrepancies;
        }

        $stmt->bind_param('i', $seasonEndingYear);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result === false) {
            $discrepancies[] = 'Failed to get result set from ibl_franchise_seasons query';
            $stmt->close();
            return $discrepancies;
        }

        /** @var array<int, string> $franchiseMap */
        $franchiseMap = [];

        $row = $result->fetch_assoc();
        while (is_array($row)) {
            /** @var int $franchiseId */
            $franchiseId = $row['franchise_id'];
            /** @var string $teamName */
            $teamName = $row['team_name'];
            $franchiseMap[$franchiseId] = $teamName;
            $row = $result->fetch_assoc();
        }
        $stmt->close();

        if ($franchiseMap === []) {
            $discrepancies[] = 'No franchise_seasons data found for season ending ' . $seasonEndingYear;
            return $discrepancies;
        }

        foreach ($configRows as $configRow) {
            $slot = $configRow['team_slot'];
            $lgeName = $configRow['team_name'];

            if (!isset($franchiseMap[$slot])) {
                $discrepancies[] = "Slot {$slot} ({$lgeName}): no matching franchise_id in ibl_franchise_seasons";
                continue;
            }

            $franchiseName = $franchiseMap[$slot];
            if ($lgeName !== $franchiseName) {
                $discrepancies[] = "Slot {$slot}: .lge has \"{$lgeName}\", franchise_seasons has \"{$franchiseName}\"";
            }
        }

        foreach ($franchiseMap as $franchiseId => $franchiseName) {
            $found = false;
            foreach ($configRows as $configRow) {
                if ($configRow['team_slot'] === $franchiseId) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $discrepancies[] = "Franchise {$franchiseId} ({$franchiseName}): not present in .lge file";
            }
        }

        return $discrepancies;
    }
}
