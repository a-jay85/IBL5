<?php

declare(strict_types=1);

namespace Team;

use Team\Contracts\TeamServiceInterface;
use Team\Contracts\TeamRepositoryInterface;
use Team\Views\AwardsView;
use Team\Views\BannersView;
use Team\Views\CurrentSeasonView;
use Team\Views\DraftPicksView;
use Team\Views\FranchiseHistoryView;
use Team\Views\SidebarView;

/**
 * @phpstan-import-type TeamPageData from Contracts\TeamServiceInterface
 * @phpstan-import-type SidebarData from Contracts\TeamServiceInterface
 * @phpstan-import-type CurrentSeasonData from Contracts\TeamServiceInterface
 * @phpstan-import-type BannerData from Contracts\TeamServiceInterface
 * @phpstan-import-type BannerItemData from Contracts\TeamServiceInterface
 * @phpstan-import-type BannerGroupData from Contracts\TeamServiceInterface
 * @phpstan-import-type PlayoffData from Contracts\TeamServiceInterface
 * @phpstan-import-type PlayoffRoundData from Contracts\TeamServiceInterface
 * @phpstan-import-type PlayoffResultItem from Contracts\TeamServiceInterface
 * @phpstan-import-type WinLossHistoryData from Contracts\TeamServiceInterface
 * @phpstan-import-type WinLossRecord from Contracts\TeamServiceInterface
 * @phpstan-import-type DraftPickItemData from Contracts\TeamServiceInterface
 * @phpstan-import-type FranchiseSeasonRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type TeamInfoRow from \Services\CommonMysqliRepository
 *
 * @see TeamServiceInterface
 */
class TeamService implements TeamServiceInterface
{
    private \mysqli $db;
    private TeamRepositoryInterface $repository;

    public function __construct(\mysqli $db, TeamRepositoryInterface $repository)
    {
        $this->db = $db;
        $this->repository = $repository;
    }

    /**
     * @see TeamServiceInterface::getTeamPageData()
     * @return TeamPageData
     */
    public function getTeamPageData(int $teamID, ?string $yr, string $display, string $userTeamName = '', ?string $split = null): array
    {
        global $leagueContext;
        /** @var \League\LeagueContext $leagueContext */

        $leagueConfig = $leagueContext->getConfig();
        /** @var string $imagesPath */
        $imagesPath = $leagueConfig['images_path'];

        $team = \Team::initialize($this->db, $teamID);

        $insertyear = ($yr !== null && $yr !== '') ? "&yr=$yr" : "";

        $tableService = new TeamTableService($this->db, $this->repository);
        $tableOutput = $tableService->getTableOutput($teamID, $yr, $display, $split);

        $isActualTeam = ($teamID !== 0);

        $draftPicksTable = '';
        if ($isActualTeam) {
            $draftPicksData = $this->prepareDraftPicksData($team);
            $draftPicksView = new DraftPicksView();
            $draftPicksTable = $draftPicksView->render($draftPicksData);
        }

        $currentSeasonCard = "";
        $awardsCard = "";
        $franchiseHistoryCard = "";
        $rafters = "";
        if ($isActualTeam) {
            $sidebarData = $this->renderTeamInfoRight($team);
            $currentSeasonCard = $sidebarData['currentSeasonCard'];
            $awardsCard = $sidebarData['awardsCard'];
            $franchiseHistoryCard = $sidebarData['franchiseHistoryCard'];
            $rafters = $sidebarData['rafters'];
        }

        return [
            'teamID' => $teamID,
            'team' => $team,
            'imagesPath' => $imagesPath,
            'yr' => $yr,
            'display' => $display,
            'insertyear' => $insertyear,
            'isActualTeam' => $isActualTeam,
            'tableOutput' => $tableOutput,
            'draftPicksTable' => $draftPicksTable,
            'currentSeasonCard' => $currentSeasonCard,
            'awardsCard' => $awardsCard,
            'franchiseHistoryCard' => $franchiseHistoryCard,
            'rafters' => $rafters,
            'userTeamName' => $userTeamName,
            'isOwnTeam' => ($userTeamName !== '' && $userTeamName === $team->name),
        ];
    }

