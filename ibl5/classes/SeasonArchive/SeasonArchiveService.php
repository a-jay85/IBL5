<?php

declare(strict_types=1);

namespace SeasonArchive;

use SeasonArchive\Contracts\SeasonArchiveRepositoryInterface;
use SeasonArchive\Contracts\SeasonArchiveServiceInterface;

/**
 * SeasonArchiveService - Business logic for season archive data assembly
 *
 * Orchestrates data from multiple repository methods into structured arrays
 * for the view layer. Handles season numbering, award extraction, team award
 * HTML parsing, and Challonge URL generation.
 *
 * @phpstan-import-type SeasonSummary from SeasonArchiveServiceInterface
 * @phpstan-import-type SeasonDetail from SeasonArchiveServiceInterface
 * @phpstan-import-type PlayoffSeries from SeasonArchiveServiceInterface
 * @phpstan-import-type AwardRow from SeasonArchiveRepositoryInterface
 * @phpstan-import-type GmAwardWithTeamRow from SeasonArchiveRepositoryInterface
 * @phpstan-import-type GmTenureWithTeamRow from SeasonArchiveRepositoryInterface
 * @phpstan-import-type TeamAwardRow from SeasonArchiveRepositoryInterface
 *
 * @see SeasonArchiveServiceInterface For the interface contract
 */
