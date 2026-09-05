<?php

declare(strict_types=1);

namespace Trading;

use Team\TeamRepository;
use Team\TeamTableService;
use Trading\Contracts\TradeAssetRepositoryInterface;
use Trading\Contracts\TradeRosterPreviewCashRowBuilderInterface;
use Trading\Contracts\TradeRosterPreviewParamValidatorInterface;
use UI\Components\TableViewDropdown;
use Team\Team;
use Season\Season;

/**
 * AJAX JSON endpoint handler for trade roster preview panel
 *
 * Returns a full-roster table showing what a team's roster would look like
 * post-trade, with players added/removed based on the proposed trade.
 * Reuses TeamTableService rendering and TableViewDropdown for dropdown wrapping.
 */
class TradeRosterPreviewApiHandler
{
    private \mysqli $db;
    private TradeAssetRepositoryInterface $tradeAssetRepository;
    private TradeRosterPreviewParamValidatorInterface $paramValidator;
    private TradeRosterPreviewCashRowBuilderInterface $cashRowBuilder;

    /**
     * The logged-in user's team ID, used to decide whether eligibility action
     * links (Rookie Option / Contract Extension) are clickable. When viewing
     * another GM's roster the links render as non-clickable labels.
     */
    private int $loggedInTeamID;
    /**
     * Optional injected Season. When null, methods fall back to new Season($db) (timing identical to today).
     */
    private ?Season $season = null;

    public function __construct(\mysqli $db, TradeAssetRepositoryInterface $tradeAssetRepository, int $loggedInTeamID = 0, ?Season $season = null, ?TradeRosterPreviewParamValidatorInterface $paramValidator = null, ?TradeRosterPreviewCashRowBuilderInterface $cashRowBuilder = null)
    {
        $this->db = $db;
        $this->tradeAssetRepository = $tradeAssetRepository;
        $this->loggedInTeamID = $loggedInTeamID;
        $this->season = $season;
        $this->paramValidator = $paramValidator ?? new TradeRosterPreviewParamValidator();
        $this->cashRowBuilder = $cashRowBuilder ?? new TradeRosterPreviewCashRowBuilder($this->paramValidator);
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $responder = new \Api\Response\HtmlResponder();

        $teamid = $this->paramValidator->validateTeamID();
        if ($teamid === 0) {
            $responder->json(['html' => '']);
            return;
        }

        $addPids = $this->paramValidator->validatePidList('addPids');
        if ($addPids === null) {
            $responder->json(['html' => '']);
            return;
        }

        $removePids = $this->paramValidator->validatePidList('removePids');
        if ($removePids === null) {
            $responder->json(['html' => '']);
            return;
        }

        $display = $this->paramValidator->validateDisplay();

        // Validate split parameter when display is 'split'
        $split = null;
        if ($display === 'split') {
            if (isset($_GET['split']) && is_string($_GET['split'])) {
                $splitRepo = new \Team\SplitStatsRepository($this->db);
                if (in_array($_GET['split'], $splitRepo->getValidSplitKeys(), true)) {
                    $split = $_GET['split'];
                } else {
                    $display = 'ratings';
                }
            } else {
                $display = 'ratings';
            }
        }

        $bufferLevel = ob_get_level();

        try {
            $teamRepository = new TeamRepository($this->db);
            $teamTableService = new TeamTableService($this->db, $teamRepository);

            // Get the base roster and starters
            $rosterData = $teamTableService->getRosterAndStarters($teamid);
            /** @var list<array<string, mixed>> $roster */
            $roster = $rosterData['roster'];
            /** @var list<int> $starterPids */
            $starterPids = $rosterData['starterPids'];

            // Outgoing players stay in the roster — JS grays them out and
            // moves them to the bottom. We only need to append incoming players.

            // Fetch and append incoming players
            if ($addPids !== []) {
                $incomingPlayers = $this->tradeAssetRepository->getPlayersByIds($addPids);
                foreach ($incomingPlayers as $incoming) {
                    $roster[] = $incoming;
                }
            }

            // Append cash rows when viewing contracts
            if ($display === 'contracts') {
                // Existing cash entries from the database
                $cashRepo = new BuyoutLedgerRepository($this->db);
                $existingCash = $cashRepo->getTeamCashConsiderations($teamid);
                foreach ($existingCash as $cashRow) {
                    $roster[] = \Team\TeamTableService::cashConsiderationToRosterRow($cashRow);
                }

                // Synthetic cash rows for the in-progress trade. cashStartYear and
                // cashEndYear are contract-year indices (1–6), so the ceiling is the
                // horizon constant directly — no Season access needed.
                $tradeCashRows = $this->cashRowBuilder->buildCashRows(
                    $teamid,
                    TradeRosterPreviewCashRowBuilder::CASH_YEAR_FORWARD_HORIZON
                );
                foreach ($tradeCashRows as $cashRow) {
                    $roster[] = $cashRow;
                }
            }

            $team = Team::initialize($this->db, $teamid);
            $season = $this->season ?? new Season($this->db);

            // Build PID list for aggregate views
            /** @var list<int> $rosterPids */
            $rosterPids = [];
            foreach ($roster as $row) {
                $pid = $row['pid'] ?? null;
                if (is_int($pid) && $pid > 0) {
                    $rosterPids[] = $pid;
                }
            }

            // Only expose clickable eligibility links when rendering the
            // logged-in user's own roster. Opponent rosters in the trade
            // preview render the markers as non-clickable labels.
            $showActionLinks = $this->loggedInTeamID !== 0 && $teamid === $this->loggedInTeamID;

            $tableHtml = $this->renderTable($display, $roster, $team, $season, $starterPids, $rosterPids, $split, $teamTableService, $removePids, $showActionLinks);

            // Wrap with dropdown
            $dropdownGroups = $teamTableService->buildDropdownGroups($season);
            $activeValue = ($display === 'split' && $split !== null) ? 'split:' . $split : $display;
            $teamData = $teamRepository->getTeam($teamid);
            $color1 = is_string($teamData['color1'] ?? null) ? $teamData['color1'] : '000000';
            $color2 = is_string($teamData['color2'] ?? null) ? $teamData['color2'] : 'FFFFFF';
            $dropdown = new TableViewDropdown($dropdownGroups, $activeValue, '', $color1, $color2);
            $wrappedHtml = $dropdown->wrap($tableHtml);

            $responder->json(['html' => $wrappedHtml]);
        } catch (\Throwable) {
            // Clean up any output buffers left open by rendering code
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            $responder->json(['html' => '']);
        }
    }

