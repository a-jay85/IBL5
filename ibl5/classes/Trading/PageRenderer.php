<?php

/**
 * Trading_PageRenderer
 * 
 * Handles HTML rendering for trading interface pages
 * Separates presentation logic from business logic
 */
class Trading_PageRenderer
{
    protected $db;
    protected $uiHelper;
    protected $season;

    public function __construct($db)
    {
        $this->db = $db;
        $this->uiHelper = new Trading_UIHelper($db);
        $this->season = new Season($db);
    }

    /**
     * Render the trade offer page
     * @param array $userData User data
     * @param array $userTeamData User team data
     * @param array $partnerTeamData Partner team data
     * @param array $allTeams All teams for selection
     */
    public function renderTradeOfferPage($userData, $userTeamData, $partnerTeamData, $allTeams)
    {
        $teamlogo = $userTeamData['teamname'];
        $teamID = $userTeamData['teamID'];
        $partner = $partnerTeamData['teamname'];

        echo "<form name=\"Trade_Offer\" method=\"post\" action=\"/ibl5/modules/Trading/maketradeoffer.php\">
            <input type=\"hidden\" name=\"offeringTeam\" value=\"$teamlogo\">
            <center>
                <img src=\"images/logo/$teamID.jpg\"><br>
                <table border=1 cellspacing=0 cellpadding=5>
                    <tr>
                        <th colspan=4><center>TRADING MENU</center></th>
                    </tr>
                    <tr>
                        <td valign=top>
                            <table cellspacing=3>
                                <tr>
                                    <td valign=top colspan=4>
                                        <center><b><u>$teamlogo</u></b></center>
                                    </td>
                                </tr>
                                <tr>
                                    <td valign=top><b>Select</b></td>
                                    <td valign=top><b>Pos</b></td>
                                    <td valign=top><b>Name</b></td>
                                    <td valign=top><b>Salary</b></td>";

        // Build and render user team salary data (this echoes player/pick rows)
        $futureSalaryUser = $this->uiHelper->buildTeamFutureSalary($userTeamData['players'], 0);
        $futureSalaryUser = $this->uiHelper->buildTeamFuturePicks($userTeamData['picks'], $futureSalaryUser);
        $switchCounter = $futureSalaryUser['k'];

        echo "</table>
            </td>
            <td valign=top>
                <table cellspacing=3>
                    <tr>
                        <td valign=top align=center colspan=4>
                            <input type=\"hidden\" name=\"switchCounter\" value=\"$switchCounter\">
                            <input type=\"hidden\" name=\"listeningTeam\" value=\"$partner\">
                            <b><u>$partner</u></b>
                        </td>
                    </tr>
                    <tr>
                        <td valign=top><b>Select</b></td>
                        <td valign=top><b>Pos</b></td>
                        <td valign=top><b>Name</b></td>
                        <td valign=top><b>Salary</b></td>
                    </tr>";

        // Build and render partner team salary data (this echoes player/pick rows)
        $futureSalaryPartner = $this->uiHelper->buildTeamFutureSalary($partnerTeamData['players'], $switchCounter);
        $futureSalaryPartner = $this->uiHelper->buildTeamFuturePicks($partnerTeamData['picks'], $futureSalaryPartner);
        $fieldsCounter = $futureSalaryPartner['k'] - 1;

        echo "</table>
            </td>
            <td valign=top>
                <table>
                    <tr>
                        <td valign=top><center><b><u>Make Trade Offer To...</u></b></center>";

        echo $this->uiHelper->renderTeamSelectionLinks($allTeams);

        echo "</td></tr></table>";

        $this->renderSalaryCapSection($teamlogo, $partner, $futureSalaryUser, $futureSalaryPartner);
        $this->renderCashConsiderationsSection($teamlogo, $partner);

        echo "<tr>
                <td colspan=3 align=center>
                    <input type=\"hidden\" name=\"fieldsCounter\" value=\"$fieldsCounter\">
                    <input type=\"submit\" value=\"Make Trade Offer\">
                </td>
            </tr>
        </form></center></table>";
    }

