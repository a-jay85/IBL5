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
     * Cross-check ibl_league_config against ibl_franchise_seasons for a given season.
     *
     * @return list<string> List of discrepancy messages (empty = all consistent)
     */
    public function crossCheckWithFranchiseSeasons(int $seasonEndingYear): array
    {
        $discrepancies = [];

        $configRows = $this->repository->getConfigForSeason($seasonEndingYear);
        if ($configRows === []) {
            $discrepancies[] = 'No league config found for season ending ' . $seasonEndingYear;
            return $discrepancies;
        }

        $franchiseMap = $this->repository->getFranchiseTeamsBySeason($seasonEndingYear);

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