    /**
     * Render the appropriate table for the given display mode
     *
     * @param list<array<string, mixed>> $roster Modified roster
     * @param list<int> $starterPids Starter PIDs from original roster
     * @param list<int> $rosterPids All PIDs in the modified roster (for aggregate queries)
     * @param list<int> $removePids Outgoing player PIDs to exclude from cap totals
     * @param bool $showActionLinks When false, Contracts table hides clickable eligibility links
     */
    private function renderTable(string $display, array $roster, Team $team, Season $season, array $starterPids, array $rosterPids, ?string $split, TeamTableService $teamTableService, array $removePids = [], bool $showActionLinks = true): string
    {
        switch ($display) {
            case 'contracts':
                return \UI\Tables\Contracts::render($this->db, $roster, $team, $season, $starterPids, $removePids, $showActionLinks);
            case 'chunk':
                return \BasketballStats\Tables\PeriodAverages::render($this->db, $team, $season, null, null, $starterPids, $rosterPids);
            case 'playoffs':
                return \BasketballStats\Tables\PeriodAverages::render($this->db, $team, $season, $season->playoffsStartDate, $season->playoffsEndDate, $starterPids, $rosterPids);
            case 'split':
                $splitRepo = new \Team\SplitStatsRepository($this->db);
                $splitKey = $split ?? 'home';
                $rows = $splitRepo->getSplitStats($team->teamid, $season->endingYear, $splitKey);
                $rows = array_values(array_filter($rows, static fn (array $r): bool => in_array($r['pid'], $rosterPids, true)));
                $splitLabel = $splitRepo->getSplitLabel($splitKey);
                return \BasketballStats\Tables\SplitStats::render($rows, $team, $splitLabel, $starterPids);
            default:
                return $teamTableService->renderTableForDisplay($display, $roster, $team, null, $season, $starterPids, $split);
        }
    }
}