    /**
     * Render the salary cap totals section
     */
    protected function renderSalaryCapSection($teamlogo, $partner, $futureSalaryUser, $futureSalaryPartner)
    {
        $currentSeasonEndingYear = $this->season->endingYear;
        $z = 0;
        $seasonsToDisplay = 6;

        if (
            $this->season->phase == "Playoffs"
            OR $this->season->phase == "Draft"
            OR $this->season->phase == "Free Agency"
        ) {
            $currentSeasonEndingYear++;
            $seasonsToDisplay--;
        }

        while ($z < $seasonsToDisplay) {
            $userCapTotal = $futureSalaryUser['player'][$z] ?? 0;
            $partnerCapTotal = $futureSalaryPartner['player'][$z] ?? 0;
            
            echo "<tr>
                <td>
                    <b>$teamlogo Cap Total in " . ($currentSeasonEndingYear + $z - 1) . "-" . ($currentSeasonEndingYear + $z) . ":</b> " . $userCapTotal . "
                </td>
                <td align=right>
                    <b>$partner Cap Total in " . ($currentSeasonEndingYear + $z - 1) . "-" . ($currentSeasonEndingYear + $z) . ":</b> " . $partnerCapTotal . "
                </td>";
            $z++;
        }
    }

    /**
     * Render the cash considerations section
     */
    protected function renderCashConsiderationsSection($teamlogo, $partner)
    {
        $currentSeasonEndingYear = $this->season->endingYear;
        $i = 1;

        if (
            $this->season->phase == "Playoffs"
            OR $this->season->phase == "Draft"
            OR $this->season->phase == "Free Agency"
        ) {
            $i++;
        }

        while ($i <= 6) {
            echo "<tr>
                <td>
                    <b>$teamlogo send
                    <input type=\"number\" name=\"userSendsCash$i\" value =\"0\" min=\"0\" max =\"2000\">
                    for " . ($currentSeasonEndingYear - 2 + $i) . "-" . ($currentSeasonEndingYear - 1 + $i) . "</b>
                </td>
                <td align=right>
                    <b>$partner send
                    <input type=\"number\" name=\"partnerSendsCash$i\" value =\"0\" min=\"0\" max =\"2000\">
                    for " . ($currentSeasonEndingYear - 2 + $i) . "-" . ($currentSeasonEndingYear - 1 + $i) . "</b>
                </td>
            </tr>";
            $i++;
        }
    }

    /**
     * Render the trade review page
     * @param array $userData User data
     * @param int $teamID Team ID
     * @param resource $tradeOffersResult Database result with trade offers
     * @param array $allTeams All teams for selection
     */
    public function renderTradeReviewPage($userData, $teamID, $tradeOffersResult, $allTeams)
    {
        $teamlogo = $userData['user_ibl_team'];
        
        echo "<center><img src=\"images/logo/$teamID.jpg\"><br>";
        echo "<table>
            <th>
                <tr>
                    <td valign=top>REVIEW TRADE OFFERS";

        $tradeworkingonnow = 0;

        while ($row = $this->db->sql_fetchrow($tradeOffersResult)) {
            $isinvolvedintrade = 0;
            $hashammer = 0;
            $offerid = $row['tradeofferid'];
            $itemid = $row['itemid'];
            $itemtype = $row['itemtype'];
            $from = $row['from'];
            $to = $row['to'];
            $approval = $row['approval'];

            if ($from == $teamlogo) {
                $isinvolvedintrade = 1;
                $oppositeTeam = $to;
            }
            if ($to == $teamlogo) {
                $isinvolvedintrade = 1;
                $oppositeTeam = $from;
            }
            if ($approval == $teamlogo) {
                $hashammer = 1;
            }

            if ($isinvolvedintrade == 1) {
                if ($offerid != $tradeworkingonnow) {
                    $this->renderTradeOfferHeader($offerid, $hashammer, $teamlogo, $oppositeTeam);
                }

                $this->renderTradeItem($itemtype, $itemid, $offerid, $from, $to);
                $tradeworkingonnow = $offerid;
            }
        }

        echo "</td>
            <td valign=top><center><b><u>Make Trade Offer To...</u></b></center>";

        echo $this->uiHelper->renderTeamSelectionLinks($allTeams);

        echo "</td>
            </tr>
            <tr>
                <td colspan=2 align=center>
                    <a href=\"modules.php?name=Waivers&action=drop\">Drop a player to Waivers</a><br>
                    <a href=\"modules.php?name=Waivers&action=add\">Add a player from Waivers</a><br>
                </td>
            </tr>
        </table>";
    }

