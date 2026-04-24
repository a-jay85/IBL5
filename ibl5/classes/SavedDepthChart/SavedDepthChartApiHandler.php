<?php

declare(strict_types=1);

namespace SavedDepthChart;

use Utilities\HtmlSanitizer;
use Team\Team;
use Season\Season;

/**
 * AJAX JSON endpoint handler for saved depth charts
 *
 * Actions: list, load, rename
 */
class SavedDepthChartApiHandler
{
    private \mysqli $db;
    private SavedDepthChartService $service;
    private SavedDepthChartRepository $repository;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->service = new SavedDepthChartService($db);
        $this->repository = new SavedDepthChartRepository($db);
    }

    /**
     * Handle an API request
     *
     * @param string $action The action to perform (list, load, rename, rename-active)
     * @param int $teamid The team ID (already authorized)
     * @param string $username The authenticated username
     * @param array<string, mixed> $params Request parameters
     */
    public function handle(string $action, int $teamid, string $username, array $params): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            switch ($action) {
                case 'list':
                    $this->handleList($teamid);
                    break;
                case 'load':
                    $this->handleLoad($teamid, $params);
                    break;
                case 'rename':
                    $this->handleRename($teamid, $params);
                    break;
                case 'rename-active':
                    $this->handleRenameActive($teamid, $username, $params);
                    break;
                default:
                    $this->sendError('Unknown action', 400);
            }
        } catch (\RuntimeException $e) {
            $this->sendError($e->getMessage(), 500);
        }
    }

    private function handleList(int $teamid): void
    {
        $season = new Season($this->db);
        $options = $this->service->getDropdownOptions($teamid, $season);

        $depthCharts = [];
        $savedDcs = $this->repository->getSavedDepthChartsForTeam($teamid);

        foreach ($savedDcs as $dc) {
            $depthCharts[] = [
                'id' => $dc['id'],
                'name' => $dc['name'],
                'simStartDate' => $dc['sim_start_date'],
                'simEndDate' => $dc['sim_end_date'],
                'simNumberStart' => $dc['sim_number_start'],
                'simNumberEnd' => $dc['sim_number_end'],
                'isActive' => $dc['is_active'] === 1,
                'createdAt' => $dc['created_at'],
            ];
        }

        $currentLiveLabel = $this->service->buildCurrentLiveLabel($teamid, $season);

        echo json_encode([
            'depthCharts' => $depthCharts,
            'options' => $options,
            'currentLiveLabel' => $currentLiveLabel,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function handleLoad(int $teamid, array $params): void
    {
        $rawId = $params['id'] ?? 0;
        $dcId = is_int($rawId) ? $rawId : (is_string($rawId) && is_numeric($rawId) ? (int) $rawId : 0);
        if ($dcId <= 0) {
            $this->sendError('Invalid depth chart ID', 400);
            return;
        }

        // Get current roster PIDs
        $commonRepo = new \Services\CommonMysqliRepository($this->db);
        $teamName = $commonRepo->getTeamnameFromTeamID($teamid) ?? '';

        $depthChartRepo = new \DepthChartEntry\DepthChartEntryRepository($this->db);
        $rosterPlayers = ($teamid > 0) ? $depthChartRepo->getPlayersOnTeam($teamid) : [];
        $currentRosterPids = array_map(
            static fn(array $p): int => $p['pid'],
            $rosterPlayers
        );

        $loaded = $this->service->loadSavedDepthChart($dcId, $teamid, $currentRosterPids);
        if ($loaded === null) {
            $this->sendError('Depth chart not found', 404);
            return;
        }

        $dc = $loaded['depthChart'];

        // Build player data for response
        $players = [];
        foreach ($loaded['players'] as $player) {
            $isOnCurrentRoster = !in_array($player['pid'], $loaded['tradedPids'], true);
            $players[] = [
                'pid' => $player['pid'],
                'playerName' => $player['player_name'],
                'dc_PGDepth' => $player['dc_pg_depth'],
                'dc_SGDepth' => $player['dc_sg_depth'],
                'dc_SFDepth' => $player['dc_sf_depth'],
                'dc_PFDepth' => $player['dc_pf_depth'],
                'dc_CDepth' => $player['dc_c_depth'],
                'dc_canPlayInGame' => $player['dc_can_play_in_game'],
                'dc_minutes' => $player['dc_minutes'],
                'dc_of' => $player['dc_of'],
                'dc_df' => $player['dc_df'],
                'dc_oi' => $player['dc_oi'],
                'dc_di' => $player['dc_di'],
                'dc_bh' => $player['dc_bh'],
                'isOnCurrentRoster' => $isOnCurrentRoster,
            ];
        }

        // Get new players not in saved DC
        $newPlayers = [];
        foreach ($rosterPlayers as $rp) {
            $rpPid = (int) $rp['pid'];
            if (in_array($rpPid, $loaded['newPlayerPids'], true)) {
                $newPlayers[] = [
                    'pid' => $rpPid,
                    'playerName' => (string) ($rp['name'] ?? ''),
                    'pos' => (string) ($rp['pos'] ?? ''),
                ];
            }
        }

        // Render period averages HTML
        $startDate = $dc['sim_start_date'];
        $endDate = $dc['sim_end_date'];
        $statsHtml = '';
        if ($endDate !== null && $endDate !== '') {
            $team = Team::initialize($this->db, $teamid);
            $season = new Season($this->db);
            $statsHtml = \UI\Tables\PeriodAverages::render($this->db, $team, $season, $startDate, $endDate);
        }

        $response = [
            'depthChart' => [
                'id' => $dc['id'],
                'name' => $dc['name'],
                'simStartDate' => $dc['sim_start_date'],
                'simEndDate' => $dc['sim_end_date'],
                'simNumberStart' => $dc['sim_number_start'],
                'simNumberEnd' => $dc['sim_number_end'],
                'isActive' => $dc['is_active'] === 1,
            ],
            'players' => $players,
            'newPlayers' => $newPlayers,
            'statsHtml' => $statsHtml,
        ];

        echo json_encode($response, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function handleRename(int $teamid, array $params): void
    {
        $rawId = $params['id'] ?? 0;
        $dcId = is_int($rawId) ? $rawId : (is_string($rawId) && is_numeric($rawId) ? (int) $rawId : 0);
        $rawNameValue = $params['name'] ?? '';
        $rawName = is_string($rawNameValue) ? $rawNameValue : '';
        $newName = trim(strip_tags($rawName));

        if ($dcId <= 0) {
            $this->sendError('Invalid depth chart ID', 400);
            return;
        }

        if ($newName === '') {
            $this->sendError('Name cannot be empty', 400);
            return;
        }

        if (mb_strlen($newName) > 100) {
            $newName = mb_substr($newName, 0, 100);
        }

        $success = $this->repository->updateName($dcId, $teamid, $newName);

        echo json_encode(['success' => $success, 'name' => $newName], JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function handleRenameActive(int $teamid, string $username, array $params): void
    {
        $rawNameValue = $params['name'] ?? '';
        $rawName = is_string($rawNameValue) ? $rawNameValue : '';
        $newName = trim(strip_tags($rawName));

        if ($newName === '') {
            $this->sendError('Name cannot be empty', 400);
            return;
        }

        if (mb_strlen($newName) > 100) {
            $newName = mb_substr($newName, 0, 100);
        }

        $season = new Season($this->db);
        $result = $this->service->nameOrCreateActive($teamid, $username, $newName, $season);

        echo json_encode($result, JSON_THROW_ON_ERROR);
    }

    private function sendError(string $message, int $httpCode): void
    {
        http_response_code($httpCode);
        echo json_encode(['error' => $message], JSON_THROW_ON_ERROR);
    }
}