class SeasonArchiveService implements SeasonArchiveServiceInterface
{
    private const ROMAN_NUMERALS = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V',
        6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X',
        11 => 'XI', 12 => 'XII', 13 => 'XIII', 14 => 'XIV', 15 => 'XV',
        16 => 'XVI', 17 => 'XVII', 18 => 'XVIII',
    ];

    /** @var int Season I ending year (league founded 1988, first season ends 1989) */
    private const FIRST_ENDING_YEAR = 1989;

    private SeasonArchiveRepositoryInterface $repository;

    public function __construct(SeasonArchiveRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see SeasonArchiveServiceInterface::getAllSeasons()
     *
     * @return list<SeasonSummary>
     */
    public function getAllSeasons(): array
    {
        $years = $this->repository->getAllSeasonYears();
        $seasons = [];

        foreach ($years as $year) {
            // Skip year 1988 — it's pre-season data, not a full season
            if ($year < self::FIRST_ENDING_YEAR) {
                continue;
            }

            $awards = $this->repository->getAwardsByYear($year);
            $playoffResults = $this->repository->getPlayoffResultsByYear($year);
            $heatYear = $year - 1;
            $teamAwards = $this->repository->getTeamAwardsByYear($year);

            $iblChampion = $this->getIblChampionFromPlayoffs($playoffResults);
            $heatChampion = $this->getHeatChampionFromTeamAwards($teamAwards);
            $mvp = $this->extractAward($awards, 'Most Valuable Player (1st)');

            $seasons[] = [
                'year' => $year,
                'label' => $this->buildSeasonLabel($year),
                'iblChampion' => $iblChampion,
                'heatChampion' => $heatChampion,
                'mvp' => $mvp,
            ];
        }

        // Sort by year descending (most recent first)
        usort($seasons, static function (array $a, array $b): int {
            return $b['year'] <=> $a['year'];
        });

        return $seasons;
    }

    /**
     * @see SeasonArchiveServiceInterface::getSeasonDetail()
     *
     * @return SeasonDetail|null
     */
    public function getSeasonDetail(int $year): ?array
    {
        $seasonNumber = $year - (self::FIRST_ENDING_YEAR - 1);
        if ($seasonNumber < 1 || $seasonNumber > 18) {
            return null;
        }

        $awards = $this->repository->getAwardsByYear($year);
        if ($awards === []) {
            return null;
        }

        $playoffResults = $this->repository->getPlayoffResultsByYear($year);
        $teamAwards = $this->repository->getTeamAwardsByYear($year);
        $gmAwards = $this->repository->getAllGmAwardsWithTeams();
        $gmTenures = $this->repository->getAllGmTenuresWithTeams();
        $heatYear = $year - 1;
        $heatStandingsRaw = $this->repository->getHeatWinLossByYear($heatYear);
        $teamColors = $this->repository->getTeamColors();
        $teamConferences = $this->repository->getTeamConferences();

        // Build playoff bracket grouped by round
        $playoffBracket = $this->buildPlayoffBracket($playoffResults);

        // Get IBL Finals (round 4)
        $iblFinals = $this->getIblFinals($playoffResults);

        // Parse team awards
        $parsedTeamAwards = $this->parseTeamAwards($teamAwards);

        // Build HEAT standings
        $heatStandings = [];
        foreach ($heatStandingsRaw as $row) {
            $heatStandings[] = [
                'team' => (string) $row['currentname'],
                'wins' => (int) $row['wins'],
                'losses' => (int) $row['losses'],
            ];
        }

        // Build teamIds map from teamColors (which now includes teamid)
        $teamIds = [];
        foreach ($teamColors as $teamName => $colorData) {
            $teamIds[$teamName] = $colorData['teamid'];
        }

        // Assemble the season data first, then collect all player names
        $seasonData = [
            'year' => $year,
            'label' => $this->buildSeasonLabel($year),
            'tournaments' => [
                'heatChampion' => $this->getHeatChampionFromTeamAwards($teamAwards),
                'heatUrl' => $this->getChallongeUrl('heat', $year),
                'oneOnOneChampion' => $this->extractAward($awards, 'One-on-One Tournament Champion'),
                'rookieOneOnOneChampion' => $this->extractAward($awards, 'Rookie One-on-One Tournament Champion'),
                'oneOnOneUrl' => 'https://challonge.com/users/coldbeatle89/tournaments',
                'iblFinalsWinner' => $iblFinals['winner'],
                'iblFinalsLoser' => $iblFinals['loser'],
                'iblFinalsLoserGames' => $iblFinals['loserGames'],
                'playoffsUrl' => $this->getChallongeUrl('playoffs', $year),
            ],
            'allStarWeekend' => [
                'gameMvps' => array_merge(
                    $this->extractAwardList($awards, 'All-Star Game MVP'),
                    $this->extractAwardList($awards, 'All-Star Game Co-MVP'),
                ),
                'slamDunkWinner' => $this->extractAward($awards, 'Slam Dunk Competition - Winner'),
                'threePointWinner' => $this->extractAward($awards, 'Three-Point Contest - Winner'),
                'rookieSophomoreMvp' => $this->extractAward($awards, 'Rookie-Sophomore Challenge - MVP'),
                'slamDunkParticipants' => $this->extractAwardList($awards, 'Slam Dunk Competition'),
                'threePointParticipants' => $this->extractAwardList($awards, 'Three-Point Contest'),
                'rookieSophomoreParticipants' => $this->extractAwardList($awards, 'Rookie-Sophomore Challenge'),
            ],
            'majorAwards' => [
                'mvp' => $this->extractAward($awards, 'Most Valuable Player (1st)'),
                'dpoy' => $this->extractAward($awards, 'Defensive Player of the Year (1st)'),
                'roy' => $this->extractAward($awards, 'Rookie of the Year (1st)'),
                'sixthMan' => $this->extractAward($awards, '6th Man Award (1st)'),
                'gmOfYear' => $this->getGmOfTheYear($gmAwards, $year),
                'finalsMvp' => $this->extractAward($awards, 'IBL Finals MVP'),
            ],
            'allLeagueTeams' => [
                'first' => $this->extractAwardList($awards, 'All-League First Team'),
                'second' => $this->extractAwardList($awards, 'All-League Second Team'),
                'third' => $this->extractAwardList($awards, 'All-League Third Team'),
            ],
            'allDefensiveTeams' => [
                'first' => $this->extractAwardList($awards, 'All-Defensive Team (1st)'),
                'second' => $this->extractAwardList($awards, 'All-Defensive Team (2nd)'),
                'third' => $this->extractAwardList($awards, 'All-Defensive Team (3rd)'),
            ],
            'allRookieTeams' => [
                'first' => $this->extractAwardList($awards, 'All-Rookie Team (1st)'),
                'second' => $this->extractAwardList($awards, 'All-Rookie Team (2nd)'),
                'third' => $this->extractAwardList($awards, 'All-Rookie Team (3rd)'),
            ],
            'statisticalLeaders' => [
                'scoring' => $this->extractAward($awards, 'Scoring Leader (1st)'),
                'rebounds' => $this->extractAward($awards, 'Rebounding Leader (1st)'),
                'assists' => $this->extractAward($awards, 'Assists Leader (1st)'),
                'steals' => $this->extractAward($awards, 'Steals Leader (1st)'),
                'blocks' => $this->extractAward($awards, 'Blocks Leader (1st)'),
            ],
            'playoffBracket' => $playoffBracket,
            'heatStandings' => $heatStandings,
            'teamAwards' => $parsedTeamAwards,
            'championRosters' => [
                'ibl' => $this->extractAwardList($awards, 'IBL Champion'),
                'heat' => $this->extractAwardList($awards, 'IBL HEAT Championship'),
            ],
            'allStarRosters' => [
                'east' => $this->extractAwardList($awards, 'Eastern Conference All-Star'),
                'west' => $this->extractAwardList($awards, 'Western Conference All-Star'),
            ],
            'allStarCoaches' => $this->getAllStarCoaches($gmAwards, $year, $teamConferences),
            'iblChampionCoach' => $this->getIblChampionCoach($gmTenures, $iblFinals['winner'], $year),
            'teamColors' => $teamColors,
            'playerIds' => [],
            'teamIds' => $teamIds,
        ];

        // Collect all unique player names from the assembled data
        $playerNames = $this->collectPlayerNames($seasonData);
        $seasonData['playerIds'] = $this->repository->getPlayerIdsByNames($playerNames);

        return $seasonData;
    }

    /**
     * Build season label (e.g., "Season I (1988-89)")
     */
    public function buildSeasonLabel(int $year): string
    {
        $seasonNumber = $year - (self::FIRST_ENDING_YEAR - 1);
        $roman = self::ROMAN_NUMERALS[$seasonNumber] ?? (string) $seasonNumber;
        $startYear = $year - 1;
        $endYearShort = substr((string) $year, 2);

        return 'Season ' . $roman . ' (' . $startYear . '-' . $endYearShort . ')';
    }

    /**
     * Extract a single award winner name by exact award name match
     *
     * Uses trim() to handle trailing whitespace in award names (known data issue).
     *
     * @param list<AwardRow> $awards All awards for a year
     * @param string $awardName Exact award name to match
     * @return string Winner name, or empty string if not found
     */
    private function extractAward(array $awards, string $awardName): string
    {
        foreach ($awards as $award) {
            if (trim($award['Award']) === $awardName) {
                return trim($award['name']);
            }
        }

        return '';
    }

    /**
     * Extract all player names for a given award name
     *
     * Uses trim() to handle trailing whitespace in award names (known data issue).
     *
     * @param list<AwardRow> $awards All awards for a year
     * @param string $awardName Award name to match (exact match after trim)
     * @return list<string> List of player names
     */
    private function extractAwardList(array $awards, string $awardName): array
    {
        $names = [];
        foreach ($awards as $award) {
            if (trim($award['Award']) === $awardName) {
                $names[] = trim($award['name']);
            }
        }

        return $names;
    }

    /**
     * Find GM of the Year from normalized GM awards data
     *
     * @param list<GmAwardWithTeamRow> $gmAwards All GM award records with team names
     * @param int $year Season ending year to find
     * @return array{name: string, team: string} GM name and team, or empty strings if not found
     */
    private function getGmOfTheYear(array $gmAwards, int $year): array
    {
        foreach ($gmAwards as $award) {
            if ($award['Award'] === 'GM of the Year' && $award['year'] === $year) {
                return ['name' => $award['gm_username'], 'team' => $award['team_name']];
            }
        }

        return ['name' => '', 'team' => ''];
    }

    /**
     * Get All-Star Game head coaches for a given year, split by conference
     *
     * Matches 'ASG Head Coach' and 'ASG Co-Head Coach' awards for the given year.
     * Uses the team_name from the JOIN to determine conference.
     *
     * @param list<GmAwardWithTeamRow> $gmAwards All GM award records with team names
     * @param int $year Season ending year
     * @param array<string, string> $teamConferences Map of team_name => 'Eastern'|'Western'
     * @return array{east: list<string>, west: list<string>}
     */
    private function getAllStarCoaches(array $gmAwards, int $year, array $teamConferences): array
    {
        /** @var list<string> $east */
        $east = [];
        /** @var list<string> $west */
        $west = [];

        foreach ($gmAwards as $award) {
            if ($award['year'] !== $year) {
                continue;
            }

            if ($award['Award'] !== 'ASG Head Coach' && $award['Award'] !== 'ASG Co-Head Coach') {
                continue;
            }

            $conference = $teamConferences[$award['team_name']] ?? '';

            if ($conference === 'Eastern') {
                $east[] = $award['gm_username'];
            } elseif ($conference === 'Western') {
                $west[] = $award['gm_username'];
            }
        }

        return ['east' => $east, 'west' => $west];
    }

    /**
     * Get the head coach (GM) of the IBL champion team for a given year
     *
     * Finds the GM whose team_name matches the champion and whose tenure covers the year.
     *
     * @param list<GmTenureWithTeamRow> $gmTenures All GM tenure records with team names
     * @param string $championTeam IBL Finals winner team name
     * @param int $year Season ending year
     * @return string GM username, or empty string if not found
     */
    private function getIblChampionCoach(array $gmTenures, string $championTeam, int $year): string
    {
        if ($championTeam === '') {
            return '';
        }

        foreach ($gmTenures as $tenure) {
            if ($tenure['team_name'] !== $championTeam) {
                continue;
            }

            if ($year < $tenure['start_season_year']) {
                continue;
            }

            if ($tenure['end_season_year'] !== null && $year > $tenure['end_season_year']) {
                continue;
            }

            return $tenure['gm_username'];
        }

        return '';
    }

    /**
     * Parse team awards from raw HTML data
     *
     * The ibl_team_awards table has HTML-contaminated data:
     * - Award field: "<B>Atlantic Division Champions</b>"
     * - Multiple awards may be concatenated with <BR>
     *
     * @param list<TeamAwardRow> $teamAwardRows Raw team award rows
     * @return array<string, string> Map of award label => team name
     */
    private function parseTeamAwards(array $teamAwardRows): array
    {
        $awards = [];

        foreach ($teamAwardRows as $row) {
            $rawAward = $row['Award'];
            $teamName = $row['name'];

            // Strip HTML tags and split by common delimiters
            $cleanAward = strip_tags($rawAward);
            $parts = preg_split('/\s*(?:\r?\n)+\s*/', trim($cleanAward));

            if (!is_array($parts)) {
                $parts = [trim($cleanAward)];
            }

            foreach ($parts as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $awards[$part] = $teamName;
                }
            }
        }

        return $awards;
    }

    /**
     * Collect all unique player names from assembled season data for batch ID lookup
     *
     * @param array<string, mixed> $data Assembled season data
     * @return list<string> Unique non-empty player names
     */
    private function collectPlayerNames(array $data): array
    {
        /** @var array<string, true> $names */
        $names = [];

        // Major Awards (single player names)
        /** @var array{mvp: string, dpoy: string, roy: string, sixthMan: string, gmOfYear: array{name: string, team: string}, finalsMvp: string} $awards */
        $awards = $data['majorAwards'];
        foreach ([$awards['mvp'], $awards['dpoy'], $awards['roy'], $awards['sixthMan'], $awards['finalsMvp']] as $name) {
            if ($name !== '') {
                $names[$name] = true;
            }
        }

        // Statistical Leaders
        /** @var array{scoring: string, rebounds: string, assists: string, steals: string, blocks: string} $leaders */
        $leaders = $data['statisticalLeaders'];
        foreach ([$leaders['scoring'], $leaders['rebounds'], $leaders['assists'], $leaders['steals'], $leaders['blocks']] as $name) {
            if ($name !== '') {
                $names[$name] = true;
            }
        }

        // All-Star Weekend winners
        /** @var array{gameMvps: list<string>, slamDunkWinner: string, threePointWinner: string, rookieSophomoreMvp: string, slamDunkParticipants: list<string>, threePointParticipants: list<string>, rookieSophomoreParticipants: list<string>} $asw */
        $asw = $data['allStarWeekend'];
        foreach ($asw['gameMvps'] as $name) {
            if ($name !== '') {
                $names[$name] = true;
            }
        }
        foreach ([$asw['slamDunkWinner'], $asw['threePointWinner'], $asw['rookieSophomoreMvp']] as $name) {
            if ($name !== '') {
                $names[$name] = true;
            }
        }

        // Tournament player winners
        /** @var array{heatChampion: string, heatUrl: string, oneOnOneChampion: string, rookieOneOnOneChampion: string, oneOnOneUrl: string, iblFinalsWinner: string, iblFinalsLoser: string, iblFinalsLoserGames: int, playoffsUrl: string} $tournaments */
        $tournaments = $data['tournaments'];
        foreach ([$tournaments['oneOnOneChampion'], $tournaments['rookieOneOnOneChampion']] as $name) {
            if ($name !== '') {
                $names[$name] = true;
            }
        }

        // All-League/Defensive/Rookie teams (lists of player names)
        foreach (['allLeagueTeams', 'allDefensiveTeams', 'allRookieTeams'] as $teamKey) {
            /** @var array{first: list<string>, second: list<string>, third: list<string>} $teamSet */
            $teamSet = $data[$teamKey];
            foreach (['first', 'second', 'third'] as $tier) {
                foreach ($teamSet[$tier] as $name) {
                    if ($name !== '') {
                        $names[$name] = true;
                    }
                }
            }
        }

        // Championship Rosters
        /** @var array{ibl: list<string>, heat: list<string>} $championRosters */
        $championRosters = $data['championRosters'];
        foreach (array_merge($championRosters['ibl'], $championRosters['heat']) as $name) {
            if ($name !== '') {
                $names[$name] = true;
            }
        }

        // All-Star Rosters
        /** @var array{east: list<string>, west: list<string>} $allStarRosters */
        $allStarRosters = $data['allStarRosters'];
        foreach (array_merge($allStarRosters['east'], $allStarRosters['west']) as $name) {
            if ($name !== '') {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * Get IBL champion from playoff results (round 4 winner)
     *
     * @param list<array{year: int, round: int, winner: string, loser: string, winner_games: int, loser_games: int}> $playoffResults
     * @return string IBL champion team name, or empty string
     */
    private function getIblChampionFromPlayoffs(array $playoffResults): string
    {
        foreach ($playoffResults as $result) {
            if ($result['round'] === 4) {
                return $result['winner'];
            }
        }

        return '';
    }

    /**
     * Get HEAT champion from team awards
     *
     * @param list<TeamAwardRow> $teamAwards Team awards for the year
     * @return string HEAT champion team name, or empty string
     */
    private function getHeatChampionFromTeamAwards(array $teamAwards): string
    {
        foreach ($teamAwards as $row) {
            $cleanAward = strip_tags($row['Award']);
            if (stripos($cleanAward, 'HEAT Champion') !== false) {
                return $row['name'];
            }
        }

        return '';
    }

    /**
     * Build playoff bracket grouped by round
     *
     * @param list<array{year: int, round: int, winner: string, loser: string, winner_games: int, loser_games: int}> $playoffResults
     * @return array<int, list<PlayoffSeries>> Map of round => series list
     */
    private function buildPlayoffBracket(array $playoffResults): array
    {
        $bracket = [];

        foreach ($playoffResults as $result) {
            $round = $result['round'];
            if (!isset($bracket[$round])) {
                $bracket[$round] = [];
            }
            $bracket[$round][] = [
                'winner' => $result['winner'],
                'loser' => $result['loser'],
                'loserGames' => $result['loser_games'],
            ];
        }

        ksort($bracket);

        return $bracket;
    }

    /**
     * Get IBL Finals data (round 4)
     *
     * @param list<array{year: int, round: int, winner: string, loser: string, winner_games: int, loser_games: int}> $playoffResults
     * @return array{winner: string, loser: string, loserGames: int}
     */
    private function getIblFinals(array $playoffResults): array
    {
        foreach ($playoffResults as $result) {
            if ($result['round'] === 4) {
                return [
                    'winner' => $result['winner'],
                    'loser' => $result['loser'],
                    'loserGames' => $result['loser_games'],
                ];
            }
        }

        return ['winner' => '', 'loser' => '', 'loserGames' => 0];
    }

    /**
     * Generate Challonge bracket URL
     *
     * @param string $type 'heat' or 'playoffs'
     * @param int $year Season ending year
     * @return string Challonge URL
     */
    private function getChallongeUrl(string $type, int $year): string
    {
        if ($type === 'heat') {
            $heatYear = $year - 1;
            $twoDigitYear = substr((string) $heatYear, 2);
            // Exception: 1994 uses lowercase
            $prefix = ($heatYear === 1994) ? 'iblheat' : 'IBLheat';

            return 'https://challonge.com/' . $prefix . $twoDigitYear;
        }

        return 'https://challonge.com/iblplayoffs' . $year;
    }
}