    /**
     * Render trade offer header with action buttons
     */
    protected function renderTradeOfferHeader($offerid, $hashammer, $teamlogo, $oppositeTeam)
    {
        echo "				</td>
                    </tr>
                </th>
            </table>
            <table border=1 valign=top align=center>
                <tr>
                    <td>
                        <b><u>TRADE OFFER</u></b><br>
                        <table align=right border=1 cellspacing=0 cellpadding=0>
                            <tr>
                                <td valign=center>";
        if ($hashammer == 1) {
            echo "<form name=\"tradeaccept\" method=\"post\" action=\"/ibl5/modules/Trading/accepttradeoffer.php\">
                <input type=\"hidden\" name=\"offer\" value=\"$offerid\">
                <input type=\"submit\" value=\"Accept\" onclick=\"this.disabled=true;this.value='Submitting...'; this.form.submit();\">
            </form>";
        } else {
            echo "(Awaiting Approval)";
        }
        echo "</td>
                <td valign=center>
                    <form name=\"tradereject\" method=\"post\" action=\"/ibl5/modules/Trading/rejecttradeoffer.php\">
                        <input type=\"hidden\" name=\"offer\" value=\"$offerid\">
                        <input type=\"hidden\" name=\"teamRejecting\" value=\"$teamlogo\">
                        <input type=\"hidden\" name=\"teamReceiving\" value=\"$oppositeTeam\">
                        <input type=\"submit\" value=\"Reject\">
                    </form>
                </td>
            </tr>
        </table>";
    }

    /**
     * Render a single trade item (player, pick, or cash)
     */
    protected function renderTradeItem($itemtype, $itemid, $offerid, $from, $to)
    {
        if ($itemtype == 'cash') {
            $dataBuilder = new Trading_TradeDataBuilder($this->db);
            $cashDetails = $dataBuilder->getCashDetails($offerid, $from);
            
            echo "The $from send 
            {$cashDetails[1]} {$cashDetails[2]} {$cashDetails[3]} {$cashDetails[4]} {$cashDetails[5]} {$cashDetails[6]}
            in cash to the $to.<br>";
        } elseif ($itemtype == 0) {
            $dataBuilder = new Trading_TradeDataBuilder($this->db);
            $pickDetails = $dataBuilder->getDraftPickDetails($itemid);
            
            if ($pickDetails) {
                $pickteam = $pickDetails['teampick'] ?? '';
                $pickyear = $pickDetails['year'] ?? '';
                $pickround = $pickDetails['round'] ?? '';
                $picknotes = $pickDetails['notes'] ?? null;

                echo "The $from send the $pickteam $pickyear Round $pickround draft pick to the $to.<br>";
                if ($picknotes != NULL) {
                    echo "<i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . $picknotes . "</i><br>";
                }
            }
        } elseif ($itemtype == 1) {
            $dataBuilder = new Trading_TradeDataBuilder($this->db);
            $playerDetails = $dataBuilder->getPlayerDetails($itemid);
            
            if ($playerDetails) {
                $plyrname = $playerDetails['name'] ?? '';
                $plyrpos = $playerDetails['pos'] ?? '';

                echo "The $from send $plyrpos $plyrname to the $to.<br>";
            }
        }
    }

    /**
     * Render error message for when trades are not allowed
     */
    public function renderTradesNotAllowedMessage($allowWaivers)
    {
        echo "Sorry, but trades are not allowed right now.";
        if ($allowWaivers == 'Yes') {
            echo "<br>
            Players may still be <a href=\"modules.php?name=Waivers&action=add\">Added From Waivers</a> or they may be <a href=\"modules.php?name=Waivers&action=drop\">Dropped to Waivers</a>.";
        } else {
            echo "<br>The waiver wire is also closed.";
        }
    }
}
