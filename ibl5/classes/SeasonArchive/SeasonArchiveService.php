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
 * @phpstan-import-type GmHistoryRow from SeasonArchiveRepositoryInterface
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
        $gmHistory = $this->repository->getAllGmHistory();
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
        $gmHistory = $this->repository->getAllGmHistory();
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
                'gameMvp' => $this->extractAward($awards, 'All-Star Game MVP'),
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
                'gmOfYear' => $this->getGmOfTheYear($gmHistory, $year),
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
            'allStarCoaches' => $this->getAllStarCoaches($gmHistory, $year, $teamConferences),
            'iblChampionCoach' => $this->getIblChampionCoach($gmHistory, $iblFinals['winner'], $year),
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
     * Parse GM of the Year from GM history HTML
     *
     * The Award field contains HTML like:
     * "<B>Name</B><BR>GM of the Year: 1990, 1993<BR>ASG Head Coach: ..."
     * We regex-match "GM of the Year:" lines and check if the year appears.
     *
     * @param list<GmHistoryRow> $gmHistory All GM history records
     * @param int $year Season ending year to find
     * @return array{name: string, team: string} GM name and team, or empty strings if not found
     */
    private function getGmOfTheYear(array $gmHistory, int $year): array
    {
        $yearStr = (string) $year;

        foreach ($gmHistory as $gm) {
            $awardText = $gm['Award'];
            // Match "GM of the Year: YYYY, YYYY, ..." pattern
            if (preg_match('/GM of the Year:\s*([0-9, ]+)/i', $awardText, $matches) === 1) {
                $yearsInRecord = preg_split('/\s*,\s*/', trim($matches[1]));
                if (is_array($yearsInRecord) && in_array($yearStr, $yearsInRecord, true)) {
                    $name = $gm['name'];
                    // The name field looks like "Ross Gates (Bulls)" — extract name and team
                    $gmName = $name;
                    $gmTeam = '';
                    if (preg_match('/^(.+?)\s*\((.+?)\)\s*$/', $name, $nameMatches) === 1) {
                        $gmName = trim($nameMatches[1]);
                        $gmTeam = trim($nameMatches[2]);
                    } else {
                        $gmName = trim(preg_replace('/\s*\(.*?\)\s*$/', '', $name) ?? $name);
                    }

                    return ['name' => $gmName, 'team' => $gmTeam];
                }
            }
        }

        return ['name' => '', 'team' => ''];
    }

    /**
     * Get All-Star Game head coaches for a given year, split by conference
     *
     * Parses "ASG Head Coach:" and "ASG Co-Head Coach:" lines from GM history
     * Award field HTML. Determines conference by extracting team name from the
     * `name` field (e.g., "Ross Gates (Bulls)") and looking it up in standings.
     *
     * @param list<GmHistoryRow> $gmHistory All GM history records
     * @param int $year Season ending year
     * @param array<string, string> $teamConferences Map of team_name => 'Eastern'|'Western'
     * @return array{east: list<string>, west: list<string>}
     */
    private function getAllStarCoaches(array $gmHistory, int $year, array $teamConferences): array
    {
        $yearStr = (string) $year;
        /** @var list<string> $east */
        $east = [];
        /** @var list<string> $west */
        $west = [];

        foreach ($gmHistory as $gm) {
            $awardText = $gm['Award'];
            $isCoach = false;

            // Check "ASG Head Coach:" line
            if (preg_match('/ASG Head Coach:\s*([0-9, ]+)/i', $awardText, $matches) === 1) {
                $yearsInRecord = preg_split('/\s*,\s*/', trim($matches[1]));
                if (is_array($yearsInRecord) && in_array($yearStr, $yearsInRecord, true)) {
                    $isCoach = true;
                }
            }

            // Check "ASG Co-Head Coach:" line
            if (!$isCoach && preg_match('/ASG Co-Head Coach:\s*([0-9, ]+)/i', $awardText, $matches) === 1) {
                $yearsInRecord = preg_split('/\s*,\s*/', trim($matches[1]));
                if (is_array($yearsInRecord) && in_array($yearStr, $yearsInRecord, true)) {
                    $isCoach = true;
                }
            }

            if (!$isCoach) {
                continue;
            }

            // Extract display name from <B>Name</B> in Award field
            $displayName = '';
            if (preg_match('/<B>([^<]+)<\/B>/i', $awardText, $nameMatch) === 1) {
                $displayName = trim($nameMatch[1]);
            }

            // Extract team from name field: "Ross Gates (Bulls)" => "Bulls"
            $teamName = '';
            if (preg_match('/\(([^)]+)\)\s*$/', $gm['name'], $teamMatch) === 1) {
                $teamName = trim($teamMatch[1]);
            }

            // Fall back to name field itself if no display name found
            if ($displayName === '') {
                $displayName = preg_replace('/\s*\(.*?\)\s*$/', '', $gm['name']) ?? $gm['name'];
                $displayName = trim($displayName);
            }

            // Determine conference from team
            $conference = $teamConferences[$teamName] ?? '';

            if ($conference === 'Eastern') {
                $east[] = $displayName;
            } elseif ($conference === 'Western') {
                $west[] = $displayName;
            }
        }

        return ['east' => $east, 'west' => $west];
    }

    /**
     * Get the head coach (GM) of the IBL champion team for a given year
     *
     * Parses the `year` field tenure range (e.g., "<B>1988-Present:</b>") and matches
     * the team from the `name` field against the IBL Finals winner. Returns the display
     * name from `<B>Name</B>` in the Award field.
     *
     * @param list<GmHistoryRow> $gmHistory All GM history records
     * @param string $championTeam IBL Finals winner team name
     * @param int $year Season ending year
     * @return string Coach display name, or empty string if not found
     */
    private function getIblChampionCoach(array $gmHistory, string $championTeam, int $year): string
    {
        if ($championTeam === '') {
            return '';
        }

        foreach ($gmHistory as $gm) {
            // Extract team from name field: "Brandon Tomyoy (Clippers)" => "Clippers"
            $teamName = '';
            if (preg_match('/\(([^)]+)\)\s*$/', $gm['name'], $teamMatch) === 1) {
                $teamName = trim($teamMatch[1]);
            }

            if ($teamName !== $championTeam) {
                continue;
            }

            // Parse tenure range from year field: "<B>1988-Present:</b>" or "<B>1990-1997:</b>"
            $yearField = strip_tags($gm['year']);
            if (preg_match('/(\d{4})\s*-\s*(\w+)/', $yearField, $tenureMatch) !== 1) {
                continue;
            }

            $startYear = (int) $tenureMatch[1];
            $endPart = $tenureMatch[2];
            $endYear = strtolower($endPart) === 'present' ? 9999 : (int) $endPart;

            if ($year < $startYear || $year > $endYear) {
                continue;
            }

            // Extract display name from <B>Name</B> in Award field
            if (preg_match('/<B>([^<]+)<\/B>/i', $gm['Award'], $nameMatch) === 1) {
                return trim($nameMatch[1]);
            }

            // Fall back to name field without team suffix
            $displayName = preg_replace('/\s*\(.*?\)\s*$/', '', $gm['name']) ?? $gm['name'];
            return trim($displayName);
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
        /** @var array{gameMvp: string, slamDunkWinner: string, threePointWinner: string, rookieSophomoreMvp: string, slamDunkParticipants: list<string>, threePointParticipants: list<string>, rookieSophomoreParticipants: list<string>} $asw */
        $asw = $data['allStarWeekend'];
        foreach ([$asw['gameMvp'], $asw['slamDunkWinner'], $asw['threePointWinner'], $asw['rookieSophomoreMvp']] as $name) {
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
     * @param list<array{year: int, round: int, winner: string, loser: string, loser_games: int, id: int}> $playoffResults
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
     * @param list<array{year: int, round: int, winner: string, loser: string, loser_games: int, id: int}> $playoffResults
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
     * @param list<array{year: int, round: int, winner: string, loser: string, loser_games: int, id: int}> $playoffResults
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
