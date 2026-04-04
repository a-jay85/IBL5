<?php

declare(strict_types=1);

namespace DepthChartEntry;

use DepthChartEntry\Contracts\DepthChartEntryControllerInterface;
use NextSim\NextSimService;
use NextSim\NextSimView;
use SavedDepthChart\SavedDepthChartService;
use Standings\StandingsRepository;
use Team\Contracts\TeamTableServiceInterface;
use Team\TeamRepository;
use Team\TeamTableService;
use TeamSchedule\TeamScheduleRepository;
use UI\Components\TableViewDropdown;
use Team\Team;
use Season\Season;

/**
 * @see DepthChartEntryControllerInterface
 */
class DepthChartEntryController implements DepthChartEntryControllerInterface
{
    private \mysqli $db;
    private DepthChartEntryRepository $repository;
    private DepthChartEntryView $view;
    private \Services\CommonMysqliRepository $commonRepository;
    private TeamTableServiceInterface $teamTableService;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->repository = new DepthChartEntryRepository($db);
        $this->view = new DepthChartEntryView();
        $this->commonRepository = new \Services\CommonMysqliRepository($db);
        $teamRepository = new TeamRepository($db);
        $this->teamTableService = new TeamTableService($db, $teamRepository);
    }

    /**
     * @see DepthChartEntryControllerInterface::displayForm()
     */
    public function displayForm(string $username): void
    {
        $display = 'ratings';
        if (isset($_REQUEST['display']) && is_string($_REQUEST['display'])) {
            $display = $_REQUEST['display'];
        }

        // Validate split parameter when display=split
        $split = null;
        if ($display === 'split' && isset($_REQUEST['split']) && is_string($_REQUEST['split'])) {
            $splitRepo = new \Team\SplitStatsRepository($this->db);
            $rawSplit = $_REQUEST['split'];
            if (in_array($rawSplit, $splitRepo->getValidSplitKeys(), true)) {
                $split = $rawSplit;
            } else {
                $display = 'ratings';
            }
        } elseif ($display === 'split') {
            $display = 'ratings';
        }

        $season = new Season($this->db);

        $teamName = $this->getUserTeamName($username);
        $teamID = $this->commonRepository->getTidFromTeamname($teamName) ?? 0;
        $team = Team::initialize($this->db, $teamID);

        \PageLayout\PageLayout::header();

        echo '<h2 class="ibl-title">Depth Chart Entry</h2>';
        $this->view->renderTeamLogo($teamID);

        // Render saved depth chart dropdown
        $savedDcService = new SavedDepthChartService($this->db);
        $dropdownOptions = $savedDcService->getDropdownOptions($teamID, $season);
        $currentLiveLabel = $savedDcService->buildCurrentLiveLabel($teamID, $season);
        $this->view->renderSavedDepthChartDropdown($dropdownOptions, $currentLiveLabel);

        $this->view->renderHelpSection();

        $playersResult = $this->repository->getPlayersOnTeam($teamID);

        // Compute quality scores and inject into player arrays
        $playersWithQuality = array_map(
            static function (array $player): array {
                $player['quality_score'] = self::computeQualityScore($player);
                return $player;
            },
            $playersResult
        );

        // Collect current roster PIDs for JS config
        $currentRosterPids = array_map(
            static fn(array $p): int => $p['pid'],
            $playersWithQuality
        );

        $slotNames = \JSB::PLAYER_POSITIONS;

        $this->view->renderLineupPreview();
        $this->view->renderFormHeader($teamName, $teamID, $slotNames);

        $depthCount = 1;
        foreach ($playersWithQuality as $player) {
            $this->view->renderPlayerRow($player, $depthCount);
            $depthCount++;
        }

        $this->view->renderFormFooter();
        $this->view->renderMobileView($playersWithQuality, $slotNames);

        echo '<div class="table-scroll-wrapper"><div class="table-scroll-container" tabindex="0" role="region" aria-label="Player ratings">';
        echo $this->getTableOutput($teamID, $display, $split);
        echo '</div></div>';

        // Output JS configuration for saved depth charts
        $jsConfig = json_encode([
            'teamId' => $teamID,
            'apiBaseUrl' => 'modules.php?name=DepthChartEntry&op=api',
            'currentRosterPids' => $currentRosterPids,
        ], JSON_THROW_ON_ERROR);
        echo '<script>window.IBL_DEPTH_CHART_CONFIG = ' . $jsConfig . ';</script>';
        echo '<script src="jslib/depth-chart-changes.js"></script>';
        echo '<script src="jslib/depth-chart-lineup-preview.js"></script>';
        echo '<script src="jslib/saved-depth-charts.js"></script>';
        echo '<script src="jslib/depth-chart-mobile.js"></script>';

        // NextSim position tables section
        $this->renderNextSimSection($teamID, $team, $season);

        \PageLayout\PageLayout::footer();
    }

    /**
     * @see DepthChartEntryControllerInterface::getTableOutput()
     */
    public function getTableOutput(int $teamID, string $display, ?string $split = null): string
    {
        $season = new Season($this->db);
        $team = Team::initialize($this->db, $teamID);

        // Delegate roster + starters to TeamService (single source of truth)
        $rosterData = $this->teamTableService->getRosterAndStarters($teamID);

        $groups = $this->teamTableService->buildDropdownGroups($season);

        $activeValue = ($display === 'split' && $split !== null) ? 'split:' . $split : $display;
        $baseUrl = 'modules.php?name=DepthChartEntry';
        $apiUrl = 'modules.php?name=DepthChartEntry&op=tab-api&teamID=' . $teamID;
        $dropdown = new TableViewDropdown($groups, $activeValue, $baseUrl, $team->color1, $team->color2, $apiUrl);
        $tableHtml = $this->teamTableService->renderTableForDisplay($display, $rosterData['roster'], $team, null, $season, $rosterData['starterPids'], $split);

        return $dropdown->wrap($tableHtml);
    }

    private function renderNextSimSection(int $teamID, Team $team, Season $season): void
    {
        // Load power rankings for SOS tier indicators
        $standingsRepo = new StandingsRepository($this->db);
        $allStreakData = $standingsRepo->getAllStreakData();
        /** @var array<int, float> $teamPowerRankings */
        $teamPowerRankings = [];
        foreach ($allStreakData as $tid => $data) {
            $teamPowerRankings[$tid] = (float) $data['ranking'];
        }

        $teamScheduleRepository = new TeamScheduleRepository($this->db);
        $nextSimService = new NextSimService($this->db, $teamScheduleRepository, $teamPowerRankings);
        $nextSimView = new NextSimView($season);

        $games = $nextSimService->getNextSimGames($teamID, $season);
        $userStarters = $nextSimService->getUserStartingLineup($team);

        echo '<div class="next-sim-depth-chart-section">';
        echo '<h2 class="ibl-title">Next Sim</h2>';

        if ($games === []) {
            echo '<div class="next-sim-empty">No games projected next sim!</div>';
        } else {
            echo $nextSimView->renderScheduleStrip($games);
            echo '<div class="nextsim-tab-container">';
            echo $nextSimView->renderTabbedPositionTable($games, 'PG', $team, $userStarters);
            echo '</div>';
            echo $nextSimView->renderColumnHighlightScript();
        }

        echo '</div>';
    }

    private function getUserTeamName(string $username): string
    {
        $teamName = $this->commonRepository->getTeamnameFromUsername($username);
        return $teamName ?? '';
    }

    /**
     * Compute the dVar58 in-game quality score for lineup selection.
     *
     * Uses the structural form from the decompiled binary with calibrated constants.
     * The exact DAT_ constants are unresolved, so we use estimates informed by
     * the +0x340 formula (which has known constants). The relative ordering of
     * players matters more than absolute values.
     *
     * @param array<string, mixed> $player Player row from ibl_plr
     */
    private static function computeQualityScore(array $player): float
    {
        /** @var int $gp */
        $gp = $player['stats_gm'] ?? 0;
        if ($gp === 0) {
            return 0.0;
        }

        /** @var int $gs */
        $gs = $player['stats_gs'] ?? 0;
        /** @var int $min */
        $min = $player['stats_min'] ?? 0;
        /** @var int $fgm */
        $fgm = $player['stats_fgm'] ?? 0;
        /** @var int $fga */
        $fga = $player['stats_fga'] ?? 0;
        /** @var int $ftm */
        $ftm = $player['stats_ftm'] ?? 0;
        /** @var int $fta */
        $fta = $player['stats_fta'] ?? 0;
        /** @var int $tpm */
        $tpm = $player['stats_3gm'] ?? 0;
        /** @var int $tpa */
        $tpa = $player['stats_3ga'] ?? 0;
        /** @var int $orb */
        $orb = $player['stats_orb'] ?? 0;
        /** @var int $drb */
        $drb = $player['stats_drb'] ?? 0;
        /** @var int $ast */
        $ast = $player['stats_ast'] ?? 0;
        /** @var int $stl */
        $stl = $player['stats_stl'] ?? 0;
        /** @var int $tvr */
        $tvr = $player['stats_to'] ?? 0;
        /** @var int $blk */
        $blk = $player['stats_blk'] ?? 0;

        // ODPT defense ratings (1-9 scale)
        /** @var int $od */
        $od = $player['od'] ?? 5;
        /** @var int $dd */
        $dd = $player['dd'] ?? 5;
        /** @var int $pd */
        $pd = $player['pd'] ?? 5;
        /** @var int $td */
        $td = $player['td'] ?? 5;

        // 2pt FGM = total FGM - 3PM
        $twoPtMade = $fgm - $tpm;
        // 2pt FGA = total FGA - 3PA
        $twoPtAtt = $fga - $tpa;

        // TERM_A (defense): weighted by games started
        $defenseSum = $od + $dd + $pd + $td;
        $termA = ($defenseSum - 20) * 0.25 * $gs * 0.05;

        // TERM_B (production): separate ORB/DRB weights
        $termB = ($ast * 0.8 + ($orb * 0.5 + ($drb - $orb) * 0.3 + $stl) - $tvr + $blk) * 0.75;

        // TERM_C (scoring): MIN appears in scoring term per dVar58 structure
        $termC = (($ftm - $twoPtMade) * 0.15
            + (($min + $fta - ($twoPtAtt - $min) * 0.5) + $twoPtMade - $ftm * 0.3)) * 1.5;

        return round(($termA + $termB + $termC) / $gp, 2);
    }
}
