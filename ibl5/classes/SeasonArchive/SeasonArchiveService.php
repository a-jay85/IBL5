<?php

declare(strict_types=1);

namespace SeasonArchive;

use SeasonArchive\Contracts\SeasonArchiveRepositoryInterface;
use SeasonArchive\Contracts\SeasonArchiveServiceInterface;

/**
 * SeasonArchiveService - Business logic for season archive data assembly
 *
 * Orchestrates data from multiple repository methods into structured arrays
 * for the view layer. Handles season numbering and Challonge URL generation,
 * delegating award extraction and playoff-bracket assembly to SeasonAwardExtractor
 * and SeasonPlayoffBracketBuilder.
 *
 * @phpstan-import-type SeasonSummary from SeasonArchiveServiceInterface
 * @phpstan-import-type SeasonDetail from SeasonArchiveServiceInterface
 *
 * @see SeasonArchiveServiceInterface For the interface contract
 */
class SeasonArchiveService implements SeasonArchiveServiceInterface
{
    /** @var int Season I ending year (league founded 1988, first season ends 1989) */
    private const FIRST_ENDING_YEAR = 1989;

    private SeasonArchiveRepositoryInterface $repository;

    private SeasonAwardExtractor $awardExtractor;

    private SeasonPlayoffBracketBuilder $bracketBuilder;