    /**
     * Render team information right sidebar
     *
     * @return SidebarData
     */
    private function renderTeamInfoRight(\Team $team): array
    {
        $color1 = \UI\TableStyles::sanitizeColor($team->color1);
        $color2 = \UI\TableStyles::sanitizeColor($team->color2);
        $teamColorStyle = "--team-color-primary: #$color1; --team-color-secondary: #$color2;";

        $sidebarView = new SidebarView();

        // Banners
        $bannerData = $this->prepareBannerData($team);
        $bannersView = new BannersView();
        $rafters = $bannersView->render($bannerData);

        // Current Season card
        $currentSeasonData = $this->prepareCurrentSeasonData($team);
        $currentSeasonHtml = '';
        if ($currentSeasonData !== null) {
            $currentSeasonView = new CurrentSeasonView();
            $currentSeasonHtml = $currentSeasonView->render($currentSeasonData);
        }
        $currentSeasonCard = $sidebarView->renderCurrentSeasonCard($currentSeasonHtml, $teamColorStyle);

        // Awards card — combines GM History and Team Accomplishments
        $tenures = $this->repository->getGMTenures($team->teamID);
        $gmAwards = $this->repository->getGMAwards($team->ownerName);
        $teamAccomplishments = $this->repository->getTeamAccomplishments($team->name);

        $awardsRenderer = new AwardsView();
        $gmHistoryHtml = $awardsRenderer->renderGmHistory($tenures, $gmAwards);
        $teamAccomplishmentsHtml = $awardsRenderer->renderTeamAccomplishments($teamAccomplishments);
        $awardsCard = $sidebarView->renderAwardsCard($gmHistoryHtml, $teamAccomplishmentsHtml, $teamColorStyle);

        // Franchise History card
        $regularSeasonData = $this->prepareWinLossHistoryData($team, 'regular');
        $heatData = $this->prepareWinLossHistoryData($team, 'heat');
        $playoffData = $this->preparePlayoffData($team);

        $franchiseHistoryView = new FranchiseHistoryView();
        $regularSeasonHtml = $franchiseHistoryView->renderRegularSeason($regularSeasonData);
        $heatHtml = $franchiseHistoryView->renderHeat($heatData);
        $playoffsHtml = $franchiseHistoryView->renderPlayoffs($playoffData);
        $franchiseHistoryCard = $sidebarView->renderFranchiseHistoryCard($heatHtml, $regularSeasonHtml, $playoffsHtml, $teamColorStyle);

        return [
            'currentSeasonCard' => $currentSeasonCard,
            'awardsCard' => $awardsCard,
            'franchiseHistoryCard' => $franchiseHistoryCard,
            'rafters' => $rafters,
        ];
    }

    /**
     * Prepare current season data for the view.
     *
     * @return CurrentSeasonData|null
     */
    private function prepareCurrentSeasonData(\Team $team): ?array
    {
        $powerData = $this->repository->getTeamPowerData($team->name);
        if ($powerData === null) {
            return null;
        }

        $divisionStandings = $this->repository->getDivisionStandings($powerData['division']);
        $divPos = 1;
        foreach ($divisionStandings as $index => $standing) {
            if ($standing['team_name'] === $team->name) {
                $divPos = $index + 1;
                break;
            }
        }

        $conferenceStandings = $this->repository->getConferenceStandings($powerData['conference']);
        $confPos = 1;
        foreach ($conferenceStandings as $index => $standing) {
            if ($standing['team_name'] === $team->name) {
                $confPos = $index + 1;
                break;
            }
        }

        $franchiseSeasons = $this->repository->getFranchiseSeasons($team->teamID);
        $fka = $this->buildFormerlyKnownAs($franchiseSeasons, $team->city, $team->name);

        return [
            'teamName' => $team->name,
            'fka' => $fka,
            'wins' => (int) $powerData['wins'],
            'losses' => (int) $powerData['losses'],
            'arena' => $team->arena,
            'capacity' => $team->capacity,
            'conference' => $powerData['conference'],
            'conferencePosition' => $confPos,
            'division' => $powerData['division'],
            'divisionPosition' => $divPos,
            'divisionGB' => (float) ($powerData['divGB'] ?? 0.0),
            'homeRecord' => $powerData['homeRecord'],
            'awayRecord' => $powerData['awayRecord'],
            'lastWin' => $powerData['last_win'],
            'lastLoss' => $powerData['last_loss'],
        ];
    }

