<?php

declare(strict_types=1);

namespace RookieOption;

use RookieOption\Contracts\RookieOptionRepositoryInterface;

/**
 * @see RookieOptionRepositoryInterface
 */
class RookieOptionRepository implements RookieOptionRepositoryInterface
{
    private $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    /**
     * @see RookieOptionRepositoryInterface::updatePlayerRookieOption()
     */
    public function updatePlayerRookieOption(int $playerID, int $draftRound, int $extensionAmount): bool
    {
        $playerID = (int) $playerID;
        $extensionAmount = (int) $extensionAmount;
        $contractYear = ($draftRound == 1) ? 'cy4' : 'cy3';
        
        // Use prepared statement to prevent SQL injection
        $query = "UPDATE ibl_plr SET `" . $contractYear . "` = ? WHERE pid = ?";
        
        if (method_exists($this->db, 'prepare')) {
            // Modern mysqli connection
            $stmt = $this->db->prepare($query);
            $stmt->bind_param('ii', $extensionAmount, $playerID);
            return $stmt->execute();
        } else {
            // Legacy database abstraction layer - use mysqli_real_escape_string or addslashes fallback
            if (isset($this->db->db_connect_id) && $this->db->db_connect_id) {
                $escapedAmount = mysqli_real_escape_string($this->db->db_connect_id, (string) $extensionAmount);
            } else {
                $escapedAmount = addslashes((string) $extensionAmount);
            }
            $legacyQuery = "UPDATE ibl_plr SET `" . $contractYear . "` = '" . $escapedAmount . "' WHERE pid = " . $playerID;
            return $this->db->sql_query($legacyQuery) !== false;
        }
    }
}