    public function __construct(SeasonArchiveRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->awardExtractor = new SeasonAwardExtractor();
        $this->bracketBuilder = new SeasonPlayoffBracketBuilder();
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
            $teamAwards = $this->repository->getTeamAwardsByYear($year, $heatYear);

            $iblChampion = $this->bracketBuilder->getIblChampionFromPlayoffs($playoffResults);
            $heatChampion = $this->awardExtractor->getHeatChampionFromTeamAwards($teamAwards);
            $mvp = $this->awardExtractor->extractAward($awards, 'Most Valuable Player (1st)');

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
        if ($seasonNumber < 1) {
            return null;
        }

        $awards = $this->repository->getAwardsByYear($year);
        if ($awards === []) {
            return null;
        }

        $playoffResults = $this->repository->getPlayoffResultsByYear($year);
        $heatYear = $year - 1;
        $teamAwards = $this->repository->getTeamAwardsByYear($year, $heatYear);
        $gmAwards = $this->repository->getAllGmAwardsWithTeams();
        $gmTenures = $this->repository->getAllGmTenuresWithTeams();
        $heatStandingsRaw = $this->repository->getHeatWinLossByYear($heatYear);
        $teamColors = $this->repository->getTeamColors();
        $teamConferences = $this->repository->getTeamConferences();

        // Build playoff bracket grouped by round
        $playoffBracket = $this->bracketBuilder->buildPlayoffBracket($playoffResults);

        // Get IBL Finals (round 4)
        $iblFinals = $this->bracketBuilder->getIblFinals($playoffResults);

        // Parse team awards (includes HEAT champion from box scores)
        $parsedTeamAwards = $this->awardExtractor->parseTeamAwards($teamAwards);

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

        // Player names collected during assembly, consumed below for player-ID lookup
        /** @var array<string, true> $collectedPlayerNames */
        $collectedPlayerNames = [];

        $seasonData = [
            'year' => $year,
            'label' => $this->buildSeasonLabel($year),
            'tournaments' => [
                'heatChampion' => $this->awardExtractor->getHeatChampionFromTeamAwards($teamAwards),
                'heatUrl' => $this->getChallongeUrl('heat', $year),
                'oneOnOneChampion' => $this->awardExtractor->extractAward($awards, 'One-on-One Tournament Champion', $collectedPlayerNames),
                'rookieOneOnOneChampion' => $this->awardExtractor->extractAward($awards, 'Rookie One-on-One Tournament Champion', $collectedPlayerNames),
                'oneOnOneUrl' => 'https://challonge.com/users/coldbeatle89/tournaments',
                'iblFinalsWinner' => $iblFinals['winner'],
                'iblFinalsLoser' => $iblFinals['loser'],
                'iblFinalsLoserGames' => $iblFinals['loserGames'],
                'playoffsUrl' => $this->getChallongeUrl('playoffs', $year),
            ],
            'allStarWeekend' => [
                'gameMvps' => array_merge(
                    $this->awardExtractor->extractAwardList($awards, 'All-Star Game MVP', $collectedPlayerNames),
                    $this->awardExtractor->extractAwardList($awards, 'All-Star Game Co-MVP', $collectedPlayerNames),
                ),
                'slamDunkWinner' => $this->awardExtractor->extractAward($awards, 'Slam Dunk Competition - Winner', $collectedPlayerNames),
                'threePointWinner' => $this->awardExtractor->extractAward($awards, 'Three-Point Contest - Winner', $collectedPlayerNames),
                'rookieSophomoreMvp' => $this->awardExtractor->extractAward($awards, 'Rookie-Sophomore Challenge - MVP', $collectedPlayerNames),
                'slamDunkParticipants' => $this->awardExtractor->extractAwardList($awards, 'Slam Dunk Competition', $collectedPlayerNames),
                'threePointParticipants' => $this->awardExtractor->extractAwardList($awards, 'Three-Point Contest', $collectedPlayerNames),
                'rookieSophomoreParticipants' => $this->awardExtractor->extractAwardList($awards, 'Rookie-Sophomore Challenge', $collectedPlayerNames),
            ],
            'majorAwards' => [
                'mvp' => $this->awardExtractor->extractAward($awards, 'Most Valuable Player (1st)', $collectedPlayerNames),
                'dpoy' => $this->awardExtractor->extractAward($awards, 'Defensive Player of the Year (1st)', $collectedPlayerNames),
                'roy' => $this->awardExtractor->extractAward($awards, 'Rookie of the Year (1st)', $collectedPlayerNames),
                'sixthMan' => $this->awardExtractor->extractAward($awards, '6th Man Award (1st)', $collectedPlayerNames),
                'gmOfYear' => $this->awardExtractor->getGmOfTheYear($gmAwards, $year),
                'finalsMvp' => $this->awardExtractor->extractAward($awards, 'IBL Finals MVP', $collectedPlayerNames),
            ],
            'allLeagueTeams' => [
                'first' => $this->awardExtractor->extractAwardList($awards, 'All-League First Team', $collectedPlayerNames),
                'second' => $this->awardExtractor->extractAwardList($awards, 'All-League Second Team', $collectedPlayerNames),
                'third' => $this->awardExtractor->extractAwardList($awards, 'All-League Third Team', $collectedPlayerNames),
            ],
            'allDefensiveTeams' => [
                'first' => $this->awardExtractor->extractAwardList($awards, 'All-Defensive Team (1st)', $collectedPlayerNames),
                'second' => $this->awardExtractor->extractAwardList($awards, 'All-Defensive Team (2nd)', $collectedPlayerNames),
                'third' => $this->awardExtractor->extractAwardList($awards, 'All-Defensive Team (3rd)', $collectedPlayerNames),
            ],
            'allRookieTeams' => [
                'first' => $this->awardExtractor->extractAwardList($awards, 'All-Rookie Team (1st)', $collectedPlayerNames),
                'second' => $this->awardExtractor->extractAwardList($awards, 'All-Rookie Team (2nd)', $collectedPlayerNames),
                'third' => $this->awardExtractor->extractAwardList($awards, 'All-Rookie Team (3rd)', $collectedPlayerNames),
            ],
            'statisticalLeaders' => [
                'scoring' => $this->awardExtractor->extractAward($awards, 'Scoring Leader (1st)', $collectedPlayerNames),
                'rebounds' => $this->awardExtractor->extractAward($awards, 'Rebounding Leader (1st)', $collectedPlayerNames),
                'assists' => $this->awardExtractor->extractAward($awards, 'Assists Leader (1st)', $collectedPlayerNames),
                'steals' => $this->awardExtractor->extractAward($awards, 'Steals Leader (1st)', $collectedPlayerNames),
                'blocks' => $this->awardExtractor->extractAward($awards, 'Blocks Leader (1st)', $collectedPlayerNames),
            ],
            'playoffBracket' => $playoffBracket,
            'heatStandings' => $heatStandings,
            'teamAwards' => $parsedTeamAwards,
            'championRosters' => [
                'ibl' => $this->awardExtractor->extractAwardList($awards, 'IBL Champion', $collectedPlayerNames),
                'heat' => $this->awardExtractor->extractAwardList($awards, 'IBL HEAT Championship', $collectedPlayerNames),
            ],
            'allStarRosters' => [
                'east' => $this->awardExtractor->extractAwardList($awards, 'Eastern Conference All-Star', $collectedPlayerNames),
                'west' => $this->awardExtractor->extractAwardList($awards, 'Western Conference All-Star', $collectedPlayerNames),
            ],
            'allStarCoaches' => $this->awardExtractor->getAllStarCoaches($gmAwards, $year, $teamConferences),
            'iblChampionCoach' => $this->awardExtractor->getIblChampionCoach($gmTenures, $iblFinals['winner'], $year),
            'teamColors' => $teamColors,
            'playerIds' => [],
            'teamIds' => $teamIds,
        ];

        $seasonData['playerIds'] = $this->repository->getPlayerIdsByNames(array_keys($collectedPlayerNames));

        return $seasonData;
    }

    /**
     * Build season label (e.g., "Season I (1988-89)")
     */
    public function buildSeasonLabel(int $year): string
    {
        $seasonNumber = $year - (self::FIRST_ENDING_YEAR - 1);
        $roman = self::toRoman($seasonNumber);
        $startYear = $year - 1;
        $endYearShort = substr((string) $year, 2);

        return 'Season ' . $roman . ' (' . $startYear . '-' . $endYearShort . ')';
    }

    private static function toRoman(int $number): string
    {
        $map = [
            1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
            100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
            10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I',
        ];
        $result = '';
        foreach ($map as $value => $numeral) {
            while ($number >= $value) {
                $result .= $numeral;
                $number -= $value;
            }
        }

        return $result;
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
