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
 * @phpstan-import-type AwardRow from SeasonArchiveRepositoryInterface
 * @phpstan-import-type TeamAwardRow from SeasonArchiveRepositoryInterface
 * @phpstan-import-type GmAwardWithTeamRow from SeasonArchiveRepositoryInterface
 * @phpstan-import-type HeatWinLossRow from SeasonArchiveRepositoryInterface
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
        // Era colors for this season win over today's colors; current names remain the fallback.
        $teamColors = $this->repository->getTeamColorsByYear()[$year] ?? [];
        $teamColors += $this->repository->getTeamColors();
        $teamConferences = $this->repository->getTeamConferences();

        $iblFinals = $this->bracketBuilder->getIblFinals($playoffResults);

        // Player names collected by the award builders below, in key order; consumed by the playerIds lookup
        /** @var array<string, true> $collectedPlayerNames */
        $collectedPlayerNames = [];

        $tournaments = $this->buildTournaments($year, $awards, $teamAwards, $iblFinals, $collectedPlayerNames);
        $allStarWeekend = $this->buildAllStarWeekend($awards, $collectedPlayerNames);
        $majorAwards = $this->buildMajorAwards($awards, $gmAwards, $year, $collectedPlayerNames);
        $allLeagueTeams = $this->buildTierLists($awards, 'All-League First Team', 'All-League Second Team', 'All-League Third Team', $collectedPlayerNames);
        $allDefensiveTeams = $this->buildTierLists($awards, 'All-Defensive Team (1st)', 'All-Defensive Team (2nd)', 'All-Defensive Team (3rd)', $collectedPlayerNames);
        $allRookieTeams = $this->buildTierLists($awards, 'All-Rookie Team (1st)', 'All-Rookie Team (2nd)', 'All-Rookie Team (3rd)', $collectedPlayerNames);
        $statisticalLeaders = $this->buildStatisticalLeaders($awards, $collectedPlayerNames);
        $championRosters = $this->buildChampionRosters($awards, $collectedPlayerNames);
        $allStarRosters = $this->buildAllStarRosters($awards, $collectedPlayerNames);

        return [
            'year' => $year,
            'label' => $this->buildSeasonLabel($year),
            'tournaments' => $tournaments,
            'allStarWeekend' => $allStarWeekend,
            'majorAwards' => $majorAwards,
            'allLeagueTeams' => $allLeagueTeams,
            'allDefensiveTeams' => $allDefensiveTeams,
            'allRookieTeams' => $allRookieTeams,
            'statisticalLeaders' => $statisticalLeaders,
            'playoffBracket' => $this->bracketBuilder->buildPlayoffBracket($playoffResults),
            'heatStandings' => $this->buildHeatStandings($heatStandingsRaw),
            'teamAwards' => $this->awardExtractor->parseTeamAwards($teamAwards),
            'championRosters' => $championRosters,
            'allStarRosters' => $allStarRosters,
            'allStarCoaches' => $this->awardExtractor->getAllStarCoaches($gmAwards, $year, $teamConferences),
            'iblChampionCoach' => $this->awardExtractor->getIblChampionCoach($gmTenures, $iblFinals['winner'], $year),
            'teamColors' => $teamColors,
            'playerIds' => $this->repository->getPlayerIdsByNames(array_keys($collectedPlayerNames)),
            'teamIds' => $this->buildTeamIds($teamColors),
        ];
    }

    /**
     * Build the tournaments section (HEAT, one-on-one, IBL Finals, bracket URLs)
     *
     * @param list<AwardRow> $awards
     * @param list<TeamAwardRow> $teamAwards
     * @param array{winner: string, loser: string, loserGames: int} $iblFinals
     * @param array<string, true> $collected Player-name accumulator (by reference)
     * @return array{heatChampion: string, heatUrl: string, oneOnOneChampion: string, rookieOneOnOneChampion: string, oneOnOneUrl: string, iblFinalsWinner: string, iblFinalsLoser: string, iblFinalsLoserGames: int, playoffsUrl: string}
     */
    private function buildTournaments(int $year, array $awards, array $teamAwards, array $iblFinals, array &$collected): array
    {
        return [
            'heatChampion' => $this->awardExtractor->getHeatChampionFromTeamAwards($teamAwards),
            'heatUrl' => $this->getChallongeUrl('heat', $year),
            'oneOnOneChampion' => $this->awardExtractor->extractAward($awards, 'One-on-One Tournament Champion', $collected),
            'rookieOneOnOneChampion' => $this->awardExtractor->extractAward($awards, 'Rookie One-on-One Tournament Champion', $collected),
            'oneOnOneUrl' => 'https://challonge.com/users/coldbeatle89/tournaments',
            'iblFinalsWinner' => $iblFinals['winner'],
            'iblFinalsLoser' => $iblFinals['loser'],
            'iblFinalsLoserGames' => $iblFinals['loserGames'],
            'playoffsUrl' => $this->getChallongeUrl('playoffs', $year),
        ];
    }

    /**
     * Build the All-Star Weekend section (game MVPs, contest winners and participants)
     *
     * @param list<AwardRow> $awards
     * @param array<string, true> $collected Player-name accumulator (by reference)
     * @return array{gameMvps: list<string>, slamDunkWinner: string, threePointWinner: string, rookieSophomoreMvp: string, slamDunkParticipants: list<string>, threePointParticipants: list<string>, rookieSophomoreParticipants: list<string>}
     */
    private function buildAllStarWeekend(array $awards, array &$collected): array
    {
        return [
            'gameMvps' => array_merge(
                $this->awardExtractor->extractAwardList($awards, 'All-Star Game MVP', $collected),
                $this->awardExtractor->extractAwardList($awards, 'All-Star Game Co-MVP', $collected),
            ),
            'slamDunkWinner' => $this->awardExtractor->extractAward($awards, 'Slam Dunk Competition - Winner', $collected),
            'threePointWinner' => $this->awardExtractor->extractAward($awards, 'Three-Point Contest - Winner', $collected),
            'rookieSophomoreMvp' => $this->awardExtractor->extractAward($awards, 'Rookie-Sophomore Challenge - MVP', $collected),
            'slamDunkParticipants' => $this->awardExtractor->extractAwardList($awards, 'Slam Dunk Competition', $collected),
            'threePointParticipants' => $this->awardExtractor->extractAwardList($awards, 'Three-Point Contest', $collected),
            'rookieSophomoreParticipants' => $this->awardExtractor->extractAwardList($awards, 'Rookie-Sophomore Challenge', $collected),
        ];
    }

    /**
     * Build the major awards section (MVP, DPOY, ROY, Sixth Man, GM of the Year, Finals MVP)
     *
     * @param list<AwardRow> $awards
     * @param list<GmAwardWithTeamRow> $gmAwards
     * @param array<string, true> $collected Player-name accumulator (by reference)
     * @return array{mvp: string, dpoy: string, roy: string, sixthMan: string, gmOfYear: array{name: string, team: string}, finalsMvp: string}
     */
    private function buildMajorAwards(array $awards, array $gmAwards, int $year, array &$collected): array
    {
        return [
            'mvp' => $this->awardExtractor->extractAward($awards, 'Most Valuable Player (1st)', $collected),
            'dpoy' => $this->awardExtractor->extractAward($awards, 'Defensive Player of the Year (1st)', $collected),
            'roy' => $this->awardExtractor->extractAward($awards, 'Rookie of the Year (1st)', $collected),
            'sixthMan' => $this->awardExtractor->extractAward($awards, '6th Man Award (1st)', $collected),
            'gmOfYear' => $this->awardExtractor->getGmOfTheYear($gmAwards, $year),
            'finalsMvp' => $this->awardExtractor->extractAward($awards, 'IBL Finals MVP', $collected),
        ];
    }

    /**
     * Build a first/second/third team section from three award names
     *
     * @param list<AwardRow> $awards
     * @param array<string, true> $collected Player-name accumulator (by reference)
     * @return array{first: list<string>, second: list<string>, third: list<string>}
     */
    private function buildTierLists(array $awards, string $firstAward, string $secondAward, string $thirdAward, array &$collected): array
    {
        return [
            'first' => $this->awardExtractor->extractAwardList($awards, $firstAward, $collected),
            'second' => $this->awardExtractor->extractAwardList($awards, $secondAward, $collected),
            'third' => $this->awardExtractor->extractAwardList($awards, $thirdAward, $collected),
        ];
    }

    /**
     * Build the statistical leaders section
     *
     * @param list<AwardRow> $awards
     * @param array<string, true> $collected Player-name accumulator (by reference)
     * @return array{scoring: string, rebounds: string, assists: string, steals: string, blocks: string}
     */
    private function buildStatisticalLeaders(array $awards, array &$collected): array
    {
        return [
            'scoring' => $this->awardExtractor->extractAward($awards, 'Scoring Leader (1st)', $collected),
            'rebounds' => $this->awardExtractor->extractAward($awards, 'Rebounding Leader (1st)', $collected),
            'assists' => $this->awardExtractor->extractAward($awards, 'Assists Leader (1st)', $collected),
            'steals' => $this->awardExtractor->extractAward($awards, 'Steals Leader (1st)', $collected),
            'blocks' => $this->awardExtractor->extractAward($awards, 'Blocks Leader (1st)', $collected),
        ];
    }

    /**
     * Build the IBL and HEAT champion rosters
     *
     * @param list<AwardRow> $awards
     * @param array<string, true> $collected Player-name accumulator (by reference)
     * @return array{ibl: list<string>, heat: list<string>}
     */
    private function buildChampionRosters(array $awards, array &$collected): array
    {
        return [
            'ibl' => $this->awardExtractor->extractAwardList($awards, 'IBL Champion', $collected),
            'heat' => $this->awardExtractor->extractAwardList($awards, 'IBL HEAT Championship', $collected),
        ];
    }

    /**
     * Build the East and West All-Star rosters
     *
     * @param list<AwardRow> $awards
     * @param array<string, true> $collected Player-name accumulator (by reference)
     * @return array{east: list<string>, west: list<string>}
     */
    private function buildAllStarRosters(array $awards, array &$collected): array
    {
        return [
            'east' => $this->awardExtractor->extractAwardList($awards, 'Eastern Conference All-Star', $collected),
            'west' => $this->awardExtractor->extractAwardList($awards, 'Western Conference All-Star', $collected),
        ];
    }

    /**
     * Build HEAT standings rows
     *
     * @param list<HeatWinLossRow> $heatStandingsRaw
     * @return list<array{team: string, wins: int, losses: int}>
     */
    private function buildHeatStandings(array $heatStandingsRaw): array
    {
        $heatStandings = [];
        foreach ($heatStandingsRaw as $row) {
            $heatStandings[] = [
                'team' => (string) $row['currentname'],
                'wins' => (int) $row['wins'],
                'losses' => (int) $row['losses'],
            ];
        }

        return $heatStandings;
    }

    /**
     * Build the team name => team ID map from the team colors (which include teamid)
     *
     * @param array<string, array{color1: string, color2: string, teamid: int}> $teamColors
     * @return array<string, int>
     */
    private function buildTeamIds(array $teamColors): array
    {
        $teamIds = [];
        foreach ($teamColors as $teamName => $colorData) {
            $teamIds[$teamName] = $colorData['teamid'];
        }

        return $teamIds;
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
