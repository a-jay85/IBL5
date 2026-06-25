<?php

declare(strict_types=1);

namespace RookieOption;

use RookieOption\Contracts\RookieOptionRepositoryInterface;

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
        // IDENTIFIER: $contractYear is one of two fixed column literals (no user
        // input); concatenate it backticked instead of interpolating.
        $contractYear = ($draftRound === 1) ? 'salary_yr4' : 'salary_yr3';

        // Use prepared statement via BaseMysqliRepository
        $query = "UPDATE `ibl_plr` SET `" . $contractYear . "` = ? WHERE pid = ?";
        $affectedRows = $this->execute($query, 'ii', $extensionAmount, $playerID);
        
        return $affectedRows > 0;
    }
}
