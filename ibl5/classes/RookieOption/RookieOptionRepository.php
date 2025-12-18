<?php

declare(strict_types=1);

namespace RookieOption;

use RookieOption\Contracts\RookieOptionRepositoryInterface;

require_once __DIR__ . '/../BaseMysqliRepository.php';

/**
 * @see RookieOptionRepositoryInterface
 */
class RookieOptionRepository extends \BaseMysqliRepository implements RookieOptionRepositoryInterface
{
    /**
     * @see RookieOptionRepositoryInterface::updatePlayerRookieOption()
     */
    public function updatePlayerRookieOption(int $playerID, int $draftRound, int $extensionAmount): bool
    {
        $contractYear = ($draftRound == 1) ? 'cy4' : 'cy3';
        
        // Use prepared statement via BaseMysqliRepository
        $query = "UPDATE ibl_plr SET `{$contractYear}` = ? WHERE pid = ?";
        $affectedRows = $this->execute($query, 'ii', $extensionAmount, $playerID);
        
        return $affectedRows > 0;
    }
}
