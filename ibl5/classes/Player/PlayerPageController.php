<?php

declare(strict_types=1);

namespace Player;

use Player\Views\PlayerButtonsView;
use Player\Views\PlayerMenuView;
use Player\Views\PlayerTradingCardFlipView;
use Player\Views\PlayerStatsCardView;
use Player\Views\PlayerStatsFlipCardView;
use Player\Views\PlayerViewFactory;
use Player\Views\TeamColorHelper;
use Services\CommonMysqliRepository;
use Utilities\HtmlSanitizer;

/**
 * PlayerPageController - Orchestrates the player page rendering
 *
 * Absorbs the logic from the showpage() function in modules/Player/index.php.
 * Uses existing PlayerPageService, PlayerViewFactory, and view classes.
 */
class PlayerPageController
{
    private \mysqli $mysqliDb;

    /**
     * @param \mysqli $mysqliDb MySQLi database connection
     */
    public function __construct(\mysqli $mysqliDb)
    {
        $this->mysqliDb = $mysqliDb;
    }

    /**
     * Render the complete player page
     *
     * @param int $playerID Player ID (may be resolved from UUID)
     * @param ?int $pageView Page view type constant
     * @param string $username Current user's username
     * @return string HTML output
     */
    public function renderPage(int $playerID, ?int $pageView, string $username): string
    {
        $sharedRepository = new \Shared\SharedRepository($this->mysqliDb);
        $commonRepository = new CommonMysqliRepository($this->mysqliDb);
        $season = new \Season($this->mysqliDb);

        $player = Player::withPlayerID($this->mysqliDb, $playerID);
        $playerStats = PlayerStats::withPlayerID($this->mysqliDb, $playerID);
        $pageService = new PlayerPageService($this->mysqliDb);

        $html = '';

        // Render result banner from PRG redirect
        $result = $_GET['result'] ?? null;
        if (is_string($result)) {
            $html .= $this->renderResultBanner($result);
        }

        // Generate team color scheme
        $teamColors = TeamColorHelper::getTeamColors($this->mysqliDb, $player->teamID ?? 0);
        $colorScheme = TeamColorHelper::generateColorScheme($teamColors['color1'], $teamColors['color2']);

        // Flip card styles
        $html .= PlayerTradingCardFlipView::getFlipStyles();
        $html .= PlayerStatsFlipCardView::getFlipStyles($colorScheme);

        // Trading card
        $playerRepository = new PlayerRepository($this->mysqliDb);
        $playerName = $player->name ?? '';
        $asg = $playerRepository->getAllStarGameCount($playerName);
        $threepointcontests = $playerRepository->getThreePointContestCount($playerName);
        $dunkcontests = $playerRepository->getDunkContestCount($playerName);
        $rooksoph = $playerRepository->getRookieSophChallengeCount($playerName);

        $contractDisplay = implode('/', $player->getRemainingContractArray());
        $html .= '<tr><td colspan="2">';
        $html .= PlayerTradingCardFlipView::render(
            $player,
            $playerStats,
            $playerID,
            $contractDisplay,
            $asg,
            $threepointcontests,
            $dunkcontests,
            $rooksoph,
            $this->mysqliDb
        );
        $html .= '</td></tr>';

        // Action buttons
        $userTeamName = $commonRepository->getTeamnameFromUsername($username) ?? 'Free Agents';
        $userTeam = \Team::initialize($this->mysqliDb, $userTeamName);

        $html .= $this->renderActionButtons($pageService, $player, $playerID, $userTeam, $season);

        // Navigation menu
        $html .= PlayerMenuView::render($playerID, $pageView, $colorScheme);

        // Content view
        $statsRepository = new PlayerStatsRepository($this->mysqliDb);
        $viewFactory = new PlayerViewFactory($playerRepository, $statsRepository, $commonRepository);

        $html .= $this->renderContentView(
            $viewFactory,
            $pageView,
            $playerID,
            $player,
            $playerStats,
            $season,
            $sharedRepository,
            $colorScheme
        );

        return $html;
    }

    /**
     * Render result banner from PRG redirect
     */
    private function renderResultBanner(string $result): string
    {
        $resultBanners = [
            'rookie_option_success' => ['class' => 'ibl-alert--success', 'message' => 'Rookie option has been exercised successfully. The contract update is reflected on the team page.'],
            'email_failed' => ['class' => 'ibl-alert--warning', 'message' => 'Rookie option exercised, but the notification email failed to send. Please notify the commissioner.'],
        ];

        if (!isset($resultBanners[$result])) {
            return '';
        }

        $banner = $resultBanners[$result];
        $safeMessage = HtmlSanitizer::safeHtmlOutput($banner['message']);
        return '<tr><td colspan="2"><div class="ibl-alert ' . $banner['class'] . '">' . $safeMessage . '</div></td></tr>';
    }

