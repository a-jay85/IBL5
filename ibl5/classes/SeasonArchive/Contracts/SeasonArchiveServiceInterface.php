<?php

declare(strict_types=1);

namespace SeasonArchive\Contracts;

/**
 * SeasonArchiveServiceInterface - Contract for season archive business logic
 *
 * Defines the public API for assembling season archive data from multiple
 * database sources into structured arrays for view rendering.
 *
 * @phpstan-type SeasonSummary array{year: int, label: string, iblChampion: string, heatChampion: string, mvp: string}
 * @phpstan-type PlayoffSeries array{winner: string, loser: string, loserGames: int}
 * @phpstan-type SeasonDetail array{
 *     year: int,
 *     label: string,
 *     tournaments: array{heatChampion: string, heatUrl: string, oneOnOneChampion: string, rookieOneOnOneChampion: string, oneOnOneUrl: string, iblFinalsWinner: string, iblFinalsLoser: string, iblFinalsLoserGames: int, playoffsUrl: string},
 *     allStarWeekend: array{gameMvp: string, slamDunkWinner: string, threePointWinner: string, rookieSophomoreMvp: string, slamDunkParticipants: list<string>, threePointParticipants: list<string>, rookieSophomoreParticipants: list<string>},
 *     majorAwards: array{mvp: string, dpoy: string, roy: string, sixthMan: string, gmOfYear: array{name: string, team: string}, finalsMvp: string},
 *     allLeagueTeams: array{first: list<string>, second: list<string>, third: list<string>},
 *     allDefensiveTeams: array{first: list<string>, second: list<string>, third: list<string>},
 *     allRookieTeams: array{first: list<string>, second: list<string>, third: list<string>},
 *     statisticalLeaders: array{scoring: string, rebounds: string, assists: string, steals: string, blocks: string},
 *     playoffBracket: array<int, list<PlayoffSeries>>,
 *     heatStandings: list<array{team: string, wins: int, losses: int}>,
 *     teamAwards: array<string, string>,
 *     championRosters: array{ibl: list<string>, heat: list<string>},
 *     allStarRosters: array{east: list<string>, west: list<string>},
 *     allStarCoaches: array{east: list<string>, west: list<string>},
 *     iblChampionCoach: string,
 *     teamColors: array<string, array{color1: string, color2: string, teamid: int}>,
 *     playerIds: array<string, int>,
 *     teamIds: array<string, int>
 * }
 *
 * @see \SeasonArchive\SeasonArchiveService For the concrete implementation
 */
interface SeasonArchiveServiceInterface
{
    /**
     * Get summary data for all seasons (for index page)
     *
     * Returns an array of season summaries with label, IBL champion,
     * HEAT champion, and MVP for each season year (1989-2006).
     *
     * @return list<SeasonSummary> Array of season summaries ordered by year DESC
     */
    public function getAllSeasons(): array;

    /**
     * Get full detail data for a single season (for detail page)
     *
     * Returns null if the year is not a valid season year.
     *
     * @param int $year Season ending year (1989-2006)
     * @return SeasonDetail|null Full season data, or null for invalid year
     */
    public function getSeasonDetail(int $year): ?array;
}
