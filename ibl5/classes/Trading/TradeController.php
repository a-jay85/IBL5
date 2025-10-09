<?php

/**
 * Trading_TradeController
 * 
 * Handles routing and authentication for trading pages
 * Coordinates data retrieval and page rendering
 */
class Trading_TradeController
{
    protected $db;
    protected $dataBuilder;
    protected $pageRenderer;
    protected $uiHelper;
    protected $season;

    public function __construct($db)
    {
        $this->db = $db;
        $this->dataBuilder = new Trading_TradeDataBuilder($db);
        $this->pageRenderer = new Trading_PageRenderer($db);
        $this->uiHelper = new Trading_UIHelper($db);
        $this->season = new Season($db);
    }

    /**
     * Handle trade offer page
     * @param string $username Username
     * @param int $bypass Bypass authentication flag
     * @param string $partner Partner team name (from GET parameter)
     */
    public function handleTradeOffer($username, $bypass = 0, $partner = '')
    {
        global $user, $prefix, $user_prefix;

        if (!$bypass) {
            cookiedecode($user);
        }

        Nuke\Header::header();
        OpenTable();

        $userinfo = $this->dataBuilder->getUserInfo($username, $user_prefix);
        $teamlogo = $userinfo['user_ibl_team'];

        $sharedFunctions = new Shared($this->db);
        $teamID = $sharedFunctions->getTidFromTeamname($teamlogo);
        UI::displaytopmenu($this->db, $teamID);

        // Get user team data
        $userTeamData = $this->dataBuilder->getTeamTradeData($teamlogo);
        
        // Get partner team data
        $partnerTeamData = $this->dataBuilder->getTeamTradeData($partner);

        // Get all teams for selection dropdown
        $allTeams = $this->uiHelper->getAllTeamsForTrading();

        // Render the page (which will call UIHelper to build salary data and render rows)
        $this->pageRenderer->renderTradeOfferPage(
            $userinfo,
            $userTeamData,
            $partnerTeamData,
            $allTeams
        );

        CloseTable();
        Nuke\Footer::footer();
    }

    /**
     * Handle trade review page
     * @param string $username Username
     * @param int $bypass Bypass authentication flag
     */
    public function handleTradeReview($username, $bypass = 0)
    {
        global $user, $prefix, $user_prefix;

        if (!$bypass) {
            cookiedecode($user);
        }

        Nuke\Header::header();
        OpenTable();

        $userinfo = $this->dataBuilder->getUserInfo($username, $user_prefix);
        $teamlogo = $userinfo['user_ibl_team'];

        $sharedFunctions = new Shared($this->db);
        $teamID = $sharedFunctions->getTidFromTeamname($teamlogo);
        UI::displaytopmenu($this->db, $teamID);

        // Get all trade offers
        $tradeOffersResult = $this->dataBuilder->getAllTradeOffers();
        
        // Get all teams for selection dropdown
        $allTeams = $this->uiHelper->getAllTeamsForTrading();

        // Render the page
        $this->pageRenderer->renderTradeReviewPage(
            $userinfo,
            $teamID,
            $tradeOffersResult,
            $allTeams
        );

        CloseTable();
        Nuke\Footer::footer();
    }

    /**
     * Check authentication and route to trade review
     * @param mixed $user User data
     */
    public function routeToTradeReview($user)
    {
        global $cookie, $stop;

        if (!is_user($user)) {
            $this->renderLoginScreen($stop);
            return;
        }

        if ($this->season->allowTrades != 'Yes') {
            $this->renderTradesNotAllowed();
            return;
        }

        cookiedecode($user);
        $this->handleTradeReview($cookie[1]);
    }

    /**
     * Check authentication and route to trade offer
     * @param mixed $user User data
     * @param string $partner Partner team name
     */
    public function routeToTradeOffer($user, $partner = '')
    {
        global $cookie, $stop;

        if (!is_user($user)) {
            $this->renderLoginScreen($stop);
            return;
        }

        cookiedecode($user);
        $this->handleTradeOffer($cookie[1], 0, $partner);
    }

    /**
     * Render login screen
     * @param bool $stop Stop flag
     */
    protected function renderLoginScreen($stop)
    {
        Nuke\Header::header();
        
        if ($stop) {
            OpenTable();
            echo "<center><font class=\"title\"><b>" . _LOGININCOR . "</b></font></center>\n";
            CloseTable();
        } else {
            OpenTable();
            echo "<center><font class=\"title\"><b>" . _USERREGLOGIN . "</b></font></center>\n";
            CloseTable();
        }
        
        OpenTable();
        UI::displaytopmenu($this->db, 0);
        loginbox();
        CloseTable();
        
        Nuke\Footer::footer();
    }

    /**
     * Render message when trades are not allowed
     */
    protected function renderTradesNotAllowed()
    {
        Nuke\Header::header();
        OpenTable();
        UI::displaytopmenu($this->db, 0);
        
        $this->pageRenderer->renderTradesNotAllowedMessage($this->season->allowWaivers);
        
        CloseTable();
        Nuke\Footer::footer();
    }
}
