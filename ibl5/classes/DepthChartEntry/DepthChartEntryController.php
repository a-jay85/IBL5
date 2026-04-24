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

        // Consume the PRG failure flash if present. Stashed by the submission
        // handler (`_ibl_depth_chart_flash`) on validation failure or empty
        // team name so the redirected GET can (1) surface validator errors
        // above the form and (2) re-populate fields from the user's submitted
        // POST rather than the DB — preserving in-flight edits.
        $flashErrorsHtml = '';
        $override = [];
        if (isset($_SESSION['_ibl_depth_chart_flash']) && is_array($_SESSION['_ibl_depth_chart_flash'])) {
            $flash = $_SESSION['_ibl_depth_chart_flash'];
            unset($_SESSION['_ibl_depth_chart_flash']);
            if (isset($flash['errors_html']) && is_string($flash['errors_html'])) {
                $flashErrorsHtml = $flash['errors_html'];
            }
            if (isset($flash['post_data']) && is_array($flash['post_data'])) {
                $override = self::buildFormOverride($flash['post_data']);
            }
        }

        $teamName = $this->getUserTeamName($username);
        $teamid = $this->commonRepository->getTidFromTeamname($teamName) ?? 0;
        $team = Team::initialize($this->db, $teamid);

        \PageLayout\PageLayout::header();

        echo '<h2 class="ibl-title">Depth Chart Entry</h2>';

        if ($flashErrorsHtml !== '') {
            echo '<div class="ibl-alert ibl-alert--error">' . $flashErrorsHtml . '</div>';
        }

        $this->view->renderTeamLogo($teamid);

        // Render saved depth chart dropdown
        $savedDcService = new SavedDepthChartService($this->db);
        $dropdownOptions = $savedDcService->getDropdownOptions($teamid, $season);
        $currentLiveLabel = $savedDcService->buildCurrentLiveLabel($teamid, $season);
        $this->view->renderSavedDepthChartDropdown($dropdownOptions, $currentLiveLabel);

        $this->view->renderHelpSection();

        $playersResult = $this->repository->getPlayersOnTeam($teamid);

        // Compute quality scores and apply any flash override so the form
        // re-renders with the user's last submitted values after a validation
        // failure, not the stale DB values.
        $playersWithQuality = array_map(
            static function (array $player) use ($override): array {
                $player['quality_score'] = self::computeQualityScore($player);
                $pid = $player['pid'];
                if (isset($override[$pid])) {
                    $player = array_merge($player, $override[$pid]);
                }
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
        $this->view->renderFormHeader($teamName, $teamid, $slotNames);

        $depthCount = 1;
        foreach ($playersWithQuality as $player) {
            $this->view->renderPlayerRow($player, $depthCount);
            $depthCount++;
        }

        $this->view->renderFormFooter();
        $this->view->renderMobileView($playersWithQuality, $slotNames);

        echo '<div class="table-scroll-wrapper"><div class="table-scroll-container" tabindex="0" role="region" aria-label="Player ratings">';
        echo $this->getTableOutput($teamid, $display, $split);
        echo '</div></div>';

        // Output JS configuration for saved depth charts
        $jsConfig = json_encode([
            'teamId' => $teamid,
            'apiBaseUrl' => 'modules.php?name=DepthChartEntry&op=api',
            'currentRosterPids' => $currentRosterPids,
        ], JSON_THROW_ON_ERROR);
        echo '<script>window.IBL_DEPTH_CHART_CONFIG = ' . $jsConfig . ';</script>';
        echo '<script src="jslib/depth-chart-changes.js"></script>';
        echo '<script src="jslib/depth-chart-lineup-preview.js"></script>';
        echo '<script src="jslib/saved-depth-charts.js"></script>';
        echo '<script src="jslib/depth-chart-mobile.js"></script>';

        // NextSim position tables section
        $this->renderNextSimSection($teamid, $team, $season);

        \PageLayout\PageLayout::footer();
    }

    /**
     * @see DepthChartEntryControllerInterface::getTableOutput()
     */
    public function getTableOutput(int $teamid, string $display, ?string $split = null): string
    {
        $season = new Season($this->db);
        $team = Team::initialize($this->db, $teamid);

        // Delegate roster + starters to TeamService (single source of truth)
        $rosterData = $this->teamTableService->getRosterAndStarters($teamid);

        $groups = $this->teamTableService->buildDropdownGroups($season);

        $activeValue = ($display === 'split' && $split !== null) ? 'split:' . $split : $display;
        $baseUrl = 'modules.php?name=DepthChartEntry';
        $apiUrl = 'modules.php?name=DepthChartEntry&op=tab-api&teamid=' . $teamid;
        $dropdown = new TableViewDropdown($groups, $activeValue, $baseUrl, $team->color1, $team->color2, $apiUrl);
        $tableHtml = $this->teamTableService->renderTableForDisplay($display, $rosterData['roster'], $team, null, $season, $rosterData['starterPids'], $split);

        return $dropdown->wrap($tableHtml);
    }

    private function renderNextSimSection(int $teamid, Team $team, Season $season): void
    {
        // Load power rankings for SOS tier indicators
        $standingsRepo = new StandingsRepository($this->db);
        $allStreakData = $standingsRepo->getAllStreakData();
        /** @var array<int, float> $teamPowerRankings */
        $teamPowerRankings = [];
        foreach ($allStreakData as $rankedTeamid => $data) {
            $teamPowerRankings[$rankedTeamid] = (float) $data['ranking'];
        }

        $teamScheduleRepository = new TeamScheduleRepository($this->db);
        $nextSimService = new NextSimService($this->db, $teamScheduleRepository, $teamPowerRankings);
        $nextSimView = new NextSimView($season);

        $games = $nextSimService->getNextSimGames($teamid, $season);
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
     * Exact formula from decompiled JSB 5.60 (FUN_004cfa50, lines 90899-90908).
     * All constants resolved from binary .rdata section:
     *
     * dVar58 = (TERM_A + TERM_B + TERM_C) / GP
     *
     * TERM_A (defense):  (OD + DD + PD + TD − 20) × 0.25 × GS × (1/48)
     * TERM_B (production): (AST×0.8 + ORB×(2/3) + (DRB−ORB)×(1/3) + STL − TVR + BLK) × 0.75
     * TERM_C (scoring):  ((FTM−2GM)×(1/6) + (MIN + FTA − (2GA−MIN)×(2/3) + 2GM − FTM×0.5)) × 1.5
     *
     * Note: 3-point stats are entirely absent from dVar58 (offset +0x160 loaded but unused).
     * Note: dc_minutes multiplier (×(dc_minutes+100)) is applied client-side since minutes
     * is a dynamic form input.
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
        $tvr = $player['stats_tvr'] ?? 0;
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

        // 2pt FGM/FGA (JSB uses 2pt stats directly, 3pt stats absent from dVar58)
        $twoPtMade = $fgm - $tpm;
        $twoPtAtt = $fga - $tpa;

        // TERM_A (defense): (OD+DD+PD+TD-20) × 0.25 × GS × (1/48)
        $termA = ($od + $dd + $pd + $td - 20) * 0.25 * $gs / 48.0;

        // TERM_B (production): (AST×0.8 + ORB×(2/3) + (DRB-ORB)×(1/3) + STL - TVR + BLK) × 0.75
        $termB = ($ast * 0.8 + $orb * (2.0 / 3.0) + ($drb - $orb) * (1.0 / 3.0) + $stl - $tvr + $blk) * 0.75;

        // TERM_C (scoring): ((FTM-2GM)×(1/6) + (MIN + FTA - (2GA-MIN)×(2/3) + 2GM - FTM×0.5)) × 1.5
        $termC = (($ftm - $twoPtMade) / 6.0
            + ($min + $fta - ($twoPtAtt - $min) * (2.0 / 3.0) + $twoPtMade - $ftm * 0.5)) * 1.5;

        return round(($termA + $termB + $termC) / $gp, 2);
    }

    /**
     * Build a PID-keyed override map from a submission's raw POST data.
     *
     * Called after a validation-failure redirect so `displayForm()` can
     * re-render the form pre-populated with the user's submitted values
     * instead of the DB values. The same clamps the view applies at render
     * time (0–5 depth, 0–40 minutes, 0/1 canPlayInGame) are applied here
     * so malformed POST input cannot escape into the rendered markup.
     *
     * Form field names use the convention `{field}{N}` where N is the
     * 1-based row index (pid1, pg1, sg1, …, min1, canPlayInGame1).
     *
     * Public for unit-test access; there is no architectural reason to
     * hide a pure static helper with no dependencies.
     *
     * Accepts `array<array-key, mixed>` — after round-tripping through the
     * session, PHPStan loses the narrower `array<string, mixed>` shape, and
     * internal lookups go through string literals anyway.
     *
     * @param array<array-key, mixed> $postData Raw $_POST payload.
     * @return array<int, array{dc_pg_depth: int, dc_sg_depth: int, dc_sf_depth: int, dc_pf_depth: int, dc_c_depth: int, dc_can_play_in_game: int, dc_minutes: int}>
     *         Keyed by player PID. Values use the same `dc_*` keys the view reads
     *         from the player row, so the caller can `array_merge` directly.
     */
    public static function buildFormOverride(array $postData): array
    {
        $override = [];

        for ($i = 1; $i <= 15; $i++) {
            $pidKey = 'pid' . $i;
            if (!isset($postData[$pidKey])) {
                continue;
            }
            $pid = self::intFromPost($postData[$pidKey]);
            if ($pid <= 0) {
                continue;
            }

            $override[$pid] = [
                'dc_pg_depth' => self::clampDepth(self::intFromPost($postData['pg' . $i] ?? 0)),
                'dc_sg_depth' => self::clampDepth(self::intFromPost($postData['sg' . $i] ?? 0)),
                'dc_sf_depth' => self::clampDepth(self::intFromPost($postData['sf' . $i] ?? 0)),
                'dc_pf_depth' => self::clampDepth(self::intFromPost($postData['pf' . $i] ?? 0)),
                'dc_c_depth' => self::clampDepth(self::intFromPost($postData['c' . $i] ?? 0)),
                'dc_can_play_in_game' => self::intFromPost($postData['canPlayInGame' . $i] ?? 0) === 1 ? 1 : 0,
                'dc_minutes' => self::clampMinutes(self::intFromPost($postData['min' . $i] ?? 0)),
            ];
        }

        return $override;
    }

    private static function intFromPost(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        return 0;
    }

    private static function clampDepth(int $value): int
    {
        if ($value < 0) {
            return 0;
        }
        if ($value > 5) {
            return 5;
        }
        return $value;
    }

    private static function clampMinutes(int $value): int
    {
        if ($value < 0) {
            return 0;
        }
        if ($value > 40) {
            return 40;
        }
        return $value;
    }
}
