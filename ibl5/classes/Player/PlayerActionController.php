<?php

declare(strict_types=1);

namespace Player;

use Negotiation\ExtensionContractDemandCalculator;
use Negotiation\NegotiationRepository;
use Negotiation\NegotiationService;
use Negotiation\NegotiationValidator;
use Player\Stats\PlayerStats;
use Player\Views\PlayerTradingCardFlipView;
use Repositories\Contracts\SalaryCapRepositoryInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use RookieOption\RookieOptionValidator;
use RookieOption\RookieOptionView;
use Season\Season;

/**
 * PlayerActionController - Renders the Player module's negotiate and rookie-option pages
 *
 * Absorbs negotiate() and rookieoption() from modules/Player/index.php. Methods return
 * the HTML body only; index.php owns the page chrome and every superglobal read.
 */
final class PlayerActionController
{
    private \mysqli $mysqliDb;
    private TeamIdentityRepositoryInterface $commonRepo;
    private SalaryCapRepositoryInterface $salaryCapRepo;
    /**
     * Optional injected Season. When null, renderRookieOption() falls back to new Season($db).
     */
    private ?Season $season;

    /**
     * @param \mysqli $mysqliDb MySQLi database connection
     * @param TeamIdentityRepositoryInterface $commonRepo Team identity lookups
     * @param SalaryCapRepositoryInterface $salaryCapRepo Salary cap lookups for negotiation
     * @param ?Season $season Season override for tests
     */
    public function __construct(\mysqli $mysqliDb, TeamIdentityRepositoryInterface $commonRepo, SalaryCapRepositoryInterface $salaryCapRepo, ?Season $season = null)
    {
        $this->mysqliDb = $mysqliDb;
        $this->commonRepo = $commonRepo;
        $this->salaryCapRepo = $salaryCapRepo;
        $this->season = $season;
    }

    /**
     * Render the contract negotiation page body
     *
     * @param int $playerID Player being negotiated with
     * @param string $username Current user's username
     * @param string $prefix Table prefix
     * @param bool $bypassOwnership Whether the debug session lifts the ownership check
     * @return string HTML output
     */
    public function renderNegotiation(int $playerID, string $username, string $prefix, bool $bypassOwnership): string
    {
        // Returns null for a logged-in user with no `ibl_team_info` row (a registered
        // non-GM); passing that into processNegotiation()'s `string $userTeamName` is a
        // TypeError under strict_types, so bail with the same error shape renderRookieOption() uses.
        $userTeamName = $this->commonRepo->getTeamnameFromUsername($username);
        if ($userTeamName === null) {
            return $this->renderErrorWithGoBack('You do not have a team assigned.');
        }

        $service = new NegotiationService(
            $this->mysqliDb,
            new NegotiationRepository($this->mysqliDb, $this->salaryCapRepo),
            new NegotiationValidator($this->mysqliDb),
            new ExtensionContractDemandCalculator($this->mysqliDb, $this->salaryCapRepo),
            $this->commonRepo,
        );

        return $service->processNegotiation($playerID, $userTeamName, $prefix, $bypassOwnership);
    }

    /**
     * Render the rookie option page body
     *
     * @param int $playerID Player the option applies to
     * @param string $username Current user's username
     * @param ?string $error PRG error message from the query string
     * @param ?string $result PRG result code from the query string
     * @param ?string $from Origin tracking from the query string
     * @return string HTML output
     */
    public function renderRookieOption(int $playerID, string $username, ?string $error, ?string $result, ?string $from): string
    {
        $season = $this->season ?? new Season($this->mysqliDb);
        $validator = new RookieOptionValidator();
        $formView = new RookieOptionView();

        $player = Player::withPlayerID($this->mysqliDb, $playerID);

        // Returns null for a logged-in user with no `ibl_team_info` row (a registered
        // non-GM); validatePlayerOwnership() declares `string $userTeamName`, so null is
        // a TypeError under strict_types.
        $userTeamName = $this->commonRepo->getTeamnameFromUsername($username);
        if ($userTeamName === null) {
            return $this->renderErrorWithGoBack('You do not have a team assigned.');
        }

        $ownershipValidation = $validator->validatePlayerOwnership($player, $userTeamName);
        if (!$ownershipValidation->isValid()) {
            return $this->renderErrorWithGoBack($ownershipValidation->getError() ?? '');
        }

        $eligibilityValidation = $validator->validateEligibilityAndGetSalary($player, $season->phase);
        if (!$eligibilityValidation['valid']) {
            return $this->renderErrorWithGoBack($eligibilityValidation['error'] ?? '');
        }

        // Rookie option value (2x final year salary)
        $rookieOptionValue = 2 * ($eligibilityValidation['finalYearSalary'] ?? 0);

        // Trading card (mirrors PlayerPageController::renderPage assembly)
        $playerRepository = new PlayerRepository($this->mysqliDb);
        $playerName = $player->getName() ?? '';
        $asg = $playerRepository->getAllStarGameCount($playerName);
        $threepointcontests = $playerRepository->getThreePointContestCount($playerName);
        $dunkcontests = $playerRepository->getDunkContestCount($playerName);
        $rooksoph = $playerRepository->getRookieSophChallengeCount($playerName);
        $playerStats = PlayerStats::withPlayerID($this->mysqliDb, $playerID);
        $contractDisplay = implode('/', $player->getRemainingContractArray());
        $cardHtml = PlayerTradingCardFlipView::render(
            $player,
            $playerStats,
            $playerID,
            $contractDisplay,
            $asg,
            $threepointcontests,
            $dunkcontests,
            $rooksoph,
            $this->commonRepo
        );

        // Flip card script must come after the card HTML so elements exist for init
        return $formView->renderForm($player, $userTeamName, $rookieOptionValue, $error, $result, $from, $cardHtml)
            . PlayerTradingCardFlipView::getFlipStyles();
    }

    private function renderErrorWithGoBack(string $message): string
    {
        return '<div class="ibl-alert ibl-alert--error">' . \Security\HtmlSanitizer::safeHtmlOutput($message) . '</div>'
            . '<a href="javascript:history.back()" class="ibl-btn ibl-btn--primary ibl-btn--spaced-inline">Go Back</a>';
    }
}