    /**
     * Prepare banner data for the view.
     *
     * @return BannerData
     */
    private function prepareBannerData(\Team $team): array
    {
        $banners = $this->repository->getChampionshipBanners($team->name);

        /** @var list<BannerItemData> $champBanners */
        $champBanners = [];
        /** @var list<BannerItemData> $confBanners */
        $confBanners = [];
        /** @var list<BannerItemData> $divBanners */
        $divBanners = [];

        $champText = "";
        $confText = "";
        $divText = "";

        foreach ($banners as $banner) {
            $year = $banner['year'];
            $name = $banner['bannername'];
            $type = $banner['bannertype'];

            if ($type === 1) {
                $champBanners[] = [
                    'year' => $year,
                    'name' => $name,
                    'label' => 'IBL Champions',
                    'bgImage' => './images/banners/banner1.gif',
                ];
                $champText = $this->appendBannerYear($champText, $year, $name, $team->name);
            } elseif ($type === 2 || $type === 3) {
                $confLabel = $type === 2 ? 'Eastern Conf. Champions' : 'Western Conf. Champions';
                $confBanners[] = [
                    'year' => $year,
                    'name' => $name,
                    'label' => $confLabel,
                    'bgImage' => './images/banners/banner2.gif',
                ];
                $confText = $this->appendBannerYear($confText, $year, $name, $team->name);
            } elseif ($type >= 4 && $type <= 7) {
                $divLabel = match ($type) {
                    4 => 'Atlantic Div. Champions',
                    5 => 'Central Div. Champions',
                    6 => 'Midwest Div. Champions',
                    default => 'Pacific Div. Champions',
                };
                $divBanners[] = [
                    'year' => $year,
                    'name' => $name,
                    'label' => $divLabel,
                    'bgImage' => null,
                ];
                $divText = $this->appendBannerYear($divText, $year, $name, $team->name);
            }
        }

        return [
            'teamName' => $team->name,
            'color1' => $team->color1,
            'color2' => $team->color2,
            'championships' => ['banners' => $champBanners, 'textSummary' => $champText],
            'conferenceTitles' => ['banners' => $confBanners, 'textSummary' => $confText],
            'divisionTitles' => ['banners' => $divBanners, 'textSummary' => $divText],
        ];
    }

