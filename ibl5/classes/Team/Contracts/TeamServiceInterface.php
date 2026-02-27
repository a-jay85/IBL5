<?php

declare(strict_types=1);

namespace Team\Contracts;

/**
 * TeamServiceInterface - Contract for Team module data orchestration
 *
 * Assembles all data needed by TeamView from repositories, domain objects,
 * and sub-components. The view receives a pre-computed data array and never
 * touches the database.
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 *
 * @phpstan-type TeamPageData array{
 *     teamID: int,
 *     team: \Team,
 *     imagesPath: string,
 *     yr: ?string,
 *     display: string,
 *     insertyear: string,
 *     isActualTeam: bool,
 *     tableOutput: string,
 *     draftPicksTable: string,
 *     currentSeasonCard: string,
 *     awardsCard: string,
 *     franchiseHistoryCard: string,
 *     rafters: string,
 *     userTeamName: string,
 *     isOwnTeam: bool
 * }
 * @phpstan-type StartersData array<string, array{name: string|null, pid: int|null}>
 * @phpstan-type SidebarData array{currentSeasonCard: string, awardsCard: string, franchiseHistoryCard: string, rafters: string}
 *
 * @phpstan-type CurrentSeasonData array{
 *     teamName: string,
 *     fka: ?string,
 *     wins: int,
 *     losses: int,
 *     arena: string,
 *     capacity: int,
 *     conference: string,
 *     conferencePosition: int,
 *     division: string,
 *     divisionPosition: int,
 *     divisionGB: float,
 *     homeRecord: string,
 *     awayRecord: string,
 *     lastWin: int,
 *     lastLoss: int
 * }
 *
 * @phpstan-type BannerItemData array{year: int|string, name: string, label: string, bgImage: ?string}
 * @phpstan-type BannerGroupData array{banners: list<BannerItemData>, textSummary: string}
 * @phpstan-type BannerData array{teamName: string, color1: string, color2: string, championships: BannerGroupData, conferenceTitles: BannerGroupData, divisionTitles: BannerGroupData}
 *
 * @phpstan-type PlayoffResultItem array{year: string, winner: string, loser: string, winnerGames: int, loserGames: int, isWin: bool}
 * @phpstan-type PlayoffRoundData array{name: string, gameWins: int, gameLosses: int, seriesWins: int, seriesLosses: int, results: list<PlayoffResultItem>}
 * @phpstan-type PlayoffData array{rounds: list<PlayoffRoundData>, totalGameWins: int, totalGameLosses: int, totalSeriesWins: int, totalSeriesLosses: int}
 *
 * @phpstan-type WinLossRecord array{year: int, label: string, wins: int, losses: int, urlYear: int, isBest: bool}
 * @phpstan-type WinLossHistoryData array{records: list<WinLossRecord>, totalWins: int, totalLosses: int, teamID: int}
 *
 * @phpstan-type DraftPickItemData array{originalTeamID: int, originalTeamCity: string, originalTeamName: string, year: string, round: int|string, notes: ?string}
 */
interface TeamServiceInterface
{
    /**
     * Assemble all data needed by the team page view
     *
     * Initialises Team, Season, and Shared objects, loads the appropriate roster
     * via the repository, and calls private rendering helpers for sub-components
     * (tabs, table, sidebar).
     *
     * @param int $teamID Team ID (>0 = specific team, 0 = free agents, -1 = entire league)
     * @param ?string $yr Historical year parameter (null if current season)
     * @param string $display Active display tab (e.g., 'ratings', 'contracts')
     * @param string $userTeamName Logged-in user's team name
     * @param ?string $split Split stats key (e.g. 'home', 'road', 'wins')
     * @return TeamPageData
     */
    public function getTeamPageData(int $teamID, ?string $yr, string $display, string $userTeamName = '', ?string $split = null): array;
}
