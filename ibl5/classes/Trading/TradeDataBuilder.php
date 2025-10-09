<?php

/**
 * Trading_TradeDataBuilder
 * 
 * Handles data retrieval and preparation for trading interface
 * Separates database queries from presentation logic
 */
class Trading_TradeDataBuilder
{
    protected $db;
    protected $sharedFunctions;
    protected $season;

    public function __construct($db)
    {
        $this->db = $db;
        $this->sharedFunctions = new Shared($db);
        $this->season = new Season($db);
    }

    /**
     * Get board configuration
     * @param string $prefix Database table prefix
     * @return array Board configuration
     */
    public function getBoardConfig($prefix)
    {
        $board_config = [];
        $sql = "SELECT * FROM " . $prefix . "_bbconfig";
        $result = $this->db->sql_query($sql);
        while ($row = $this->db->sql_fetchrow($result)) {
            $board_config[$row['config_name']] = $row['config_value'];
        }
        return $board_config;
    }

    /**
     * Get user information by username
     * @param string $username Username
     * @param string $user_prefix User table prefix
     * @return array User information
     */
    public function getUserInfo($username, $user_prefix)
    {
        $sql = "SELECT * FROM " . $user_prefix . "_users WHERE username = '$username'";
        $result = $this->db->sql_query($sql);
        return $this->db->sql_fetchrow($result);
    }

    /**
     * Get team data for trade interface
     * @param string $teamname Team name
     * @return array Team data including ID, players, and picks
     */
    public function getTeamTradeData($teamname)
    {
        $teamID = $this->sharedFunctions->getTidFromTeamname($teamname);
        
        $queryPlayers = "SELECT pos, name, pid, ordinal, cy, cy1, cy2, cy3, cy4, cy5, cy6
            FROM ibl_plr
            WHERE tid = $teamID
            AND retired = '0'
            ORDER BY ordinal ASC";
        $resultPlayers = $this->db->sql_query($queryPlayers);

        $queryPicks = "SELECT *
            FROM ibl_draft_picks
            WHERE ownerofpick = '$teamname'
            ORDER BY year, round ASC";
        $resultPicks = $this->db->sql_query($queryPicks);

        return [
            'teamID' => $teamID,
            'teamname' => $teamname,
            'players' => $resultPlayers,
            'picks' => $resultPicks
        ];
    }

    /**
     * Get all pending trade offers
     * @return resource Database result
     */
    public function getAllTradeOffers()
    {
        $sql = "SELECT * FROM ibl_trade_info ORDER BY tradeofferid ASC";
        return $this->db->sql_query($sql);
    }

    /**
     * Get trade offer details
     * @param int $offerId Trade offer ID
     * @return array Trade offer data
     */
    public function getTradeOfferDetails($offerId)
    {
        $sql = "SELECT * FROM ibl_trade_info WHERE tradeofferid = '$offerId'";
        $result = $this->db->sql_query($sql);
        
        $items = [];
        while ($row = $this->db->sql_fetchrow($result)) {
            $items[] = $row;
        }
        
        return $items;
    }

    /**
     * Get cash details for a trade offer
     * @param int $offerId Trade offer ID
     * @param string $sendingTeam Sending team name
     * @return array Cash details by year
     */
    public function getCashDetails($offerId, $sendingTeam)
    {
        $queryCashDetails = "SELECT * FROM ibl_trade_cash WHERE tradeOfferID = $offerId AND sendingTeam = '$sendingTeam';";
        $cashDetails = $this->db->sql_fetchrow($this->db->sql_query($queryCashDetails));
        
        if (!$cashDetails) {
            return [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        }
        
        return [
            1 => $cashDetails['cy1'] ?? 0,
            2 => $cashDetails['cy2'] ?? 0,
            3 => $cashDetails['cy3'] ?? 0,
            4 => $cashDetails['cy4'] ?? 0,
            5 => $cashDetails['cy5'] ?? 0,
            6 => $cashDetails['cy6'] ?? 0
        ];
    }

    /**
     * Get draft pick details
     * @param int $pickId Pick ID
     * @return array Pick details
     */
    public function getDraftPickDetails($pickId)
    {
        $sql = "SELECT * FROM ibl_draft_picks WHERE pickid = '$pickId'";
        $result = $this->db->sql_query($sql);
        return $this->db->sql_fetchrow($result);
    }

    /**
     * Get player details
     * @param int $playerId Player ID
     * @return array Player details
     */
    public function getPlayerDetails($playerId)
    {
        $sql = "SELECT * FROM ibl_plr WHERE pid = '$playerId'";
        $result = $this->db->sql_query($sql);
        return $this->db->sql_fetchrow($result);
    }
}