    /**
     * Prepare playoff data for the view.
     *
     * @return PlayoffData
     */
    private function preparePlayoffData(\Team $team): array
    {
        $playoffResults = $this->repository->getPlayoffResults();
        $teamName = $team->name;

        $totalGameWins = 0;
        $totalGameLosses = 0;

        /** @var array<int, array{name: string, gameWins: int, gameLosses: int, seriesWins: int, seriesLosses: int, results: list<PlayoffResultItem>}> $roundsMap */
        $roundsMap = [
            1 => ['name' => 'First Round', 'gameWins' => 0, 'gameLosses' => 0, 'seriesWins' => 0, 'seriesLosses' => 0, 'results' => []],
            2 => ['name' => 'Conference Semis', 'gameWins' => 0, 'gameLosses' => 0, 'seriesWins' => 0, 'seriesLosses' => 0, 'results' => []],
            3 => ['name' => 'Conference Finals', 'gameWins' => 0, 'gameLosses' => 0, 'seriesWins' => 0, 'seriesLosses' => 0, 'results' => []],
            4 => ['name' => 'IBL Finals', 'gameWins' => 0, 'gameLosses' => 0, 'seriesWins' => 0, 'seriesLosses' => 0, 'results' => []],
        ];

        foreach ($playoffResults as $playoff) {
            $round = $playoff['round'];
            $winner = $playoff['winner'];
            $loser = $playoff['loser'];

            if (!isset($roundsMap[$round])) {
                continue;
            }

            $isWin = ($winner === $teamName);
            $isLoss = ($loser === $teamName);

            if (!$isWin && !$isLoss) {
                continue;
            }

            $year = \Utilities\HtmlSanitizer::safeHtmlOutput((string) $playoff['year']);
            $winnerSafe = \Utilities\HtmlSanitizer::safeHtmlOutput($playoff['winner_name_that_year']);
            $loserSafe = \Utilities\HtmlSanitizer::safeHtmlOutput($playoff['loser_name_that_year']);
            $winnerGames = $playoff['winner_games'];
            $loserGames = $playoff['loser_games'];

            /** @var PlayoffResultItem $resultItem */
            $resultItem = [
                'year' => $year,
                'winner' => $winnerSafe,
                'loser' => $loserSafe,
                'winnerGames' => $winnerGames,
                'loserGames' => $loserGames,
                'isWin' => $isWin,
            ];

            $roundsMap[$round]['results'][] = $resultItem;

            if ($isWin) {
                $totalGameWins += $winnerGames;
                $totalGameLosses += $loserGames;
                $roundsMap[$round]['gameWins'] += $winnerGames;
                $roundsMap[$round]['gameLosses'] += $loserGames;
                $roundsMap[$round]['seriesWins']++;
            } else {
                $totalGameLosses += $winnerGames;
                $totalGameWins += $loserGames;
                $roundsMap[$round]['gameLosses'] += $winnerGames;
                $roundsMap[$round]['gameWins'] += $loserGames;
                $roundsMap[$round]['seriesLosses']++;
            }
        }

        /** @var list<PlayoffRoundData> $rounds */
        $rounds = [];
        foreach ($roundsMap as $r) {
            $rounds[] = [
                'name' => $r['name'],
                'gameWins' => $r['gameWins'],
                'gameLosses' => $r['gameLosses'],
                'seriesWins' => $r['seriesWins'],
                'seriesLosses' => $r['seriesLosses'],
                'results' => $r['results'],
            ];
        }

        $totalSeriesWins = 0;
        $totalSeriesLosses = 0;
        foreach ($rounds as $r) {
            $totalSeriesWins += $r['seriesWins'];
            $totalSeriesLosses += $r['seriesLosses'];
        }

        return [
            'rounds' => $rounds,
            'totalGameWins' => $totalGameWins,
            'totalGameLosses' => $totalGameLosses,
            'totalSeriesWins' => $totalSeriesWins,
            'totalSeriesLosses' => $totalSeriesLosses,
        ];
    }

    /**
     * Prepare win/loss history data for regular season or HEAT.
     *
     * @param string $type 'regular' or 'heat'
     * @return WinLossHistoryData
     */
    private function prepareWinLossHistoryData(\Team $team, string $type): array
    {
        if ($type === 'heat') {
            $history = $this->repository->getHEATHistory($team->name);
            $urlYearOffset = 1;
        } else {
            $history = $this->repository->getRegularSeasonHistory($team->name);
            $urlYearOffset = 0;
        }

        $totalWins = 0;
        $totalLosses = 0;

        // Find best record by win percentage
        $bestPct = -1.0;
        $bestWins = -1;
        $bestIndex = -1;
        foreach ($history as $index => $record) {
            $w = (int) $record['wins'];
            $l = (int) $record['losses'];
            $total = $w + $l;
            if ($total > 0) {
                $pct = $w / $total;
                if ($pct > $bestPct) {
                    $bestPct = $pct;
                    $bestWins = $w;
                    $bestIndex = $index;
                } elseif ($pct === $bestPct && $w > $bestWins) {
                    $bestWins = $w;
                    $bestIndex = $index;
                }
            }
        }

        /** @var list<WinLossRecord> $records */
        $records = [];
        foreach ($history as $index => $record) {
            $yearwl = $record['year'];
            $wins = (int) $record['wins'];
            $losses = (int) $record['losses'];
            $totalWins += $wins;
            $totalLosses += $losses;

            $name = \Utilities\HtmlSanitizer::safeHtmlOutput($record['namethatyear']);
            if ($type === 'heat') {
                $label = $yearwl . ' ' . $name;
            } else {
                $prevYear = $yearwl - 1;
                $label = $prevYear . '-' . $yearwl . ' ' . $name;
            }

            $records[] = [
                'year' => $yearwl,
                'label' => $label,
                'wins' => $wins,
                'losses' => $losses,
                'urlYear' => $yearwl + $urlYearOffset,
                'isBest' => ($index === $bestIndex),
            ];
        }

        return [
            'records' => $records,
            'totalWins' => $totalWins,
            'totalLosses' => $totalLosses,
            'teamID' => $team->teamID,
        ];
    }