    /**
     * Render action buttons (renegotiation, rookie option)
     */
    private function renderActionButtons(
        PlayerPageService $pageService,
        Player $player,
        int $playerID,
        \Team $userTeam,
        \Season $season
    ): string {
        $html = '';

        if ($pageService->shouldShowRookieOptionUsedMessage($player)) {
            $html .= PlayerButtonsView::renderRookieOptionUsedMessage();
        } elseif ($pageService->canShowRenegotiationButton($player, $userTeam, $season)) {
            $html .= PlayerButtonsView::renderRenegotiationButton($playerID);
        }

        if ($pageService->canShowRookieOptionButton($player, $userTeam, $season)) {
            $html .= PlayerButtonsView::renderRookieOptionButton($playerID);
        }

        return $html;
    }

    /**
     * Render the appropriate content view based on page type
     *
     * @param array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string} $colorScheme
     */
    private function renderContentView(
        PlayerViewFactory $viewFactory,
        ?int $pageView,
        int $playerID,
        Player $player,
        PlayerStats $playerStats,
        \Season $season,
        \Shared\Contracts\SharedRepositoryInterface $sharedRepository,
        array $colorScheme
    ): string {
        $playerName = $player->name ?? '';

        if ($pageView === PlayerPageType::OVERVIEW) {
            $view = $viewFactory->createOverviewView();
            return $view->renderOverview($playerID, $player, $playerStats, $season, $sharedRepository, $colorScheme);
        }

        if ($pageView === PlayerPageType::SIM_STATS) {
            $view = $viewFactory->createSimStatsView();
            return '<tr><td colspan="2">'
                . PlayerStatsCardView::render($view->renderSimStats($playerID), '', $colorScheme)
                . '</td></tr>';
        }

        if ($pageView === PlayerPageType::REGULAR_SEASON_TOTALS || $pageView === PlayerPageType::REGULAR_SEASON_AVERAGES) {
            return $this->renderFlipCardView(
                $viewFactory->createRegularSeasonAveragesView()->renderAverages($playerID),
                $viewFactory->createRegularSeasonTotalsView()->renderTotals($playerID),
                'Regular Season',
                $pageView === PlayerPageType::REGULAR_SEASON_AVERAGES,
                $colorScheme
            );
        }

        if ($pageView === PlayerPageType::PLAYOFF_TOTALS || $pageView === PlayerPageType::PLAYOFF_AVERAGES) {
            return $this->renderFlipCardView(
                $viewFactory->createPlayoffAveragesView()->renderAverages($playerName),
                $viewFactory->createPlayoffTotalsView()->renderTotals($playerName),
                'Playoffs',
                $pageView === PlayerPageType::PLAYOFF_AVERAGES,
                $colorScheme
            );
        }

        if ($pageView === PlayerPageType::HEAT_TOTALS || $pageView === PlayerPageType::HEAT_AVERAGES) {
            return $this->renderFlipCardView(
                $viewFactory->createHeatAveragesView()->renderAverages($playerName),
                $viewFactory->createHeatTotalsView()->renderTotals($playerName),
                'H.E.A.T.',
                $pageView === PlayerPageType::HEAT_AVERAGES,
                $colorScheme
            );
        }

        if ($pageView === PlayerPageType::OLYMPIC_TOTALS || $pageView === PlayerPageType::OLYMPIC_AVERAGES) {
            return $this->renderFlipCardView(
                $viewFactory->createOlympicAveragesView()->renderAverages($playerName),
                $viewFactory->createOlympicTotalsView()->renderTotals($playerName),
                'Olympics',
                $pageView === PlayerPageType::OLYMPIC_AVERAGES,
                $colorScheme
            );
        }

        if ($pageView === PlayerPageType::RATINGS_AND_SALARY) {
            $view = $viewFactory->createRatingsAndSalaryView();
            return '<tr><td colspan="2">'
                . PlayerStatsCardView::wrap($view->renderRatingsAndSalary($playerID), '', '', $colorScheme)
                . '</td></tr>';
        }

        if ($pageView === PlayerPageType::AWARDS_AND_NEWS) {
            $view = $viewFactory->createAwardsAndNewsView();
            return '<tr><td colspan="2">'
                . PlayerStatsCardView::render($view->renderAwardsAndNews($playerName), '', $colorScheme)
                . '</td></tr>';
        }

        if ($pageView === PlayerPageType::ONE_ON_ONE) {
            $view = $viewFactory->createOneOnOneView();
            return '<tr><td colspan="2">'
                . PlayerStatsCardView::render($view->renderOneOnOneResults($playerName), '', $colorScheme)
                . '</td></tr>';
        }

        // Default to overview
        $view = $viewFactory->createOverviewView();
        return $view->renderOverview($playerID, $player, $playerStats, $season, $sharedRepository, $colorScheme);
    }

    /**
     * Render a flip card view (Averages/Totals toggle)
     *
     * @param array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string} $colorScheme
     */
    private function renderFlipCardView(
        string $averagesHtml,
        string $totalsHtml,
        string $label,
        bool $showAveragesFirst,
        array $colorScheme
    ): string {
        return '<tr><td colspan="2">'
            . PlayerStatsFlipCardView::render(
                $averagesHtml,
                $totalsHtml,
                $label,
                $showAveragesFirst,
                $colorScheme
            )
            . '</td></tr>';
    }
}