    /**
     * Prepare draft picks data for the view.
     *
     * @return list<DraftPickItemData>
     */
    private function prepareDraftPicksData(\Team $team): array
    {
        $teamQueryRepo = new TeamQueryRepository($this->db);
        $resultPicks = $teamQueryRepo->getDraftPicks($team->teamID);

        $league = new \League($this->db);
        $allTeamsResult = $league->getAllTeamsResult();

        /** @var array<string, \Team> $teamsArray */
        $teamsArray = [];
        foreach ($allTeamsResult as $teamRow) {
            /** @var TeamInfoRow $teamRow */
            $teamRowName = $teamRow['team_name'];
            $teamsArray[$teamRowName] = \Team::initialize($this->db, $teamRow);
        }

        /** @var list<DraftPickItemData> $draftPicks */
        $draftPicks = [];

        foreach ($resultPicks as $draftPickRow) {
            $draftPick = new \Draft\DraftPick($draftPickRow);

            $draftPicks[] = [
                'originalTeamID' => $teamsArray[$draftPick->originalTeam]->teamID,
                'originalTeamCity' => $teamsArray[$draftPick->originalTeam]->city,
                'originalTeamName' => $draftPick->originalTeam,
                'year' => (string) $draftPick->year,
                'round' => $draftPick->round,
                'notes' => $draftPick->notes,
            ];
        }

        return $draftPicks;
    }

    /**
     * Build a "formerly known as" string from franchise season history.
     *
     * @param list<FranchiseSeasonRow> $franchiseSeasons
     */
    private function buildFormerlyKnownAs(array $franchiseSeasons, string $currentCity, string $currentName): ?string
    {
        if ($franchiseSeasons === []) {
            return null;
        }

        /** @var list<array{city: string, name: string, startYear: int, endYear: int}> $eras */
        $eras = [];

        foreach ($franchiseSeasons as $season) {
            $city = $season['team_city'];
            $name = $season['team_name'];
            $lastIndex = count($eras) - 1;

            if ($lastIndex >= 0 && $eras[$lastIndex]['city'] === $city && $eras[$lastIndex]['name'] === $name) {
                $eras[$lastIndex] = [
                    'city' => $eras[$lastIndex]['city'],
                    'name' => $eras[$lastIndex]['name'],
                    'startYear' => $eras[$lastIndex]['startYear'],
                    'endYear' => $season['season_ending_year'],
                ];
            } else {
                $eras[] = [
                    'city' => $city,
                    'name' => $name,
                    'startYear' => $season['season_year'],
                    'endYear' => $season['season_ending_year'],
                ];
            }
        }

        $parts = [];
        foreach ($eras as $era) {
            if ($era['city'] === $currentCity && $era['name'] === $currentName) {
                continue;
            }
            $parts[] = $era['city'] . ' ' . $era['name'] . ' (' . $era['startYear'] . '-' . $era['endYear'] . ')';
        }

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    /**
     * Append a banner year to the accumulating text, noting the franchise name if different.
     */
    private function appendBannerYear(string $text, int|string $year, string $bannerName, string $currentName): string
    {
        if ($text === '') {
            $text = (string) $year;
        } else {
            $text .= ", $year";
        }
        if ($bannerName !== $currentName) {
            $text .= " (as $bannerName)";
        }
        return $text;
    }
}
